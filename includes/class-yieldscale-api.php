<?php

/**
 * Faciliates communication with the YieldScale API
 */
class Yieldscale_API{
    const URL ="https://api.yieldscale.com/v1.1";
    private $api_key;
    private $user_secret;
    private $user_key;

    public function setup($api_key, $user_key){
        $this->api_key = $api_key;
        $this->user_key = $user_key;
    }

    /**
     * the user secret will not be stored. whenever you want to make requests, that require the user_secret,
     * you have to set it manually with this method.
     * @param $user_secret
     */
    public function set_user_secret($user_secret){
        $this->user_secret = $user_secret;
    }

    /**
     * @param $callback_url
     * @return Yieldscale_Response_Login_Link
     */
    public function get_login_link($callback_url){
        return new Yieldscale_Response_Login_Link($this->request("getLoginLink", array('callbackURL' => $callback_url)));
    }

    /**
     * @param $callback_url
     * @return Yieldscale_Response_Login_Link
     */
    public function get_registration_link($callback_url){
        return new Yieldscale_Response_Login_Link($this->request("getRegistrationLink", array('callbackURL' => $callback_url)));
    }

    /**
     * @return YieldScale_Response_Commands
     */
    public function get_available_commands(){
        return new YieldScale_Response_Commands($this->request_no_secret("getAvailableCommands"));
    }

    /**
     * @return YieldScale_Response_Version
     */
    public function get_version(){
        return new YieldScale_Response_Version($this->request_no_secret("getVersionInfo"));
    }

    /**
     * @return YieldScale_Response_Get_User_Key
     */
    public function get_user_key(){
        return new YieldScale_Response_Get_User_Key($this->request_using_secret("getUserKey"));
    }

    /**
     * @param $domain string the domain to get the ad units for (null for all)
     * @return YieldScale_Response_Get_All_Ad_Units
     */
    public function get_all_ad_units($domain){
        $params = array();
        if ($domain) $params['domain'] = $domain;
        return new YieldScale_Response_Get_All_Ad_Units($this->request("getAllAdUnits", $params));
    }

    /**
     * @param id int The id of the YieldScale ad to get the code for
     * @return YieldScale_Response_Get_All_Ad_Units
     */
    public function get_code($id){
        return new Yieldscale_Response_Get_Code($this->request("getCode", array('id' => $id)));
    }




    /**
     * processes a request. use this method for requests, that require the user key to be sent
     * the user key has to be retrieved before it can be used.
     * @param $params array an associative array containing the request parameters
     * @return bool|string the result of the curl request
     */
    private function request($command, $params = array()){
        if (! is_array($params)) $params = array();
        $params['command'] = $command;
        $params['apiKey'] = $this->api_key;
        $params['userKey'] = $this->user_key;
        return $this->process_request($params);
    }

    /**
     * processes a request. use this method for requests, that require the user secret to be sent
     * @param $params array an associative array containing the request parameters
     * @return bool|string the result of the curl request
     */
    private function request_using_secret($command, $params = array()){
        if (! is_array($params)) $params = array();
        $params['command'] = $command;
        $params['apiKey'] = $this->api_key;
        $params['userSecret'] = $this->user_secret;
        return $this->process_request($params);
    }

    /**
     * processes a request. use this method when no user secret is required (like for creating the secret)
     * @param $params array an associative array containing the request parameters
     * @return bool|string the result of the curl request
     */
    private function request_no_secret($command, $params = array()){
        if (! is_array($params)) $params = array();
        $params['command'] = $command;
        $params['apiKey'] = $this->api_key;
        return $this->process_request($params);
    }

    /**
     * @param $params array an associative array containing the request parameters
     * @return bool|string the result of the curl request
     */
    private function process_request($params){
        $ch = curl_init();
        $retu = json_encode ($params);
        curl_setopt_array($ch, [
            CURLOPT_URL => self::URL,
            CURLOPT_POST => true,
            CURLOPT_POSTREDIR => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_POSTFIELDS => $retu
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
}

abstract class Yieldscale_Response{
    const ERROR_CURL = -42000;
    const ERROR_EXCEPTION = -42001;
    const ERROR_RESPONSE = -42002;
    public $error_code;
    public $error_text;

    /**
     * Yieldscale_Response constructor.
     * @param $raw string|bool the raw response data (the result of curl_exec)
     */
    public function __construct($raw)
    {
        if ($raw == false){
            $this->error_code = self::ERROR_CURL;
            $this->error_text = "The request to the YieldScale server could not be completed.";
        }
        else {
            try {
                $data = json_decode($raw);
                if ($data && isset($data->error) && isset($data->error->code)) {
                    $this->error_code = $data->error->code;
                    $this->error_text = $data->error->text;
                    if ($this->error_code == 0) {
                        $this->process_result($data->result);
                    }
                } else {
                    $this->error_code = self::ERROR_RESPONSE;
                    $this->error_text = "Invalid response.";
                }
            } catch (Exception $ex) {
                $this->error_code = self::ERROR_EXCEPTION;
                $this->error_text = $ex->getMessage();
            }
        }
    }

    /**
     * @return bool
     */
    public function has_error(){
        return $this->error_code != null;
    }

    /**
     * when a valid response was received this method will be called for processing the result object.
     * @param $result the result of a successful request
     */
    protected abstract function process_result($result);
}

class YieldScale_Response_Get_User_Key extends Yieldscale_Response {
    /**
     * @var string
     */
    public $user_key;

    /**
     * @var string
     */
    public $user_name;

    protected function process_result($result){
        $this->user_key = $result->userKey;
        if (isset($result->userData)){
            if (isset($result->userData->firstName) && strlen($result->userData->firstName) > 0){
                $this->user_name = $result->userData->firstName;
                if (isset($result->userData->lastName) && strlen($result->userData->lastName) > 0){
                    $this->user_name .= " " . $result->userData->lastName;
                }
            }
            else if (isset($result->userData->lastName) && strlen($result->userData->lastName) > 0){
                $this->user_name = $result->userData->lastName;
            }
            else{
                $this->user_name = null;
            }
        }
    }
}

class YieldScale_Response_Version extends Yieldscale_Response {
    /**
     * @var string
     */
    public $version_id;
    /**
     * @var string a description of the version
     */
    public $version_info;

    protected function process_result($result){
        $this->version_id = $result->versionID;
        $this->version_info = $result->versionInfo;
    }
}

class YieldScale_Response_Commands extends Yieldscale_Response {
    /**
     * @var array a list of available commands
     */
    public $available_commands;

    protected function process_result($result){
        $this->available_commands = $result->commandList;
    }
}

class YieldScale_Response_Get_All_Ad_Units extends Yieldscale_Response {
    /**
     * @var array a list of available ad units
     */
    public $ad_units;

    protected function process_result($result){
        $this->ad_units = $result->AdUnits;
    }
}

class Yieldscale_Response_Get_Code extends Yieldscale_Response {
    /**
     * @var string the ads.txt code
     */
    public $adstxt;
    /**
     * @var string the head code
     */
    public $head;
    /**
     * @var string the body code
     */
    public $body;
    /**
     * @var string the head code for AMP
     */
    public $amphead;
    /**
     * @var string the body code for AMP
     */
    public $ampbody;

    protected function process_result($result){
        //the response contains a key called "ads.txt", that cannot be accessed directly
        foreach ($result as $k => $v){
            if ($k == "ads.txt"){
                $this->adstxt = $v;
                break;
            }
        }
        $this->head = $result->head;
        $this->body = $result->body;
        $this->amphead = $result->amphead;
        $this->ampbody = $result->ampbody;
    }
}

class Yieldscale_Response_Login_Link extends Yieldscale_Response{
    /**
     * @var string
     */
    public $link;

    protected function process_result($result) {
        $this->link = $result->link;
    }
}
?>