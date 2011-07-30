<?php
/**
 * @package		Bullhorn
 * @version		1.0
 * @copyright           Copyright (C)2011 Andrew Brown. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
defined('_JEXEC') or die;

// Include the syndicate functions only once
require_once dirname(__FILE__).'/jobs.php';

// setup
$model = new ModuleBullhornModelJobs();
// create query
$query = array();
$query[] = 'isOpen=1';
if( $params->get('corporation_id', false) ) $query[] = 'clientCorporationID='.$params->get('corporation_id');
$query = implode(' AND ', $query);
// get results limit
$limit = $params->get('limit', 10);
if( $limit > 20 ) $limit = 20;

// get job IDs
$ids = $model->query('JobOrder', $query, "dateAdded DESC", $limit);
// case: no IDs returned
if ( !$ids ) {
    $jobs = array();
}
// case: one ID returned
elseif( $ids && !is_array($ids) ){
    $jobs = array( $model->find('JobOrder', $ids) );
}
// default: get jobs
else {
    $jobs = $model->findMultiple('JobOrder', $ids);
}

// display
require JModuleHelper::getLayoutPath('mod_bullhorn', 'default');