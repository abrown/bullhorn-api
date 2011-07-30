<?php

/**
 * @package		Bullhorn
 * @version		1.0
 * @copyright           Copyright (C)2011 Andrew Brown. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */
// no direct access
defined('_JEXEC') or die('Restricted access');

jimport('joomla.application.component.view');

class BullhornViewBullhorn extends JView {

    function display($tpl = null) {
        // create toolbar
        if ( $this->getLayout() !== 'test') {
            JToolBarHelper::title('Bullhorn API');
            JToolBarHelper::preferences('com_bullhorn');
            $bar = JToolBar::getInstance('toolbar');
            $url = JURI::base() . 'index.php?option=com_bullhorn&task=test&tmpl=component';
            $bar->appendButton('Popup', 'preview', 'Test Connection', $url);
        }
        // display
        parent::display($tpl);
    }
}