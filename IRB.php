<?php

namespace Stanford\IRB;
/**
 * Created by PhpStorm.
 * User: LeeAnnY
 * Date: 6/1/2018
 * Time: 9:58 AM
 */

class IRB extends \ExternalModules\AbstractExternalModule
{

    public function __construct()
    {
        parent::__construct();

        //$this->disableUserBasedSettingPermissions();
        $user_based = $this->areSettingPermissionsUserBased();
        self::log("Are the settings user based: " . ($user_based ? 1 : 0));
    }

    public static function log($obj = "Here", $detail = null, $type = "INFO")
    {
        self::writeLog($obj, $detail, $type);
    }

    public static function debug($obj = "Here", $detail = null, $type = "DEBUG")
    {
        self::writeLog($obj, $detail, $type);
    }

    public static function error($obj = "Here", $detail = null, $type = "ERROR")
    {
        self::writeLog($obj, $detail, $type);
        //TODO: BUBBLE UP ERRORS FOR REVIEW!
    }

    public static function writeLog($obj, $detail, $type)
    {
        $plugin_log_file = \ExternalModules\ExternalModules::getSystemSetting('redcap-em-irb-lookup', 'log_path');

        // Get calling file using php backtrace to help label where the log entry is coming from
        $bt = debug_backtrace();
        $calling_file = $bt[1]['file'];
        $calling_line = $bt[1]['line'];
        $calling_function = $bt[3]['function'];
        if (empty($calling_function)) $calling_function = $bt[2]['function'];
        if (empty($calling_function)) $calling_function = $bt[1]['function'];
        // if (empty($calling_function)) $calling_function = $bt[0]['function'];

        // Convert arrays/objects into string for logging
        if (is_array($obj)) {
            $msg = "(array): " . print_r($obj, true);
        } elseif (is_object($obj)) {
            $msg = "(object): " . print_r($obj, true);
        } elseif (is_string($obj) || is_numeric($obj)) {
            $msg = $obj;
        } elseif (is_bool($obj)) {
            $msg = "(boolean): " . ($obj ? "true" : "false");
        } else {
            $msg = "(unknown): " . print_r($obj, true);
        }

        // Prepend prefix
        if ($detail) $msg = "[$detail] " . $msg;

        // Build log row
        $output = array(
            date('Y-m-d H:i:s'),
            empty($project_id) ? "-" : $project_id,
            basename($calling_file, '.php'),
            $calling_line,
            $calling_function,
            $type,
            $msg
        );

        // Output to plugin log if defined, else use error_log
        if (!empty($plugin_log_file)) {
            file_put_contents(
                $plugin_log_file,
                implode("\t", $output) . "\n",
                FILE_APPEND
            );
        }
        if (!file_exists($plugin_log_file)) {
            // Output to error log
            error_log(implode("\t", $output));
        }
    }

    // This will only return true or false depending on whether or not the IRB is valid.
    public function isValid($irb_number, $pid = null)
    {
        IRB::log("Entered isValid");
        $response = $this->IRBStatus($irb_number, $pid);
        if ($response !== false) {
            return (($response["isValid"] == true) and ($response["isPresent"] == true) and ($response["protocolState"] == "APPROVED") ? true : false);
        } else {
            return $response;
        }
    }

    // This will return only an array of personnel on the IRB
    public function getPersonnel($irb_number, $pid = null)
    {
        $response = $this->IRBStatus($irb_number, $pid);
        if ($response["isPresent"] == true) {
            return $response["personnel"];
        } else {
            return false;
        }
    }

    // This will return all data returned from the API call
    public function returnAll($irb_number, $pid = null)
    {
        $response = $this->IRBStatus($irb_number, $pid);
        if ($response["isPresent"] == true) {
            return $response;
        } else {
            return false;
        }
    }

    private  function IRBStatus($irb_number, $pid)
    {
        // Log this request
        IRB::log("Project " . $pid . " is requesting IRB Status for IRB " . $irb_number);

        // Get a valid API token
        $token = $this->findValidToken();
        if ($token == false) {
            return false;
        }

        // With a valid token, Invoke API for IRB Validity check
        $header = array('Authorization: Bearer ' . $token,
            'Content-Type: application/json');
        $api_url = $this->getSystemSetting("irb_url");
        $ch = curl_init($api_url . $irb_number);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // See if this Get was successful
        $jsonResponse = json_decode($response, true);
        $response = $jsonResponse["protocols"][0];

        if ($http_code !== 200) {
            IRB::error("Error calling IRB Validity API for project ID " . $pid . " and IRB " . $irb_number . ". HTTP response code is " . $http_code, $jsonResponse);
            return false;
        } else {
            IRB::log("Successfully retrieved IRB Status for project ID: " . $pid);
            return $response;
        }
    }

    public function findValidToken() {

        // See if the token is still valid
        $proj_settings = $this->getTokens();
        $todaysDate = date('Y-m-d');

        if (strcmp($proj_settings["irb_token_expiration_date"], $todaysDate) != 0) {
            $token = $this->refreshToken($proj_settings, $todaysDate);
        } else {
            $token = $proj_settings["irb_validity_token"];
        }

        return $token;
    }

    private function getTokens() {
        $settings = array("irb_validity_token" => $this->getSystemSetting("irb_validity_token"),
            "irb_token_expiration_date" => $this->getSystemSetting("irb_token_expiration_date"),
            "irb_refresh_token" => $this->getSystemSetting("irb_refresh_token"));
        return $settings;
    }

    private function saveTokens($access_token, $refresh_token, $expire_date) {
        $this->setSystemSetting("irb_validity_token", $access_token);
        $this->setSystemSetting("irb_refresh_token", $refresh_token);
        $this->setSystemSetting("irb_token_expiration_date", $expire_date);
    }

    private function refreshToken($proj_settings, $todaysDate) {

        $body = json_encode(array("refreshToken" => $proj_settings["irb_refresh_token"]));
        $header = array('Content-Type: application/json',
            'Content-Length: ' . strlen($body));

        $ch = curl_init($this->getSystemSetting("irb_refresh_url"));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $jsonTokenStr = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $jsonToken = json_decode($jsonTokenStr);
        IRB::log("This is the return from refresh token: " . $jsonTokenStr . " and this is the return code: " . $http_code);
        IRB::log("This is json token: " . implode(',', array_keys($jsonToken)));

        // Save the new tokens in the project settings
//        if (isset($jsonToken->refreshToken) && strlen($jsonToken->refreshToken) > 0 &&
//            isset($jsonToken->accessToken) && strlen($jsonToken->accessToken) > 0 ) {
        if ($http_code == 200) {
            $this->saveTokens($jsonToken->refreshToken, $jsonToken->accessToken, $todaysDate);
        } else {
            IRB::error("Problem retrieving new token while refreshing token. HTTP return code: " . $http_code);
            return false;
        }

        return $jsonToken->accessToken;
    }

}
