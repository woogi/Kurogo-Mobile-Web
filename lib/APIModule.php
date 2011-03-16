<?php

abstract class APIModule extends Module
{
    protected $responseVersion;
    protected $requestedVersion;
    protected $requestedVmin;
    protected $vmin;
    protected $vmax;
    protected $command = '';
    protected $response; //response object
  
    public function getVmin() {
        return $this->vmin;
    }

    public function getVmax() {
        return $this->vmax;
    }

 /**
   * Set the command
   * @param string the command
   */  
   protected function setCommand($command) {
    $this->command = $command;
  }
  
 /**
   * The module is disabled. 
   */
   protected function moduleDisabled() {
    $error = new KurogoError(2, 'Module Disabled', 'This module has been disabled');
    $this->throwError($error);
  }
  

 /**
   * The module must be run securely (https)
   */
  protected function secureModule() {
    $error = new KurogoError(3, 'Secure access required', 'This module must be used using https');
    $this->throwError($error);
  }

 /**
   * The user cannot access this module
   */
  protected function unauthorizedAccess() {
    $error = new KurogoError(4, 'Unauthorized', 'You are not permitted to use this module');
    $this->throwError($error);
  }

 /**
   * An unrecognized command was requested
   */
  protected function invalidCommand() {
    $error = new KurogoError(5, 'Invalid Command', "The $this->id module does not understand $this->command");
    $this->throwError($error);
  }

 /**
   * The module was unable to load the version requested
   */
  protected function noVersionAvailable() {
    $error = new KurogoError(6, 'No version available', "A command matching the specified version could not be processed");
    $this->throwError($error);
  }

 /**
   * Sets the error portion of the response. Some messages can return both a response and an error
   * @param KurogoError $error an error object
   */
  protected function setResponseError(KurogoError $error) {
    $this->loadResponseIfNeeded();
    $this->response->setError($error);
  }

 /**
   * Set the response text. Typically an object or associate array (a dictionary)
   */
  protected function setResponse($response) {
    $this->loadResponseIfNeeded();
    $this->response->setResponse($response);
  }
 
 /**
   * Throw a fatal error in the API. Used for user created errors like invalid parameters. Stops 
   * execution and displays the error
   * @param KurogoError $user a valid error object
   */
  protected function throwError(KurogoError $error) {
    $this->loadResponseIfNeeded();
    $this->setResponseError($error);
    if (is_null($this->responseVersion)) {
        $this->setResponseVersion(0);
    }
    $this->response->display();
  }

  protected function redirectTo($command, $args=array()) {
    
    $url = URL_BASE . API_URL_PREFIX . "/$this->id/$command";

    if (count($args)) {
      $url .= http_build_query($args);
    }
    
    //error_log('Redirecting to: '.$url);
    header("Location: $url");
    exit;
  }
   /**
     * Factory method
     * @param string $id the module id to load
     * @param string $command the command to execute
     * @param array $args an array of arguments
     * @return APIModule 
     */
    public static function factory($id, $command='', $args=array()) {

        $module = parent::factory($id, 'api');
        if ($command) {
            $module->init($command, $args);
        }

        return $module;
    }
   
    protected function getAllModules() {
        $dirs = array(MODULES_DIR, SITE_MODULES_DIR);
        $modules = array('core'=>CoreAPIModule::factory());
        foreach ($dirs as $dir) {
            if (is_dir($dir)) {
                $d = dir($dir);
                while (false !== ($entry = $d->read())) {
                    if ($entry[0]!='.' && is_dir(sprintf("%s/%s", $dir, $entry))) {
                       try {
                            $module = APIModule::factory($entry);
                            $modules[$entry] = $module;
                        } catch (Exception $e) {
                        }
                    }
                }
                $d->close();
            }
        }
        ksort($modules);    
        return $modules;        
      }


 /**
   * Lazy load the response object
   */
  private function loadResponseIfNeeded() {
    if (!isset($this->response)) {
      $this->response = new APIResponse($this->id, $this->command);
    }
  }
  
 /**
   * Sets the requested version and minimum accepted version
   */
  private function setRequestedVersion($requestedVersion, $minimumVersion) {
    if ($requestedVersion) {
        $this->requestedVersion = intval($requestedVersion);

        if ($minimumVersion) {
            $this->requestedVmin = intval($minimumVersion);
        } else {
            $this->requestedVmin = $this->requestedVersion;
        }
    } else {
        $this->requestedVersion = null;
        $this->requestedVmin = null;
    }
  
  }
  
 /**
   * Called by the modules to set what version we are returning to the client_info
   * @param int $version the version we are returning
   */
  protected function setResponseVersion($version) {
    $this->loadResponseIfNeeded();
    $this->response->setVersion($version);
    $this->responseVersion = $this->response->getVersion();
  }
  
  protected function setContext($context) {
    $this->loadResponseIfNeeded();
    $this->context = $context;
    $this->response->setContext($context);
  }

 /**
   * Initialize the request
   */
   protected function init($command='', $args=array()) {
    parent::init();
    $this->setArgs($args);
    $this->setRequestedVersion($this->getArg('v', null), $this->getArg('vmin', null));
    if ($context = $this->getArg('context', null)) {
        $this->setContext($context);
    }
    $this->setCommand($command);
  }
  
 /**
   * Execute the command. Will call initializeForCommand() which should set the version, error and response
   * values appropriately
   */
  public function executeCommand() {
    $this->loadResponseIfNeeded();
    $this->initializeForCommand();
    $this->response->display();
  }
  
  /**
    * Load config vars from site/<site>/config/api/<name>.ini
    */
  public function loadAPIConfigFile($name, $opts=0) {
      $config = $this->getConfig($name, 'api', $opts);
      return $config->getSectionVars(true);
  }
  
 /**
   * All modules must implement this method to handle the logic of each command.
   */
  abstract protected function initializeForCommand();

}


