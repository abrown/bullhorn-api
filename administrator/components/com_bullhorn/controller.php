<?php
/**
 * @package		Bullhorn
 * @version		1.0
 * @copyright           Copyright (C)2011 Andrew Brown. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

class BullhornController extends JController {

    /**
     * Display default template
     */
    public function display() {
        parent::display();
    }
    
    /**
     * Display 'Test Connection' page
     */
    public function test(){
        $model = $this->getModel('Jobs');
        // get open job IDs
        $key = $model->getSessionKey();
        // display view
        $view = $this->getView('bullhorn', 'html', '', array('base_path' => $this->basePath));
        $view->setModel($model, true);
        $view->setLayout('test');
        $view->assignRef('key', $key);
        $view->assignRef('errors', $model->getErrors());
        $view->display();
    }

}
