<?php

class Hub_PackageModel extends PullHubHubBaseModel
{

	public function initialize($context, $parameters)
	{
		parent::initialize($context, $parameters);
	}

	public function getRepos($user = null)
	{
		$results = array();

		foreach (AgaviConfig::get('hub.user_alias', array()) as $alias => $root) {
			if (!is_dir($root)) {
				continue;
			}

			if ($user !== null && $user != $alias) {
				continue;
			}

			$model = $this->context->getModel('Folderhub', 'Hub', array('root' => $root, 'alias' => $alias));

			$results = $model->getRepos();
		}

		$model = $this->context->getModel('Github', 'Hub');

		if (!$user) {
			return array_merge($results, $model->getRepos());
		}

		return array_merge($results, $model->getRepos($user));
	}

	public function getRepo($user, $repo, $tree = null)
	{
		$alias = AgaviConfig::get('hub.user_alias', array());

		$result = null;

		if (key_exists($user, $alias)) {
			$model = $this->context->getModel('Folderhub', 'Hub', array('root' => $alias[$user], 'alias' => $user));

			$result = $model->getRepo($repo);
		}

		if (!$result) {
			$model = $this->context->getModel('Github', 'Hub');

			$result = $model->getRepo($user, $repo, $tree);

			if (!$result) {
				return null;
			}

			$model = $this->context->getModel('Folderhub', 'Hub', array('root' => $result['path'], 'alias' => $result['owner']));

			$model->getTree($result);
		}

		$this->expandManifest($result);

		$model->expandManifest($result);

		// scripts.json => manifest
		$convert = array();

		if (isset($result['scripts']) && is_array($result['scripts'])) {

			foreach ($result['scripts'] as $parent => $scripts) {
				foreach ($scripts as $script_name => $script_manifest) {
					$convert[$parent . '/' . $script_name . '.js'] = array(
						'require' => $script_manifest['deps'],
						'description' => $script_manifest['desc']
					);
				}
			}

		}

		foreach ($result['tree'] as &$file) {
			if ($file['nature'] != 'source') {
				continue;
			}

			if (count($convert)) {

				foreach ($convert as $convert_path => $convert_manifest) {
					if (strpos($file['path'], $convert_path) !== false) {
						$file['manifest'] = $convert_manifest;
						break;
					}
				}

			}


			if (!isset($file['manifest'])) {
				continue;
			}

			if (isset($file['manifest']['require'])) {
				$file['manifest']['require_regex'] = $this->translateMatch($file['manifest']['require']);
			}

			if (isset($file['manifest']['provide'])) {
				$file['manifest']['provide_regex'] = $this->translateMatch($file['manifest']['provide']);
			}
		}

		return $result;
	}

	protected function expandManifest(&$repo)
	{
		if (!isset($repo['manifest'])) {
			$repo['manifest'] = array();
		}

		$manifest =& $repo['manifest'];

		if (!isset($manifest['description'])) {
			if (isset($repo['description'])) {
				$manifest['description'] = $repo['description'];
			} else {
				$manifest['description'] = $repo['name'];
			}
		}

		$nature = array();

		if (!isset($manifest['source'])) {
			if (isset($repo['tree'][$repo['name'] . ':Source'])) {
				$manifest['source'] = 'Source/*.js';
			} else {
				$manifest['source'] = '*.js';
			}
		}

		$nature['source'] = $this->translateMatch($manifest['source']);

		if (!isset($manifest['specs'])) {
			if (isset($repo['tree'][$repo['name'] . ':Specs'])) {
				$manifest['specs'] = 'Specs/*';
			} elseif (isset($repo['tree'][$repo['name'] . ':Specs.js'])) {
				$manifest['specs'] = 'Specs.js';
			}
		}

		if (isset($manifest['specs'])) {
			$nature['specs'] = $this->translateMatch($manifest['specs']);
		}

		if (!isset($manifest['tests'])) {
			if (isset($repo['tree'][$repo['name'] . ':Tests'])) {
				$manifest['tests'] = 'Tests/*';
			} elseif (isset($repo['tree'][$repo['name'] . ':Tests.js'])) {
				$manifest['tests'] = 'Tests.js';
			}
		}

		if (isset($manifest['tests'])) {
			$nature['tests'] = $this->translateMatch($manifest['tests']);
		}

		if (!isset($manifest['demos'])) {
			if (isset($repo['tree'][$repo['name'] . ':Demos'])) {
				$manifest['demos'] = 'Demos/*';
			} elseif (isset($repo['tree'][$repo['name'] . ':Demos.html'])) {
				$manifest['demos'] = 'Demos.html';
			} elseif (isset($repo['tree'][$repo['name'] . ':Demos.js'])) {
				$manifest['demos'] = 'Demos.js';
			}
		}

		if (isset($manifest['demos'])) {
			$nature['demos'] = $this->translateMatch($manifest['demos']);
		}

		if (!isset($manifest['docs'])) {
			if (isset($repo['tree'][$repo['name'] . ':Docs'])) {
				$manifest['docs'] = 'Docs/*';
			} elseif (isset($repo['tree'][$repo['name'] . ':README'])) {
				$manifest['docs'] = 'README';
			} else {
				$manifest['docs'] = '*.md';
			}
		}

		$nature['docs'] = $this->translateMatch($manifest['docs']);

		if (!isset($manifest['assets'])) {
			if (isset($repo['tree'][$repo['name'] . ':Assets'])) {
				$manifest['assets'] = 'Assets/*';
			} else {
				$manifest['assets'] = '*.css, *.gif, *.png, *.jpg';
			}
		}

		if (!isset($manifest['compatibility'])) {
			if (isset($repo['tree'][$repo['name'] . ':Compatibility'])) {
				$manifest['compatibility'] = 'Compatibility/*.js';
			} elseif (isset($repo['tree'][$repo['name'] . ':Compatibility.js'])) {
				$manifest['compatibility'] = 'Compatibility.js';
			}
		}

		if (isset($manifest['compatibility'])) {
			$nature['compatibility'] = $this->translateMatch($manifest['compatibility']);
		}


		$nature['assets'] = $this->translateMatch($manifest['assets']);

		foreach ($repo['tree'] as $key => &$file) {
			$file['nature'] = null;

			$matches = array();

			foreach (array_reverse($nature, true) as $name => $preg) {

				if (!preg_match('/' . $preg . '/', $key, $bits)) {
					continue;
				}
				$last = array_pop($bits);

				$matches[substr_count($last, '/')] =  $name;
			}

			if (count($matches)) {
				ksort($matches);
				$file['nature'] = array_shift($matches);
			}
		}
	}

	protected static function translateMatch($match)
	{
		if (!is_array($match)) {
			$match = preg_split('/\\s*,\\s*/', $match);
		}

		foreach ($match as &$path) {
			$path = explode('*', $path);

			foreach ($path as &$bit) {
				$bit = preg_quote($bit, '/');
			}

			$path = '(?:^|:)((?:\w+\\/)*)' . join('.*', $path) . '(?:\.[a-z0-9]{2,4})?$';
		}
		unset($path);

		return join('|', $match);
	}

}

?>