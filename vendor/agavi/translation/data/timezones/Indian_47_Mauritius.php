<?php

/**
 * Data file for Indian/Mauritius timezone, compiled from the olson data.
 *
 * Auto-generated by the phing olson task on 03/25/2009 14:53:37
 *
 * @package    agavi
 * @subpackage translation
 *
 * @copyright  Authors
 * @copyright  The Agavi Project
 *
 * @since      0.11.0
 *
 * @version    $Id: Indian_47_Mauritius.php 3974 2009-03-25 14:55:55Z david $
 */

return array (
  'types' => 
  array (
    0 => 
    array (
      'rawOffset' => 14400,
      'dstOffset' => 0,
      'name' => 'MUT',
    ),
    1 => 
    array (
      'rawOffset' => 14400,
      'dstOffset' => 3600,
      'name' => 'MUST',
    ),
  ),
  'rules' => 
  array (
    0 => 
    array (
      'time' => -1988164200,
      'type' => 0,
    ),
    1 => 
    array (
      'time' => 403041600,
      'type' => 1,
    ),
    2 => 
    array (
      'time' => 417034800,
      'type' => 0,
    ),
    3 => 
    array (
      'time' => 1224972000,
      'type' => 1,
    ),
    4 => 
    array (
      'time' => 1238277600,
      'type' => 0,
    ),
  ),
  'finalRule' => 
  array (
    'type' => 'dynamic',
    'offset' => 14400,
    'name' => 'MU%sT',
    'save' => 3600,
    'start' => 
    array (
      'month' => 9,
      'date' => -1,
      'day_of_week' => 1,
      'time' => 7200000,
      'type' => 1,
    ),
    'end' => 
    array (
      'month' => 2,
      'date' => -1,
      'day_of_week' => 1,
      'time' => 7200000,
      'type' => 1,
    ),
    'startYear' => 1984,
  ),
  'name' => 'Indian/Mauritius',
);

?>