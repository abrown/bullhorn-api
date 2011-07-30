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
    public $count;
    public $last_id;

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
     * Submits a candidate for a job
     * @param array $POST
     * @return int 
     */
    public function submit($POST) {
        if (!$this->getClient())
            $this->setError('Could not access Bullhorn API Client');
        if (!$POST['jobId'])
            $this->setError('No Job ID given');
        // do process
        try {
            $candidateID = $this->_addCandidate($POST);
            if (!$candidateID) throw new Exception('No candidate ID returned');
            $fileID = $this->_addFile($candidateID, $POST);
            if (!$fileID) throw new Exception('No file ID returned');
            $noteID = $this->_addNote($candidateID, $POST['jobId'], $POST['comments']);
            if (!$fileID) throw new Exception('No file ID returned');
            $jobSubmissionID = $this->_submitCandidate($candidateID, $POST['jobId']);
        } 
        catch (SoapFault $s) {
            $this->setError($s->getMessage());
            //echo '<pre>'.$s->getTraceAsString().'</pre>';
            //get_last_soap($this->getClient());
            //echo '<pre>'.htmlentities($this->getClient()->__getLastRequest()).'</pre>';
            //echo '<pre>'.htmlentities($this->getClient()->__getLastResponse()).'</pre>';
            return false;
        }
        catch(Exception $e){
            $this->setError($e->getMessage());
            //echo '<pre>'.$s->getTraceAsString().'</pre>';
            return false;
        }
        // return
        return @$jobSubmissionID;
    }
    
    /**
     * Cleans and returns data; uses Joomla's JFilterInput to protect from XSS
     * @param array $POST
     * @return array 
     */
    public function cleanSubmission($POST) {
        if (!is_array($POST))
            return null;
        $filter = JFilterInput::getInstance();
        foreach ($POST as &$value) {
            if (is_array($value))
                $this->cleanSubmission($value);
            else
                $value = $filter->clean($value);
        }
        return $POST;
    }

    /**
     * Validates submitted data, adding model errors for incorrect field
     * @param array $POST
     * @return bool 
     */
    public function validateSubmission($POST) {
        if (!is_array($POST)) {
            $this->setError('No data submitted');
            return false;
        }
        if (!array_key_exists('candidate', $POST)) {
            $this->setError('No candidate data submitted');
            return false;
        }
        $error = 0;
        // customer fields 
        if (!array_key_exists('firstName', $POST['candidate']) || !$POST['candidate']['firstName']) {
            $this->setError('A first name is required', 'candidate.firstName');
            $error++;
        }
        if (!array_key_exists('lastName', $POST['candidate']) || !$POST['candidate']['lastName']) {
            $this->setError('A last name is required', 'candidate.lastName');
            $error++;
        }
        if (!array_key_exists('email', $POST['candidate']) || !$POST['candidate']['email']) {
            $this->setError('An e-mail is required', 'candidate.email');
            $error++;
        }
        if (!preg_match('#^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$#i', $POST['candidate']['email'])) {
            $this->setError('E-mail address must be valid', 'candidate.email');
            $error++;
        }
        // file upload
        if (!array_key_exists('resume', $POST)) {
            $this->setError('A resume file is required', 'resume');
            return false;
        }
        if ($POST['resume']['error'] > 0) {
            $this->setError('The file upload failed; please resubmit', 'resume');
            $error++;
        }
        // return
        return ($error) ? false : true;
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
    
    /**
     * Submits a candidate from POST to the Bullhorn API
     * @param array $POST
     * @return int Candidate ID
     * @throws SoapFault 
     */
    private function _addCandidate($POST) {
        // create address
        $_address = new stdClass;
        if (@$POST['candidate']['address']['address1'])
            $_address->address1 = $POST['candidate']['address']['address1'];
        if (@$POST['candidate']['address']['address2'])
            $_address->address2 = $POST['candidate']['address']['address2'];
        if (@$POST['candidate']['address']['city'])
            $_address->city = $POST['candidate']['address']['city'];
        if (@$POST['candidate']['address']['state'])
            $_address->state = $POST['candidate']['address']['state'];
        if (@$POST['candidate']['address']['zip'])
            $_address->zip = $POST['candidate']['address']['zip'];
        // create candidate
        $_candidate = new stdClass;
        $_candidate->firstName = $POST['candidate']['firstName'];
        $_candidate->lastName = $POST['candidate']['lastName'];
        $_candidate->name = $_candidate->firstName . ' ' . $_candidate->lastName;
        $_candidate->email = $POST['candidate']['email'];
        if (@$POST['phone'])
            $_candidate->phone = $POST['candidate']['phone'];
        if (!empty($_address))
            $_candidate->address = $_address;
        $_candidate->username = strtolower($_candidate->firstName.$_candidate->lastName.rand(1000,9999));
        $_candidate->status = 'Available';
        $_candidate->description = '...';
        $_candidate->ownerID = new SoapVar($this->session['userId'], XSD_INT, 'int', "http://www.w3.org/2001/XMLSchema");
        $_candidate->userTypeID = new SoapVar(35, XSD_INT, 'int', "http://www.w3.org/2001/XMLSchema");
        $_candidate->categoryID = new SoapVar(45, XSD_INT, 'int', "http://www.w3.org/2001/XMLSchema");
        $_candidate->password = '...';
        // create request
        $_request = new stdClass();
        $_request->session = $this->getSessionKey();
        $_request->dto = new SoapVar($_candidate, SOAP_ENC_OBJECT, 'candidateDto', 'http://candidate.entity.bullhorn.com/');
        $request = new SoapVar($_request, SOAP_ENC_OBJECT, 'save', 'http://save.apiservice.bullhorn.com/');
        // do request
        $response = $this->getClient()->save($request);
        $this->saveSessionKey($response);
        // return
        return (int) $response->return->dto->userID;
    }

    /**
     * Add candidate to job; creates a JobSubmission
     * @param int $candidateID
     * @param type $jobID
     * @return int jobSubmissionID
     * @throws SoapFault, Exception
     */
    private function _submitCandidate($candidateID, $jobID) {
        if (!$candidateID)
            throw new Exception('No Candidate ID given.');
        if (!$jobID)
            throw new Exception('No Job ID given.');
        // create job submission
        $_submission = new stdClass;
        $_submission->candidateID = new SoapVar($candidateID, XSD_INT, 'int', "http://www.w3.org/2001/XMLSchema");
        $_submission->isDeleted = new SoapVar(false, XSD_BOOLEAN, 'boolean', "http://www.w3.org/2001/XMLSchema");;
        $_submission->jobOrderID = new SoapVar($jobID, XSD_INT, 'int', "http://www.w3.org/2001/XMLSchema");
        $_submission->sendingUserID = new SoapVar($this->session['userId'], XSD_INT, 'int', "http://www.w3.org/2001/XMLSchema");
        $_submission->source = "Bullhorn API";
        $_submission->status = "Submitted";
        // create request
        $_request = new stdClass();
        $_request->session = $this->getSessionKey();
        $_request->dto = new SoapVar($_submission, SOAP_ENC_OBJECT, 'jobSubmissionDto', 'http://job.entity.bullhorn.com/');
        $request = new SoapVar($_request, SOAP_ENC_OBJECT, "save", "http://save.apiservice.bullhorn.com/");
        // do request
        $response = $this->getClient()->save($request);
        $this->saveSessionKey($response);
        // return
        return (int) $response->return->dto->jobSubmissionID;
    }

    /**
     * Adds a file from POSTed data to the given candidate
     * @param int $candidateID
     * @param array $POST
     * @return int File ID
     * @throws SoapFault, Exception
     */
    private function _addFile($candidateID, $POST) {
        if (!$candidateID)
            throw new Exception('No Candidate ID given.');
        // create file metadata
        $_fileMetaData = new stdClass;
        $_fileMetaData->comments = "Added via Bullhorn API";
        list($type, $subtype) = explode('/', $POST['resume']['type']);
        if (!$type)
            $type = 'text';
        if (!$subtype)
            $subtype = 'plain';
        $_fileMetaData->contentSubType = $type;
        $_fileMetaData->contentType = $subtype;
        $_fileMetaData->name = $POST['resume']['name'];
        $_fileMetaData->type = "Resume";
        // create file
        $_file = new stdClass;
        $_file->session = $this->getSessionKey();
        $_file->entityName = 'Candidate';
        $_file->entityId = new SoapVar($candidateID, XSD_INT, 'int', "http://www.w3.org/2001/XMLSchema");
        $_file->fileMetaData = new SoapVar($_fileMetaData, SOAP_ENC_OBJECT, 'fileMeta', "http://meta.apiservice.bullhorn.com/");
        $_file->fileContent = new SoapVar(file_get_contents($POST['resume']['tmp_name']), XSD_BASE64BINARY, 'base64Binary', "http://www.w3.org/2001/XMLSchema");
        // create request
        $request = new SoapVar($_file, SOAP_ENC_OBJECT);
        // do request
        $response = $this->getClient()->addFile($request);
        $this->saveSessionKey($response);
        // return
        return (int) $response->return->id;
    }

    /**
     * Adds note to a candidate and job
     * @param int $candidateID
     * @param int $jobID
     * @return bool
     */
    private function _addNote($candidateID, $jobID, $comments = '') {
        if (!$candidateID)
            throw new Exception('No Candidate ID given.');
        if (!$jobID)
            throw new Exception('No Job ID given.');
        // create note
        $_note = new stdClass;
        $_note->action = 'API Submission Comments';
        $_note->commentingPersonID = new SoapVar($candidateID, XSD_INTEGER, "int", "http://www.w3.org/2001/XMLSchema");
        $_note->personReferenceID = new SoapVar($candidateID, XSD_INTEGER, "int", "http://www.w3.org/2001/XMLSchema");
        $_note->comments = $comments;
        // create request
        $_request = new stdClass;
        $_request->session = $this->getSessionKey();
        $_request->dto = new SoapVar($_note, SOAP_ENC_OBJECT, "noteDto", "http://note.entity.bullhorn.com/");
        $request = new SoapVar($_request, SOAP_ENC_OBJECT, "save", "http://save.apiservice.bullhorn.com/");
        // do request
        $response = $this->getClient()->save($request);
        $this->saveSessionKey($response);
        // get noteID
        $noteID = (int) $response->return->dto->noteID;
        if( !$noteID ) throw new Exception('No Note ID given.');
        // add note reference to candidate
        $_request = array(
            'session' => $this->getSessionKey(),
            'noteId' => new SoapVar($noteID, XSD_INTEGER, "int", "http://www.w3.org/2001/XMLSchema"),
            'entityName' => 'Candidate',
            'entityId' => new SoapVar($candidateID, XSD_INTEGER, "int", "http://www.w3.org/2001/XMLSchema")
        );
        $request = new SoapVar($_request, SOAP_ENC_OBJECT, "addNoteReference", "http://apiservice.bullhorn.com/");
        // do request
        $response = $this->getClient()->addNoteReference($request);
        $this->saveSessionKey($response);
        // add note reference to job
        $_request = array(
            'session' => $this->getSessionKey(),
            'noteId' => new SoapVar($noteID, XSD_INTEGER, "int", "http://www.w3.org/2001/XMLSchema"),
            'entityName' => 'JobOrder',
            'entityId' => new SoapVar($jobID, XSD_INTEGER, "int", "http://www.w3.org/2001/XMLSchema")
        );
        $request = new SoapVar($_request, SOAP_ENC_OBJECT, "addNoteReference", "http://apiservice.bullhorn.com/");
        // do request
        $response = $this->getClient()->addNoteReference($request);
        $this->saveSessionKey($response);
        // return
        return true;
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