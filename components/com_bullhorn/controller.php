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

// settings
ini_set('soap.wsdl_cache_limit', '50');
error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('America/New_York');

class BullhornController extends JController {

    protected $caching = false;
    protected $displayed = false;
    
    /**
     * Constructor
     */
    public function __construct(){
        parent::__construct();
        // is system caching
        $config = JFactory::getConfig();
        $this->caching = $config->get('caching') >= 1;
        // get cache
        if ( JRequest::getCmd('task') !== 'submit' && $this->caching ){
            $cache = JFactory::getCache($this, 'view'); // group, handler
            $cache->get( $this, JRequest::getCmd('task') ); // view (I use controller), method
            $this->displayed = true;
        }
    }
    
//    /**
//     * Gets Cache ID for current request
//     * @return string 
//     */
//    protected function getCacheID(){
//        return md5(JURI::current());
//    }
//    
//    /**
//     * Caches and displays the current view
//     * @param JView $view
//     */
//    protected function cacheAndDisplay( $view ){
//        // is system caching?
//        $config = JFactory::getConfig();
//        $system_cache_on = $config->get('caching') >= 1;
//        if( !$system_cache_on ){ $view->display(); return; }
//        // get html
//        ob_start();
//        $view->display();
//        $html = ob_get_clean();
//        // cache
//        $cache = JFactory::getCache($this, 'view'); // group, handler
//        $cache->store($html, $this->getCacheID(), 'com_bullhorn'); // id, group
//        // display html
//        echo $html;
//    }
    
    /**
     * Default display
     */
    public function display($task = null, $model = null, $data = null, $cachable = true) {
        // default:
        $this->search();
    }

    /**
     * Displays all available jobs
     */
    public function all() {
        if( $this->displayed ) return;
        $model = $this->getModel('Jobs');
        // get open job IDs
        $ids = $model->query('JobOrder', 'isOpen=1', "dateAdded DESC");
        // create navigation and get jobs
        $index = (JRequest::getVar('page', 1, 'GET', 'INT') - 1) * 20;
        if ($index < 0) $index = 0;
        // case: no IDs returned
        if ( !$ids ) {
            $jobs = array();
        }
        // case: one ID returned
        elseif( $ids && !is_array($ids) ){
            $jobs = array( $model->find('JobOrder', $ids) );
        }
        // case: index too large
        elseif($index > count($ids)){
            $jobs = array();
        }
        // default: get jobs
        else {
            $_ids = array_slice($ids, $index, 20);
            $jobs = $model->findMultiple('JobOrder', $_ids);
        }
        // display view
        $view = $this->getView('bullhorn', 'html', '', array('base_path' => $this->basePath));
        $view->setModel($model, true);
        $view->setLayout('all');
        $view->assignRef('ids', $ids);
        $view->assignRef('jobs', $jobs);
        $view->assignRef('errors', $model->getErrors());
        $view->display();
    }
    
    /**
     * Gets jobs internal to the company; requires 'corporation_id' to be set in admin section
     */
    public function internal() {
        if( $this->displayed ) return;
        $model = $this->getModel('Jobs');
        // get open job IDs
        $interior_id = BullhornModelConfig::get('corporation_id');
        if (!$interior_id) {
            $model->setError('No Corporation ID set in administrator section');
            $jobs = array();
        } else {
            $ids = $model->query('JobOrder', 'isOpen=1 AND clientCorporationID='.$interior_id, "dateAdded DESC");
            // create navigation and get jobs
            $index = (JRequest::getVar('page', 1, 'GET', 'INT') - 1) * 20;
            if ($index < 0) $index = 0;
            // case: no IDs returned
            if ( !$ids ) {
                $jobs = array();
            }
            // case: one ID returned
            elseif( $ids && !is_array($ids) ){
                $jobs = array( $model->find('JobOrder', $ids) );
            }
            // case: index too large
            elseif($index > count($ids)){
                $jobs = array();
            }
            // default: get jobs
            else {
                $_ids = array_slice($ids, $index, 20);
                $jobs = $model->findMultiple('JobOrder', $_ids);
            }
        }
        // display view
        $view = $this->getView('bullhorn', 'html', '', array('base_path' => $this->basePath));
        $view->setModel($model, true);
        $view->setLayout('internal');
        $view->assignRef('ids', $ids);
        $view->assignRef('jobs', $jobs);
        $view->assignRef('errors', $model->getErrors());
        $view->display();
    }
    
    /**
     * Gets recent jobs (for module)
     */
    public function recent() {
        if( $this->displayed ) return;
        // get jobs
        $model = $this->getModel('Jobs');
        $ids = $model->query('JobOrder', 'isOpen=1', "dateAdded DESC", 20);
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
        // display view
        $view = $this->getView('bullhorn', 'html', '', array('base_path' => $this->basePath));
        $view->setModel($model, true);
        $view->setLayout('recent');
        $view->assignRef('ids', $ids);
        $view->assignRef('jobs', $jobs);
        $view->assignRef('errors', $model->getErrors());
        $view->display();
    }
    
    /**
     * Searches jobs
     * Uses title, description, category, and state to filter jobs
     */
    public function search() {
        if( $this->displayed ) return;
        $model = $this->getModel('Jobs');
        // create WHERE query
        $query = JRequest::getString('q', '');
        $category = JRequest::getInt('c', 0);
        $state = JRequest::getWord('s', '');
        $where = array('isOpen=1');
        if( $query ) $where[] = "(title LIKE '%$query%' OR description LIKE '%$query%')";
        if( $category ) $where[] = "$category = some elements(categories)";
        if( $state ) $where[] = "address.state = '$state'";
        // get IDs
        $ids = $model->query('JobOrder', implode(' AND ', $where), "dateAdded DESC");
        // create navigation and get jobs
        $index = (JRequest::getVar('page', 1, 'GET', 'INT') - 1) * 20;
        if ($index < 0) $index = 0;
        // case: no IDs returned
        if ( !$ids ) {
            $jobs = array();
        }
        // case: one ID returned
        elseif( $ids && !is_array($ids) ){
            $jobs = array( $model->find('JobOrder', $ids) );
        }
        // case: index too large
        elseif($index > count($ids)){
            $jobs = array();
        }
        // default: get jobs
        else {
            $_ids = array_slice($ids, $index, 20);
            $jobs = $model->findMultiple('JobOrder', $_ids);
        }
        // display
        $view = $this->getView('bullhorn', 'html', '', array('base_path' => $this->basePath));
        $view->setModel($model, true);
        $view->setLayout('search');
        $view->assignRef('categories', $this->_getCategories());
        $view->assignRef('ids', $ids);
        $view->assignRef('jobs', $jobs);
        $view->assignRef('errors', $model->getErrors());
        $view->display();
    }
    
    /**
     * Gives 'search()' a list of categories
     * @return array 
     */
    private function _getCategories(){
        $model = $this->getModel('Jobs');
        $ids = $model->query('Category', 'enabled=1', "name ASC");
        $categories = array();
        for($i=0; $i<count($ids); $i+=20){
            $_ids = array_slice($ids, $i, 20);
            $categories = array_merge($categories, $model->findMultiple('Category', $_ids));
        }        
        return $categories;
    }
    
    /**
     * Gives 'search()' a list of skills
     * @return type 
     */
    private function _getSkills(){
        $model = $this->getModel('Jobs');
        $ids = $model->query('Specialty', null, "name ASC");
        $skills = array();
        for($i=0; $i<count($ids); $i+=20){
            $_ids = array_slice($ids, $i, 20);
            $skills = array_merge($skills, $model->findMultiple('Specialty', $_ids));
        }    
        return $skills;
    }

    /**
     * Submits an application to the Bullhorn servers
     * @return BullhornController 
     */
    public function submit(){
        // get view
        $view = $this->getView('bullhorn', 'html', '', array('base_path' => $this->basePath));
        $view->setLayout('submit');
        $model = $this->getModel('Jobs');
        $view->setModel($model, true);
        // case: no data
        if( empty($_POST) ){
            $view->display();
        }
        // case: data
        else{
            $post = $model->cleanSubmission($_POST);
            if( array_key_exists('resume', $_FILES) ) $post['resume'] = $_FILES['resume'];
            if( $model->validateSubmission($post) ) $jobSubmissionID = $model->submit($post);
            // display data
            $view->assignRef('POST', $post);
            $view->assignRef('errors', $model->getErrors());
            if( !$model->hasErrors() ){
                $view->assignRef('jobSubmissionID', $jobSubmissionID);
                $view->setLayout('submit-success');
            }
            // display
            $view->display();
        }
        // return
        return $this;
    }
    
    /**
     * View a job
     */
    public function view(){
        if( $this->displayed ) return;
        // get jobs
        $model = $this->getModel('Jobs');
        $id = JRequest::getInt('jobId', 0);
        if( $id <= 0 ) $model->setError('No "jobId" specified.');
        else $job = $model->find('JobOrder', $id);
        // display view
        $view = $this->getView('bullhorn', 'html', '', array('base_path' => $this->basePath));
        $view->setModel($model, true);
        $view->setLayout('view');
        $view->assignRef('job', $job);
        $view->assignRef('errors', $model->getErrors());
        $view->display();
    }
}