<?php
/**
 * @package		Bullhorn
 * @version		1.0
 * @copyright           Copyright (C)2011 Andrew Brown. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

// no direct access
defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

$controller = JController::getInstance('Bullhorn');
$controller->execute(JRequest::getCmd('task'));
$controller->redirect();