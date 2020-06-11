<?php
namespace Stanford\IRB;
/** @var \Stanford\IRB\IRB $this **/

use \REDCap;
use Exception;
require_once "emLoggerTrait.php";

class IRB extends \ExternalModules\AbstractExternalModule
{

    use emLoggerTrait;
    private $dpa_prefix;

    public function __construct()
    {
        parent::__construct();
        $this->dpa_prefix = 'DPA-';
    }

    /**
     * This function validates an IRB number and returns true or false.
     *
     * @param $irb_number - IRB Number to verify validity
     * @param null $pid - Optional calling project - used for logging if available
     * @return bool|false - IRB Number is valid or not
     */
    public function isIRBValid($irb_number, $pid = null)
    {
        try {
            $response = $this->IRBStatus($irb_number, $pid);
            $this->emDebug("In isIRBValid response: " . json_encode($response));
        } catch (Exception $ex) {
            $this->emLog("Exception occurred when retrieving IRB Status: " . $ex);
        }

        if ($response !== false) {
            return (($response["isValid"] == "true")  ? true : false);
        } else {
            return $response;
        }
    }

    /**
     * This function validates an DPA number
     *
     * @param $value - value to check for DPA number
     * @return bool|false - is this a DPA?
     */
    public function isDPA($value)
    {
        $same = strncmp($this->dpa_prefix, $value, strlen($this->dpa_prefix));
        return ($same === 0 ? true : false);
    }

    /**
     * This will return only an array of personnel on the IRB
     *
     * @param $irb_number - IRB number being queried
     * @param null $pid - Optional calling project - used for logging if available
     * @return false - if IRB is not found
     *         array of personnel named on the IRB if found
     */
    public function getIRBPersonnel($irb_number, $pid = null)
    {
        try {
            $response = $this->IRBStatus($irb_number, $pid);
        } catch (Exception $ex) {
            $this->emError("Exception occurred when retrieving IRB Status: " . $ex);
        }

        if ($response["isPresent"] == true) {
            return $response["personnel"];
        } else {
            return false;
        }
    }

    /**
     * This will return all available data returned from the API call.  The data includes:
     *  procotol title, expiration date, protocol status, personnel
     *
     * @param $irb_number - IRB number being queried
     * @param null $pid - Optional calling project - used for logging if available
     * @return false if IRB is not found
     *         array of IRB information if found
     */
    public function getAllIRBData($irb_number, $pid = null)
    {
        try {
            $response = $this->IRBStatus($irb_number, $pid);
        } catch (Exception $ex) {
            $this->emError("Exception occurred when retrieving IRB Status: " . $ex);
        }

        if ($response["isPresent"] == true) {
            return $response;
        } else {
            return false;
        }
    }

    /**
     * This function will return all IRB numbers that this sunetID is associated.
     *
     * @param $sunet_id - SunetID being queried for protocols
     * @return false - if an error occurred
     *         array - protocols associated with this sunetID
     */
    public function getIRBNumsBySunetID($sunet_id) {
        if (is_null($sunet_id)) {
            $this->emError("Status is being requested for null sunetID ");
            return false;
        } else {
            $this->emDebug("Redcap user $sunet_id is requesting IRB numbers");
        }

        try {
            $token = $this->getIRBToken();
        } catch (Exception $ex) {
            $this->emError("Exception occurred when retrieving IRB token: " . $ex);
        }

        $header = array("Authorization: Bearer " . $token);
        $api_url = $this->getSystemSetting("irb_url_num") . $sunet_id;
        $response = http_get($api_url, 10, "", $header);
        if ($response == false) {
            $this->emError("Error calling IRB Validity API for user" . $sunet_id);
            return false;
        } else {
            $this->emDebug("Successfully retrieved IRB Status for user: " . $sunet_id);
            $jsonResponse = json_decode($response, true);
            $response = $jsonResponse["protocols"];
            return $response;
        }

    }

    /**
     * This function will retrieve the list of IRB Numbers that this sunetID is associated and also, whether
     * or not the protocol is valid, the expire date and the protocol status.
     *
     * @param $sunet_id - SunetID being queried for protocols
     * @return false - if an error occurred
     *         array - array of protocols associated with this sunetID
     */
    public function getIRBAllBySunetID($sunet_id) {

        // If this user is null, there is nothing to retrieve
        if (is_null($sunet_id)) {
            $this->emError("Status is being requested for Null user");
            return false;
        } else {
            $this->emDebug("Redcap user $sunet_id is requesting status for IRBs");
        }

        // Get a valid token
        try {
            $token = $this->getIRBToken();
        } catch (Exception $ex) {
            $this->emError("Exception occurred when retrieving IRB token: " . $ex);
        }

        if ($token == false) {
            $this->emError("Cannot retrieve a valid IRB token when retrieving IRBs for user $sunet_id");
            return false;
        }

        // Token is valid, retrieve all IRBs associated with this sunetID
        $header = array("Authorization: Bearer " . $token);
        $api_url = $this->getSystemSetting("irb_url_all") . $sunet_id;
        $response = http_get($api_url, 10, "", $header);
        if ($response == false) {
            $this->emError("Error calling IRB Validity API for user " . $sunet_id);
            return false;
        } else {
            $this->emDebug("Successfully retrieved IRB Status for user: " . $sunet_id);
            $jsonResponse = json_decode($response, true);
            $responseArray = $jsonResponse["protocols"];

            return $responseArray;
        }

    }

    /**
     * This function will check the validity of the IRB and if the IRB is not valid, will return false.
     * If the IRB is valid, the privacy settings will be checked and returned.  The privacy settings returned
     * are ones that can be retrieved through an API call to STARR: demographics, labs, dx codes, px codes, and
     * medications. Currently, the EM mrn_lookup uses this but I will convert DDP to use this also.
     *
     * @param $irb_number - IRB number to check for validity and return privacy settings
     * @return array - status - true/false - status is true when IRB is valid otherwise false
     *                 message - returned message
     *                 privacy - Privacy settings when status is true
     */
    public function getPrivacySettings($irb_number, $pid) {

        $privacy_data = array();

        //Check to see if Protocol is valid using IRB Validity API
        if (!is_null($irb_number) and !empty($irb_number)) {

            // If this is a DPA request (Clinical QI/Assessment) project, there is no IRB involved so the
            // record number from pid 9883.  Skip the IRB check.
            if ($this->isDPA($irb_number)) {
                $this->emDebug("Project $pid has entered a Clinical AI/Assessment ($irb_number) number instead of IRB number");
            } else {
                $irb_valid = $this->isIRBValid($irb_number, $pid);
                if (!$irb_valid) {
                    $msg = "* IRB number " . $irb_number . " is not valid from project $pid - might have lapsed or might not be approved";
                    $this->emError($msg);
                    return array("status" => false,
                        "message" => $msg);
                }
            }
        } else {
            $msg = "* Empty IRB number entered from project $pid";
            $this->emError($msg);
            return array("status"           => false,
                         "message"          => $msg);
        }

        // IRB is valid, now check to see if privacy has approved an attestation for this IRB.
        // These are the categories (we are not retrieving all of them - only those in tris_rim.pat_map and tris_rim.rit* tables)
        //      approved => 'Yes' (1), 'No' (0)
        //      lab_results => 1 (Lab test results [non PHI])
        //      billing_codes => 1 (ICDx, CPT, etc [non PHI])
        //      medications => 1 (Medication Orders [non PHI])
        //      demographics => 1 (gender, race, height (latest), weight (latest), etc [non PHI]), 2 (HIPAA identifiers [PHI])
        //      HIPAA identifiers => 1 (Names), 3 (telephone numbers), 4 (address), 5 (dates more precise than year),
        //                           7 (Email address), 8 (Medical record numbers),
        $privacy_report = $this->checkPrivacyReport($irb_number);
        if (is_null($privacy_report) or empty($privacy_report)) {
            $msg = "* Cannot find a privacy record for IRB number " . $irb_number;
            $this->emError($msg);
            return array("status"           => false,
                         "message"          => $msg);
        }

        // Make sure privacy approved this request
        if ($privacy_report['approved'] <> '1') {
            $msg = "* Privacy has not approved your request for IRB number " . $irb_number;
            $this->emError($msg);
            return array("status"           => false,
                         "message"          => $msg);
        }

        // IRB and privacy are approved. Send back the categories that are approved to the caller.
        return array("status"           => true,
                     "message"          => "Found privacy report",
                     "privacy"           => $privacy_report);
    }

    /**
     * This is a helper function that will retrieve the IRB Number from the project setup page.
     *
     * @param $pid - REDCap project number
     * @return null - if the project does not have an IRB number entered
     *         integer - IRB Number if entered in the Project
     */
    public function findIRBNumber($pid) {
        // Find the IRB number for this project
        // Check to make sure pid is an int
        $query = "select project_irb_number from redcap_projects where project_id = " . intval($pid);
        $q = db_query($query);
        $results = db_fetch_row($q);
        if (is_null($results) or empty($results)) {
            return null;
        } else {
            return $results[0];
        }
    }

    /**
     * This function makes a call to the vertx token manager to retrieve a current token for the IRB
     * API call.  The vertx token manager will refresh the token if it is invalid so we expect the returned
     * token to be valid.
     *
     * @return false - if token was not attainable
     *         string token - valid token that can be used in the API call
     * @throws \Exception
     */
    private function getIRBToken() {
        // Log this request
        $service = "irb";

        // Get a valid API token from the vertx token manager
        try {
            $VTM = \ExternalModules\ExternalModules::getModuleInstance('vertx_token_manager');
        } catch (Exception $ex) {
            $this->emError("Exception thrown trying to access vertx_token_manager from getIRBToken(): " . $ex->getMessage());
            exit;
        }
        $token = $VTM->findValidToken($service);
        if ($token == false) {
            $this->emError("Could not retrieve valid IRB token for service $service");
            return false;
        } else {
            return $token;
        }
    }

    /**
     * This function retrieves the valid token and makes the API call to check validity of IRB Status.
     *
     * @param $irb_number - IRB Number to check status
     * @param $pid - only used for logging
     * @return false - if an error is found
     *         array - IRB information retrieved from API call
     * @throws \Exception
     */
    private function IRBStatus($irb_number, $pid)
    {
        if (is_null($irb_number)) {
            $this->emError("Status is being requested for null IRB ");
            return false;
        } else {
            $this->emLog("Redcap project $pid is requesting status for IRB " . $irb_number);
        }

        // Check to see if this a DPA instead of an IRB number and if so, skip because it won't be valid
        if ($this->isDPA($irb_number)) {
            $this->emLog("This is a DPA so skip the IRB check for Redcap project $pid, number " . $irb_number);
            return array("isValid" => "true");
        }

        // Retrieve a valid token
        $token = $this->getIRBToken();
        if ($token == false) {
            $this->emError("Could not retrieve a valid IRB Token for project $pid, IRB $irb_number");
            return false;
        }

        // Retrieve info on this IRB
        $header = array("Authorization: Bearer " . $token);
        $api_url = $this->getSystemSetting("irb_url") . $irb_number;
        $response = http_get($api_url, 10, "", $header);
        if ($response == false) {
            $this->emError("Response from http POST: " . $response);
            $this->emError("Error calling IRB Validity API for project ID " . $pid . " and IRB " . $irb_number);
            return false;
        } else {
            $this->emDebug("Successfully retrieved IRB Status for project ID: " . $pid . ", status: " . $response);
            $jsonResponse = json_decode($response, true);
            $response = $jsonResponse["protocols"][0];
            if (empty($response)) {
                $this->emError("No data for protocol $irb_number");
                return false;
            }
            return $response;
        }
    }

    /**
     * This function will check the status of an IRB number and if valid, check the privacy attestation for an
     * IRB Number. If a privacy attestation is found, the following information will be returned: whether or not
     * the attestation is approved and whether or not the IRB is allowed to have data for labs, medications,
     * billing codes and certain categories of PHI and non-PHI demographics.
     *
     * @param $irb_num
     * @return false - if a privacy attestation is not found
     *         array - privacy settings when found
     */
    private function checkPrivacyReport($irb_num) {

        $privacy = array();

        // Retrieve system settings of where privacy setting projects are
        $privacy_pid = $this->getSystemSetting("new_privacy_pid");
        $privacy_event_id = $this->getSystemSetting("new_privacy_event_id");
        $old_privacy_pid = $this->getSystemSetting("old_privacy_pid");
        $old_privacy_event_id = $this->getSystemSetting("old_privacy_event_id");

        // There are 2 Redcap projects that hold Privacy approval: pid = 9883 for 2018 and newer Privacy approvals
        // and pid 4734 for Privacy Approvals before 2018.  First check 9883 to see if it exists there and if not,
        // go to 4734.
        $privacy_fields_new = array('approved', 'd_lab_results', 'd_diag_proc', 'd_medications', 'd_demographics',
            'd_full_name', 'd_geographic', 'd_dates', 'd_telephone', 'd_fax', 'd_email', 'd_ssn', 'd_mrn', 'd_beneficiary_num',
            'd_insurance_num', 'd_certificate_num', 'd_vehicle_num', 'd_device_num', 'approval_date');

        // If this is a DPA project and not an IRB project, just retrieve the record listed in the DPA number
        // The DPA number is DPA-xxxx where xxxx is the record number of project 9883
        $privacy_record = array();
        if ($this->isDPA($irb_num)) {
            $record_num = substr($irb_num, strlen($this->dpa_prefix));
            $privacy_data = REDCap::getData($privacy_pid, 'array', $record_num, $privacy_fields_new);
            $privacy_event_id = array_keys($privacy_data[$record_num]);
            $privacy_record = $privacy_data[$record_num][$privacy_event_id[0]];
        } else {
            $privacy_filter = "[prj_protocol] = '" . $irb_num . "' and [approved] = 1";
            $privacy_data = REDCap::getData($privacy_pid, 'array', null, $privacy_fields_new, null, null, false, false, false, $privacy_filter);

            if (!is_null($privacy_data) and !empty($privacy_data)) {

                // Check to see which record has the last approval and select that one
                $last_modified_date = array();
                foreach ($privacy_data as $privacy_record_num => $privacy_record) {
                    $last_modified_date[$privacy_record_num] = $privacy_record[$privacy_event_id]['approval_date'];
                }

                // Sorting is most recent first so take the first record
                arsort($last_modified_date);
                $privacy_record_id = array_keys($last_modified_date)[0];
                $privacy_record = $privacy_data[$privacy_record_id][$privacy_event_id];
            }
        }

        if (!is_null($privacy_record) && !empty($privacy_record)) {
            // Convert the format to be the same as the old Privacy Report
            $full_name  = (($privacy_record["d_full_name"][1] === '1')or
                ($privacy_record["d_full_name"][2] === '1') or
                ($privacy_record["d_full_name"][3] === '1') ? '1' : '0');
            $ssn  = (($privacy_record["d_ssn"][1] === '1') or
                ($privacy_record["d_ssn"][2] === '1') or
                ($privacy_record["d_ssn"][3] === '1') ? '1' : '0');
            $phone      = (($privacy_record["d_telephone"][1] === '1') or
                ($privacy_record["d_telephone"][2] === '1') or
                ($privacy_record["d_telephone"][3] === '1') ? '1' : '0');
            $geography  = (($privacy_record["d_geographic"][1] === '1') or
                ($privacy_record["d_geographic"][2] === '1') or
                ($privacy_record["d_geographic"][3] === '1') ? '1' : '0');
            $dates      = (($privacy_record["d_dates"][1] === '1') or
                ($privacy_record["d_dates"][2] === '1') or
                ($privacy_record["d_dates"][3] === '1') ? '1' : '0');
            $fax      = (($privacy_record["d_fax"][1] === '1') or
                ($privacy_record["d_fax"][2] === '1') or
                ($privacy_record["d_fax"][3] === '1') ? '1' : '0');
            $email      = (($privacy_record["d_email"][1] === '1') or
                ($privacy_record["d_email"][2] === '1') or
                ($privacy_record["d_email"][3] === '1') ? '1' : '0');
            $mrn        = (($privacy_record["d_mrn"][1] === '1') or
                ($privacy_record["d_mrn"][2] === '1') or
                ($privacy_record["d_mrn"][3] === '1') ? '1' : '0');
            $health  = (($privacy_record["d_beneficiary_num"][1] === '1') or
                ($privacy_record["d_beneficiary_num"][2] === '1') or
                ($privacy_record["d_beneficiary_num"][3] === '1') ? '1' : '0');
            $accounts  = (($privacy_record["d_insurance_num"][1] === '1') or
                ($privacy_record["d_insurance_num"][2] === '1') or
                ($privacy_record["d_insurance_num"][3] === '1') ? '1' : '0');
            $license  = (($privacy_record["d_certificate_num"][1] === '1') or
                ($privacy_record["d_certificate_num"][2] === '1') or
                ($privacy_record["d_certificate_num"][3] === '1') ? '1' : '0');
            $deviceids  = (($privacy_record["d_device_num"][1] === '1') or
                ($privacy_record["d_device_num"][2] === '1') or
                ($privacy_record["d_device_num"][3] === '1') ? '1' : '0');
            $other  = (($privacy_record["d_other_phi"][1] === '1') or
                ($privacy_record["d_other_phi"][2] === '1') or
                ($privacy_record["d_other_phi"][3] === '1') ? '1' : '0');
            $photos  = (($privacy_record["d_identifying_image"][1] === '1') or
                ($privacy_record["d_identifying_image"][2] === '1') or
                ($privacy_record["d_identifying_image"][3] === '1') ? '1' : '0');
            $weburls  = (($privacy_record["d_urls"][1] === '1') or
                ($privacy_record["d_urls"][2] === '1') or
                ($privacy_record["d_urls"][3] === '1') ? '1' : '0');
            $labs       = (($privacy_record["d_lab_results"][1] === '1') or
                ($privacy_record["d_lab_results"][2] === '1') or
                ($privacy_record["d_lab_results"][3] === '1') ? '1' : '0');
            $billing    = (($privacy_record["d_diag_proc"][1] === '1') or
                ($privacy_record["d_diag_proc"][2] === '1') or
                ($privacy_record["d_diag_proc"][3] === '1') ? '1' : '0');
            $medications   = (($privacy_record["d_medications"][1] === '1') or
                ($privacy_record["d_medications"][2] === '1') or
                ($privacy_record["d_medications"][3] === '1') ? '1' : '0');
            $nonPhi     = (($privacy_record["d_demographics"][1] === '1') or
                ($privacy_record["d_demographics"][2] === '1') or
                ($privacy_record["d_demographics"][3] === '1') ? '1' : '0');
            $phi =  ((($full_name == '1') or ($ssn == '1') or ($phone == '1') or ($geography == '1') or ($dates == '1') or
                      ($fax == '1') or ($email == '1') or ($mrn == '1') or ($health == '1') or ($accounts == '1') or
                      ($license == '1') or ($deviceids == '1') or ($other == '1') or ($photos == '1') or ($weburls == '1')
                     ) ? '1' : '0');

            $privacy = array(
                "approved"          => $privacy_record["approved"],
                "lab_results"       => "$labs",
                "billing_codes"     => "$billing",
                "medications"       => "$medications",
                "demographic"       => array("nonphi"       => "$nonPhi",
                                             "phi"          => "$phi",
                                             "phi_approved" => array(
                                                    "fullname"      => "$full_name",
                                                    "ssn"           => "$ssn",
                                                    "phone"         => "$phone",
                                                    "geography"     => "$geography",
                                                    "dates"         => "$dates",
                                                    "fax"           => "$fax",
                                                    "email"         => "$email",
                                                    "mrn"           => "$mrn",
                                                    "insurance"     => "$health",
                                                    "accounts"      => "$accounts",
                                                    "license"       => "$license",
                                                    "deviceids"     => "$deviceids",
                                                    "biometric"     => "0",
                                                    "photos"        => "$photos",
                                                    "other"         => "$other",
                                                    "web_urls"      => "$weburls"
                                                    )
                                            )
            );

            return $privacy;
        } else {
            $this->emDebug("Privacy approval was not found in $privacy_pid - looking in project $old_privacy_pid.");
        }

        // Privacy approval was not found in newer project so look through the old project.
        $privacy_fields_old = array('approved', 'lab_results', 'billing_codes', 'clinical_records', 'demographic', 'phi');
        $privacy_filter = '[protocol] = "' . $irb_num . '" and [approved] = 1';
        $privacy_data = REDCap::getData($old_privacy_pid, 'array', null, $privacy_fields_old, $old_privacy_event_id, null, false, false, false, $privacy_filter);

        if (!is_null($privacy_data) and !empty($privacy_data)) {
            $privacy_record_id = array_keys($privacy_data)[0];
            $privacy_record = $privacy_data[$privacy_record_id][$old_privacy_event_id];
            $this->emDebug("Found privacy approval for IRB " . $irb_num . " in Privacy Project $old_privacy_pid in record " . $privacy_record_id);
            $privacy = array(
                "approved"          => $privacy_record["approved"],
                "lab_results"       => ($privacy_record["lab_results"]["1"] === "1" ? '1': '0'),
                "billing_codes"     => ($privacy_record["billing_codes"]["1"] === "1" ? '1': '0'),
                "medications"       => ($privacy_record["clinical_records"]["1"] === "1" ? '1': '0'),
                "demographic"       => array("nonphi"       => ($privacy_record["demographic"]["1"] === "1" ? '1': '0'),
                                             "phi"          => ($privacy_record["demographic"]["2"] === "1" ? '1': '0'),
                                             "phi_approved" => array(
                                                    "fullname"      => ($privacy_record["phi"]["1"] === "1" ? '1': '0'),
                                                    "ssn"           => ($privacy_record["phi"]["2"] === "1" ? '1': '0'),
                                                    "phone"         => ($privacy_record["phi"]["3"] === "1" ? '1': '0'),
                                                    "geography"     => ($privacy_record["phi"]["4"] === "1" ? '1': '0'),
                                                    "dates"         => ($privacy_record["phi"]["5"] === "1" ? '1': '0'),
                                                    "fax"           => ($privacy_record["phi"]["6"] === "1" ? '1': '0'),
                                                    "email"         => ($privacy_record["phi"]["7"] === "1" ? '1': '0'),
                                                    "mrn"           => ($privacy_record["phi"]["8"] === "1" ? '1': '0'),
                                                    "insurance"     => ($privacy_record["phi"]["9"] === "1" ? '1': '0'),
                                                    "accounts"      => ($privacy_record["phi"]["10"] === "1" ? '1': '0'),
                                                    "license"       => ($privacy_record["phi"]["11"] === "1" ? '1': '0'),
                                                    "deviceids"     => ($privacy_record["phi"]["13"] === "1" ? '1': '0'),
                                                    "biometric"     => ($privacy_record["phi"]["16"] === "1" ? '1': '0'),
                                                    "photos"        => ($privacy_record["phi"]["17"] === "1" ? '1': '0'),
                                                    "other"         => ($privacy_record["phi"]["18"] === "1" ? '1': '0'),
                                                    "web_urls"      => '0'
                                            )
                )
            );

            return $privacy;
        } else {
            $this->emError("Privacy approval was not found in $old_privacy_pid.");
            return false;
        }
    }
    
}
