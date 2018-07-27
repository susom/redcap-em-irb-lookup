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

    function log() {
        $emLogger = \ExternalModules\ExternalModules::getModuleInstance('em_logger');
        $emLogger->log($this->PREFIX, func_get_args(), "INFO");
    }

    function debug() {
        // Check if debug enabled
        if ($this->getSystemSetting('enable-system-debug-logging') || $this->getProjectSetting('enable-project-debug-logging')) {
            $emLogger = \ExternalModules\ExternalModules::getModuleInstance('em_logger');
            $emLogger->log($this->PREFIX, func_get_args(), "DEBUG");
        }
    }

    function error() {
        $emLogger = \ExternalModules\ExternalModules::getModuleInstance('em_logger');
        $emLogger->log($this->PREFIX, func_get_args(), "ERROR");
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

    private function IRBStatus($irb_number, $pid)
    {
        // Log this request
        $service = "irb";
        if (is_null($pid)) {
            $this->log("Status is being requested for IRB " . $irb_number);
        } else {
            $this->log("Redcap project $pid is requesting status for IRB " . $irb_number);
        }

        // Get a valid API token from the vertx token manager
        $VTM = \ExternalModules\ExternalModules::getModuleInstance('vertx_token_manager');
        $token = $VTM->findValidToken($service);
        if ($token == false) {
            $this->error("Could not retrieve valid IRB token for project $pid and IRB $irb_number");
            return false;
        }

        $header = array("Authorization: Bearer " . $token);
        $api_url = $this->getSystemSetting("irb_url") . $irb_number;
        $response = http_get($api_url, 10, "", $header);
        if ($response == false) {
            $this->log("Error calling IRB Validity API for project ID " . $pid . " and IRB " . $irb_number);
            return false;
        } else {
            $this->log("Successfully retrieved IRB Status for project ID: " . $pid);
            $jsonResponse = json_decode($response, true);
            $response = $jsonResponse["protocols"][0];
            return $response;
        }
    }

}
