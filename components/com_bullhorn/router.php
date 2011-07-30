<?php

/**
 * @package		Bullhorn
 * @version		1.0
 * @copyright           Copyright (C)2011 Andrew Brown. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */
// No direct access
defined('_JEXEC') or die;

/**
 * Builds an SEF-enabled route
 * @param type $query 
 */
function BullhornBuildRoute(&$query) {
    $segments = array();
    if (isset($query['task'])) {
        $segments[] = $query['task'];
        unset($query['task']);
    }
    // page/jobID
    if (isset($query['jobId'])) {
        $segments[] = $query['jobId'];
        unset($query['jobId']);
    }
    elseif (isset($query['page']) ){
        $segments[] = $query['page'];
        unset($query['page']);
    }
    // return
    return $segments;
}

/**
 * Parses an SEF URL
 * @param array $segments 
 */
function BullhornParseRoute($segments) {
    $vars = array();
    $vars['task'] = $segments[0];
    if (isset($segments[1])){
        if( $vars['task'] == 'submit' || $vars['task'] == 'view') $vars['jobId'] = $segments[1];
        else $vars['page'] = $segments[1];
    }
    return $vars;
}
