<?php

/**
 * @package		Bullhorn
 * @version		1.0
 * @copyright           Copyright (C)2011 Andrew Brown. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */
// no direct access
defined('_JEXEC') or die;

jimport('joomla.application.component.model');

class BullhornModelJobs extends JModel {

    private $client;
    private $session;
    private $config;
    private $errors = array();

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
        $this->getSessionKey();
    }

    /**
     * Gets an object from the Bullhorn API
     * @param string $entity
     * @param id $id 
     * @return any
     */
    public function find($entity, $id) {
        if (!$this->getClient())
            $this->setError('Could not access Bullhorn API Client');
        // create query
        $request = new stdClass;
        $request->session = $this->getSessionKey();
        $request->entityName = $entity;
        $request->id = new SoapVar($id, XSD_INT, 'int', "http://www.w3.org/2001/XMLSchema");
        // do query
        try {
            $response = $this->getClient()->find($request);
        } catch (SoapFault $fault) {
            $this->setError($fault->getMessage());
        }
        // return
        return $response->return->dto;
    }

    /**
     * Gets a list of objects from the Bullhorn API
     * @param string $entity
     * @param int $ids list of IDs
     * @return array
     */
    public function findMultiple($entity, $ids) {
        if (!$this->getClient())
            $this->setError('Could not access Bullhorn API Client');
        // create query
        $request = new stdClass;
        $request->session = $this->getSessionKey();
        $request->entityName = $entity;
        $request->ids = array();
        foreach ($ids as $id) {
            $request->ids[] = new SoapVar($id, XSD_INT, 'int', "http://www.w3.org/2001/XMLSchema");
        }
        // do query
        try {
            $response = $this->getClient()->findMultiple($request);
        } catch (SoapFault $fault) {
            $this->setError($fault->getMessage());
        }
        // return
        return $response->return->dtos;
    }

    /**
     * Queries Bullhorn API for entity
     * 
     * SIGNATURE: queryResponse query(query $parameters){}
     * TYPES: struct query {string session; dtoQuery query; }
     * struct dtoQuery { 
      string alias;
      boolean distinct;
      string entityName;
      int maxResults;
      string orderBys;
      parameters parameters;
      string where;
     * }
     * 
     * @param string $entity
     * @param string $where Only use single quotes; e.g. name='Tailwind'
     * @param int $number_of_results
     * @return array 
     */
    public function query($entity, $where = null, $order_by = null, $number_of_results = null) {
        if (!$this->getClient())
            $this->setError('Could not access Bullhorn API Client');
        // create query
        $_dtoQuery = new stdClass();
        $_dtoQuery->entityName = $entity;
        if ($number_of_results)
            $_dtoQuery->maxResults = new SoapVar($number_of_results, XSD_INT, 'int', 'http://www.w3.org/2001/XMLSchema');
        if ($where)
            $_dtoQuery->where = $where;
        if ($order_by)
            $_dtoQuery->orderBys = $order_by;
        $_query = new stdClass();
        $_query->session = $this->getSessionKey();
        $_query->query = new SoapVar($_dtoQuery, XSD_ANYTYPE, 'dtoQuery', 'http://query.entity.bullhorn.com');
        $query = new SoapVar($_query, XSD_ANYTYPE, 'query', 'http://query.entity.bullhorn.com');
        // do query
        try {
            $response = $this->getClient()->query($query);
        } catch (SoapFault $fault) {
            $this->setError($fault->getMessage());
        }
        // set count and last id
        $ids_returned = property_exists($response->return, 'ids');
        if( $ids_returned ){
            $this->last_id = @$response->return->ids[$this->count - 1];
            $this->count = count($response->return->ids);
        }
        else{
            $this->last_id = null;
            $this->count = 0;
        }
        // return
        if( !$ids_returned ) return array();
        else return $response->return->ids;
    }

    /**
     * Get SOAP client instance
     * @return SoapClient 
     */
    public function getClient() {
        if ($this->client === null) {
            $params = array(
                'trace' => 1,
                'soap_version' => SOAP_1_1
            );
            try {
                $this->client = new SoapClient("https://api.bullhornstaffing.com/webservices-2.0/?wsdl", $params);
            } catch (Exception $e) {
                $this->setError('Bullhorn API says: ' . $e->getMessage());
            }
        }
        if (!$this->client)
            $this->setError('Could not connect to the Bullhorn API');
        return $this->client;
    }

    /**
     * Get Bullhorn API session key; calls API method startSession()
     * @return string 
     */
    public function getSessionKey() {
        if (!$this->session) {
            $session_request = new stdClass;
            $session_request->username = BullhornModelConfig::get('username');
            $session_request->password = BullhornModelConfig::get('password');
            $session_request->apiKey = BullhornModelConfig::get('api_key');
            try {
                $startSessionResponse = $this->getClient()->startSession($session_request);
                $this->session = array();
                $this->session['key'] = $startSessionResponse->return->session;
                $this->session['userId'] = $startSessionResponse->return->userId;
                $this->session['corporationId'] = $startSessionResponse->return->corporationId;
            } catch (Exception $e) {
                $this->setError('Bullhorn API says: ' . $e->getMessage());
            }
        }
        if (!$this->session['key'])
            $this->setError('Could not retrieve a session key from the Bullhorn API: username, password or API key may be incorrect');
        return $this->session['key'];
    }
    
    /**
     * Updates session key after request
     * @param SOAP $response 
     */
    private function saveSessionKey($response){
        $this->session['key'] = $response->return->session;
    }

    /**
     * Returns whether the connection has errors
     * @return bool 
     */
    public function hasErrors() {
        return (count($this->errors)) ? true : false;
    }

    /**
     * Adds error message to the error list for display
     * @param string $message 
     */
    public function setError($message, $key = null) {
        if (!is_null($key))
            $this->errors[$key] = $message;
        else
            $this->errors[] = $message;
    }

    /**
     * Returns current errors
     * @return array 
     */
    public function getErrors() {
        return $this->errors;
    }
}

/**
 * 
 */
class BullhornModelConfig {

    static private $config;

    /**
     * Gets Joomla parameters for this component
     * @return JRegistry 
     */
    static public function getConfig() {
        if (self::$config === null) {
            self::$config = JComponentHelper::getParams('com_bullhorn')->toObject();
        }
        return self::$config;
    }

    /**
     * Gets key from config; convenience method for BullhornModelConfig::getConfig()->get(...)
     * @param string $key 
     */
    static public function get($key) {
        if (!property_exists(self::getConfig(), $key))
            return null;
        else
            return self::getConfig()->$key;
    }

}

/**
 * Debugger
 * @param type $thing
 * @return type 
 */
function pr($thing) {
    echo '<pre>';
    if (is_null($thing))
        echo 'NULL';
    elseif (is_bool($thing))
        echo $thing ? 'TRUE' : 'FALSE';
    else
        print_r($thing);
    echo '</pre>';
    return ($thing) ? true : false; // for testing purposes
}

/**
 * Displays last SOAP request/response
 * @param SoapClient $client 
 */
function get_last_soap($client) {
    // get request
    $buffer1 = $client->__getLastRequest();
    $buffer1 = format_xml_string($buffer1);
    // get response
    $buffer2 = $client->__getLastResponse();
    $buffer2 = format_xml_string($buffer2);
    // return
    pr(htmlentities($buffer1)."\n---------------------\n".htmlentities($buffer2));    
}

/**
 * Indents XML
 * From http://recursive-design.com/blog/2007/04/05/format-xml-with-php/
 * @param string $xml
 * @return string 
 */
function format_xml_string($xml) {  
  
  // add marker linefeeds to aid the pretty-tokeniser (adds a linefeed between all tag-end boundaries)
  $xml = preg_replace('/(>)(<)(\/*)/', "$1\n$2$3", $xml);
  
  // now indent the tags
  $token      = strtok($xml, "\n");
  $result     = ''; // holds formatted version as it is built
  $pad        = 0; // initial indent
  $matches    = array(); // returns from preg_matches()
  
  // scan each line and adjust indent based on opening/closing tags
  while ($token !== false) : 
  
    // test for the various tag states
    
    // 1. open and closing tags on same line - no change
    if (preg_match('/.+<\/\w[^>]*>$/', $token, $matches)) : 
      $indent=0;
    // 2. closing tag - outdent now
    elseif (preg_match('/^<\/\w/', $token, $matches)) :
      $pad--;
    // 3. opening tag - don't pad this one, only subsequent tags
    elseif (preg_match('/^<\w[^>]*[^\/]>.*$/', $token, $matches)) :
      $indent=1;
    // 4. no indentation needed
    else :
      $indent = 0; 
    endif;
    
    // pad the line with the required number of leading spaces
    $line    = str_pad($token, strlen($token)+$pad, ' ', STR_PAD_LEFT);
    $result .= $line . "\n"; // add to the cumulative result, with linefeed
    $token   = strtok("\n"); // get the next token
    $pad    += $indent; // update the pad size for subsequent lines    
  endwhile; 
  
  return $result;
}