<?php

namespace Stanford\IRB;
/** @var \Stanford\IRB\IRB $module **/
/**
 * Created by PhpStorm.
 * User: LeeAnnY
 * Date: 6/1/2018
 * Time: 9:58 AM
 */
use \REDCap;

class IRB extends \ExternalModules\AbstractExternalModule
{

    public function __construct()
    {
        parent::__construct();
    }

    function emLog() {
        $emLogger = \ExternalModules\ExternalModules::getModuleInstance('em_logger');
        $emLogger->emLog($this->PREFIX, func_get_args(), "INFO");
    }

    function emDebug() {
        // Check if debug enabled
        if ($this->getSystemSetting('enable-system-debug-logging') || $this->getProjectSetting('enable-project-debug-logging')) {
            $emLogger = \ExternalModules\ExternalModules::getModuleInstance('em_logger');
            $emLogger->emLog($this->PREFIX, func_get_args(), "DEBUG");
        }
    }

    function emError() {
        $emLogger = \ExternalModules\ExternalModules::getModuleInstance('em_logger');
        $emLogger->emLog($this->PREFIX, func_get_args(), "ERROR");
    }

    // This will only return true or false depending on whether or not the IRB is valid.
    public function isIRBValid($irb_number, $pid = null)
    {
        $response = $this->IRBStatus($irb_number, $pid);
        if ($response !== false) {
            return (($response["isValid"] == true) and ($response["isPresent"] == true) and ($response["protocolState"] == "APPROVED") ? true : false);
        } else {
            return $response;
        }
    }

    // This will return only an array of personnel on the IRB
    public function getIRBPersonnel($irb_number, $pid = null)
    {
        $response = $this->IRBStatus($irb_number, $pid);
        if ($response["isPresent"] == true) {
            return $response["personnel"];
        } else {
            return false;
        }
    }

    // This will return all data returned from the API call
    public function getAllIRBData($irb_number, $pid = null)
    {
        $response = $this->IRBStatus($irb_number, $pid);
        if ($response["isPresent"] == true) {
            return $response;
        } else {
            return false;
        }
    }

    public function getIRBNumsBySunetID($sunet_id) {
        if (is_null($sunet_id)) {
            $this->emLog("Status is being requested for sunetID " . $sunet_id);
        } else {
            $this->emLog("Redcap user $sunet_id is requesting IRB numbers");
        }

        $token = $this->getIRBToken();

        $header = array("Authorization: Bearer " . $token);
        $api_url = $this->getSystemSetting("irb_url_num") . $sunet_id;
        $response = http_get($api_url, 10, "", $header);
        if ($response == false) {
            $this->emLog("Error calling IRB Validity API for user" . $sunet_id);
            return false;
        } else {
            $this->emLog("Successfully retrieved IRB Status for user: " . $sunet_id);
            $jsonResponse = json_decode($response, true);
            $response = $jsonResponse["protocols"];
            return $response;
        }

    }

    public function getIRBAllBySunetID($sunet_id) {
        if (is_null($sunet_id)) {
            $this->emLog("Status is being requested for user " . $sunet_id);
        } else {
            $this->emLog("Redcap user $sunet_id is requesting status for IRBs");
        }

        $token = $this->getIRBToken();

        $header = array("Authorization: Bearer " . $token);
        $api_url = $this->getSystemSetting("irb_url_all") . $sunet_id;
        $response = http_get($api_url, 10, "", $header);
        if ($response == false) {
            $this->emLog("Error calling IRB Validity API for user " . $sunet_id);
            return false;
        } else {
            $this->emLog("Successfully retrieved IRB Status for user: " . $sunet_id);
            $jsonResponse = json_decode($response, true);
            $responseArray = $jsonResponse["protocols"];

            return $responseArray;
        }

    }

    private function getIRBToken() {
        // Log this request
        $service = "irb";

        // Get a valid API token from the vertx token manager
        $VTM = \ExternalModules\ExternalModules::getModuleInstance('vertx_token_manager');
        $token = $VTM->findValidToken($service);
        if ($token == false) {
            $this->emError("Could not retrieve valid IRB token for service $service");
            return false;
        } else {
            return $token;
        }
    }

    private function IRBStatus($irb_number, $pid)
    {
        if (is_null($pid)) {
            $this->emLog("Status is being requested for IRB " . $irb_number);
        } else {
            $this->emLog("Redcap project $pid is requesting status for IRB " . $irb_number);
        }

        $token = $this->getIRBToken();

        $header = array("Authorization: Bearer " . $token);
        $api_url = $this->getSystemSetting("irb_url") . $irb_number;
        $response = http_get($api_url, 10, "", $header);
        if ($response == false) {
            $this->emLog("Error calling IRB Validity API for project ID " . $pid . " and IRB " . $irb_number);
            return false;
        } else {
            $this->emLog("Successfully retrieved IRB Status for project ID: " . $pid);
            $jsonResponse = json_decode($response, true);
            $response = $jsonResponse["protocols"][0];
            return $response;
        }
    }

}
