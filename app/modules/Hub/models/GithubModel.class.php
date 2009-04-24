<?php

class Hub_GithubModel extends PullHubHubBaseModel
{

  protected $api = 'http://github.com/api/v2/json/';


  protected $login = null;

  protected $token = null;

  protected function fetchURL($url)
  {
    if (($handle = curl_init()) == false) {
      throw new Exception("curl_init error.");
    }

    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);

    curl_setopt($handle, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($handle, CURLOPT_MAXREDIRS, 2);
    curl_setopt($handle, CURLOPT_TIMEOUT, 5);

    curl_setopt($handle, CURLOPT_FAILONERROR, true);

    if ($this->login !== null) {
      curl_setopt($handle, CURLOPT_HTTPAUTH, CURLAUTH_ANYSAFE);
      curl_setopt($handle, CURLOPT_USERPWD, $this->login . ':' . $this->token);
    }

    curl_setopt($handle, CURLOPT_HEADER, false);

    curl_setopt($handle, CURLOPT_USERAGENT, AgaviConfig::get('core.app_name') . ', ' . AgaviConfig::get('agavi.name') . ' on PHP/' . PHP_VERSION);

    curl_setopt($handle, CURLOPT_URL, $url);

    $content = curl_exec($handle);

    if (curl_errno($handle) || $content === false) {
      throw new Exception("curl_exec error for url $url.");
    }

    curl_close($handle);

    sleep(1); // simple protection (max 60 requests/min)

    return $content;
  }

  protected function retrieve($path, $args = null, $reduce = null, $authless = false)
  {
    if ($args !== null) {
      $path = vsprintf($path, array_map('urlencode', (array) $args));
    }

    $cache = $this->context->getModel('Cache', 'Hub');

    $groups = explode('/', $path);

    if (!$authless && $this->login) {
      array_unshift($groups, $this->login);
    }

    if ($cache->checkCache($groups, '1 hour')) {
      return $cache->readCache($groups);
    }

    $raw = $this->fetchURL($this->api . str_replace('%2F', '/', $path));

    if ($reduce !== null) {
      $raw = json_decode($raw, true);

      if (isset($raw['error'])) {
      	throw new Exception('Error from GitHub API: ' . $raw['error'][0]['error']);
      }

      $raw = $raw[$reduce];
    }

    $cache->writeCache($groups, $raw);

    return $raw;
  }

  public function authenticate($login, $token, $verify = true)
  {
    $this->login = $login;
    $this->token = $token;

    if (!$verify) {
      return true;
    }

    try {
      $this->getData('user/show/%s', $login);
    } catch (Exception $e) {
      $this->token = $this->login = null;
      return false;
    }

    return true;
  }

  public function searchRepos($word)
  {
    return $this->retrieve('repos/search/%s', $word, 'repositories');
  }

  public function showRepos($user)
  {
    return $this->retrieve('repos/show/%s', $user, 'repositories');
  }

  public function showRepo($user, $repo)
  {
    return $this->retrieve('repos/show/%s/%s', array($user, $repo), 'repository', true);
  }

  public function showRepoCollaborators($user, $repo)
  {
    return $this->retrieve('repos/show/%s/%s/collaborators', array($user, $repo), 'collaborators', true);
  }

  public function showRepoTags($user, $repo)
  {
    return $this->retrieve('repos/show/%s/%s/tags', array($user, $repo), 'tags', true);
  }

  public function showRepoBranches($user, $repo)
  {
    return $this->retrieve('repos/show/%s/%s/branches', array($user, $repo), 'branches', true);
  }


  public function showTree($user, $repo, $sha)
  {
    return $this->retrieve('tree/show/%s/%s/%s', array($user, $repo, $sha), 'tree', true);
  }

  public function showBlob($user, $repo, $sha, $path)
  {
    return $this->retrieve('blob/show/%s/%s/%s/%s', array($user, $repo, $sha, $path), 'blob', true);
  }


  public function showUser($user)
  {
    return $this->retrieve('user/show/%s', $user, 'user');
  }

  public function getRepos($user = null)
  {
    if ($user === null) {
      $repos = $this->showRepos('mootools');
    } else {
      $repos = $this->showRepos($user);
    }

    foreach ($repos as &$repo) {
      $repo = $this->expandRepo($repo);
    }

    return $repos;
  }

  public function getRepo($user, $repo, $tree = null)
  {
    $repo = $this->showRepo($user, $repo);

    return $this->expandRepo($repo, $tree);
  }

  public function expandRepo($repo, $tree = null)
  {
    $repo['branches'] = $this->showRepoBranches($repo['owner'], $repo['name']);
    $repo['tags'] = $this->showRepoTags($repo['owner'], $repo['name']);

    $stack = array_merge($repo['branches'], $repo['tags']);

    if ($tree === null) {
      $tree = 'master';
    }

    if (!key_exists($tree, $stack)) {
      throw new Exception('Chosen tree does not exist');
    }

    $repo['sha'] = $stack[$tree];

    $repo['tree'] = $this->expandTree($repo['sha'], $repo);

    return $repo;
  }

  protected function expandTree($sha, &$repo, $path = array())
  {
    $tree = array();

    foreach ($this->showTree($repo['owner'], $repo['name'], $sha) as $leave) {
      if (substr($leave['name'], 0, 1) == '.') {
      	continue;
      }

    	$leave['tree_sha'] = $repo['sha'];

      $leave['path'] = $path;
      $leave['depth'] = count($path);
      $leave['path'][] = $leave['name'];

      $tree[join('/', $leave['path']) ] = &$leave;

      if ($leave['name'] == 'manifest.yml') {
        // sfYaml::load();
        $repo['manifest'] = $this->showBlob($repo['owner'], $repo['name'], $repo['sha'], join('/', $leave['path']));
      } elseif ($leave['type'] == 'tree') {
      	$tree = array_merge($tree, $this->expandTree($leave['sha'], $repo, $leave['path']));
      }

      unset($leave);
    }


    return $tree;
  }

}

?>