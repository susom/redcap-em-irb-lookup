
# IRB Validity Check

The IRB EM is a helper class for accessing IRB and Data Security Compliance Status data.  

The IRB EM uses the Stanford compliance API to return IRB and Data privacy permissions and attestations as 
well as permissions to view and download PHI. 
To access the service, include these statements:
```php
$irb_or_dpa = '12345';
$pid = $this->getProjectId();
try {
    $Compliance = \ExternalModules\ExternalModules::getModuleInstance('irb_lookup');
    $irb_valid = $Compliance->isIRBValid($irb_or_dpa); // if DPA, will check for valid IRB
            
    $dpa_valid = $Compliance->isDpaValid($irb_or_dpa);  // if IRB, will check if it has a valid dpa  
        
    $personnel = $Compliance->getCompliancePersonnel($irb_or_dpa, $pid);
    // returns array of personnel structs with the following definition
    /*{"sunetid":string,
        "role":string,  // "role" indicates role on IRB submission
        "onIrb":boolean,
// signedDpa indicates approved DPA in redcap PID 9883,(retired) 4947 or PID 12935
        "signedDpa":boolean,
// phiDownloadPolicyExempt indicates exemption approved in redcap pid 28287 
        "phiDownloadPolicyExempt":boolean, 
        "hasOnlineChartReviewPermission":boolean,
        "hasPhiDownloadPermission":boolean,
        "isResearchProject":boolean}*/
    
     $user_permissions = $Compliance->getUserPermissions($irb_or_dpa, $sunet_id, $pid)
     // returns single personnel struct or false if user has no permissions
                        
    $irb_or_dpa_ids = $Compliance->getComplianceIdsBySunetID($sunet_id, $pid);
    // return array of strings of irb protocol nums or DPA ids for a sunet_id
    
    $privacy_data = $Compliance->getCompliancePrivacySettings($irb_or_dpa, $pid)
    // return privacy struct with the following definition
    /* {"status": boolean
    "message": string
    "privacy": {
          "dpa": "DPA-" . $dpa["recordId"],
          "projectType": string, 
          "valid": boolean, 
          "approved": boolean, // a DPA may be approved but invalid due to expiration date
          "lab_results": boolean,
          "billing_codes": boolean,
          "medications": boolean,
          "diagnosis": boolean, 
          "procedure": boolean, 
          "clinical_notes": boolean, 
          "psych_notes": boolean, 
          "radiology": boolean,
          "demographic": {"nonphi": boolean,
                        "phi": boolean,
                        "phi_approved": {
                            "fullname": boolean,
                            "ssn": boolean,
                            "phone": boolean,
                            "geography": boolean,
                            "dates": boolean,
                            "fax": boolean,
                            "email": boolean,
                            "mrn": boolean,
                            "insurance": boolean,
                            "accounts": boolean,
                            "license": boolean,
                            "vehicle": boolean,
                            "deviceids": boolean,
                            "biometric": boolean,
                            "photos": boolean,
                            "other": boolean,
                            "web_urls": boolean
        }
    }
    */
            
    $compliance_data = $Compliance->getAllCompliance($irb_or_dpa, $pid);
    // returns compliance struct with the following definition
    /* {
    "irbOrDpa":string,
    "projectTitle":string,
    "expireDate":"31-DEC-99",
    "isValid":boolean,
    "protocol":{
        "ptlNumber": number, // this is the irb number
        "detailsId": number,
        "ptlStatus":"APPROVED",
        "formStatus":"APPROVED",
        "pdId":string, // suid of protocol director
        "protocolDirector": string, // lastname, firstname of protocol director
        "pdDept": string, // name of department
        "protocolType":"CHART REVIEW",
        "formType":"REVISION",
        "panelId":7,
        "initialApprovalDate":"17-JUL-20",
        "approvalDate":"12-APR-23",
        "meetingDate":"30-APR-23",
        "expireDate":"31-DEC-99",
        "protocolTitle": string,
        "isValid":"true"
        },
    "dpa":null or { // this is different from struct returned from privacy settings
        "recordId": number,
        "protocolNumStr": string, "protocolNum": number, // the IRB #
        "dpaVersionNumber":3,
        "dpaExists":true,"isDpaValid":true,
        "primaryUser":"<suid of primary user>",
        "errorMessage":null,
        "dpaApproved":"APPROVED","dpaStatus":"COMPLETE",
        "dpaApprovalDate":"2020-06-22 13:10","dpaCreateDate":"2020-06-22 12:01:03",
        "dpaExpireDate":null,
        "projectTitle":"XXXX",
        "projectTypeCodedValue":"1","projectType":"Research (IRB)",
        "department":"<department name>","facultySponsor":"<sponsor name>",
        "approvedForAnyPhi":true,
        "approvedForDemoNonPhi":true,"approvedForDemoPhi":true,
        "approvedForDemographics":true,"approvedForDiagnosis":true,
        "approvedForLabResult":true,"approvedForMedications":true,
        "approvedForHospitalCost":false,"approvedForPsychNotes":false,
        "approvedForProcedure":true,"approvedForClinicalNotes":true,
        "approvedForRadiology":true,"approvedForOtherImages":false,
        "approvedForOtherNonPhi":true,"approvedForName":true,
        "approvedForStateOrLess":true,"approvedForDates":true,
        "approvedForPhoneNums":false,"approvedForFaxNums":false,
        "approvedForEmail":false, "approvedForSsn":false,
        "approvedForMrn":true,"approvedForHealthPlan":true,
        "approvedForCertNum":true,"approvedForVehicleNum":false,
        "approvedForDeviceNum":false,"approvedForUrls":false,
        "approvedForIps":false,"approvedForIdentifyingImage":false,
        "approvedForAcctNums":true,"approvedForOtherPhi":false,
    
        "intentForDiagnosis":"Internal Use","intentForProcedure":"Internal Use",
        "intentForLabResult":"Internal Use","intentForMedications":"Internal Use",
        "intentForHospitalCost":"","intentForDemographics":"Internal Use",
        "intentForPsychNotes":"","intentForClinicalNotes":"Internal Use",
        "intentForRadiology":"Internal Use","intentForOtherImages":"",
        "intentForOtherNonPhi":"Internal Use","intentForName":"Internal Use",
        "intentForStateOrLess":"Internal Use","intentForDates":"Internal Use",
        "intentForPhoneNums":"","intentForFaxNums":"","intentForEmail":"",
        "intentForSsn":"","intentForMrn":"Internal Use",
        "intentForHealthPlan":"Internal Use","intentForAcctNums":"Internal Use",
        "intentForCertNum":"Internal Use","intentForVehicleNum":"","intentForDeviceNum":"",
        "intentForUrls":"","intentForIps":"","intentForIdentifyingImage":"","intentForOtherPhi":""
     },
     "personnel": array of personnel struct,
    }*/
        
    $user_compliance_data = $Compliance->getComplianceAllBySunetID($sunet_id, $pid=null);
    // return array of all compliance settings associated with a user
    
} catch(Exception $ex) {
    $module->emError("Exception when creating class irb_lookup");
}
```

These IRB functions return IRB status using the deprecated IRB api and are included to maintain backwards 
compatibility. Please use compliance functions going forward.
```php
$irb_num = 12345;
try {
    $IRB = \ExternalModules\ExternalModules::getModuleInstance('irb_lookup');
    $valid = $IRB->isIRBValid($irb_num);
            or
    $personnel = $IRB->getIRBPersonnel($irb_num);
            or
    $irb_data = $IRB->getAllIRBData($irb_num);
            or
    $irb_numbers = $IRB->getIRBNumsBySunetID($sunet_id);
            or
    $irb_info = $IRB->getIRBAllBySunetID($sunet_id);
} catch(Exception $ex) {
    $module->emError("Exception when creating class irb_lookup");
}
```
# Setup
To use this EM, system settings must be filled in prior to use.  

For Compliance functions, only the compliance API 
endpoint is required.  

To use deprecated IRB functions, three IRB API endpoint URLs must be added. 
In addition, there are utility functions which will return the privacy attestation categories and the status of 
whether or not the IRB is approved for them.  When using the privacy attestation checks, 
the REDCap projects which store the privacy information must be defined. 

# Compliance Functions:
* **isIRBValid** - will return true or false; if input is a DPA, will check for valid IRB
* **isDpaValid** - will return true or false; if input is an IRB, will check for a valid DPA
* **getCompliancePersonnel** - will return an array of personnel associated with the IRB or DPA and their permissions
* **getUserPermissions** - will return user permissions for an IRB or DPA or false if user has no permissions
* **getAllCompliance** - will return all compliance data including status, irb, dpa, personnel, expiration date, etc.
* **getComplianceIdsBySunetID** - will return a list of IRBs or DPAs that this person is named on
* **getComplianceAllBySunetID** - will return a list of protocols and their status
* **getCompliancePrivacySettings** - returns the privacy categories and whether or not this project is approved for data in that category.

# IRB Functions (deprecated):
* getIRBPersonnel - will return a json encoded list of people named on the IRB
* getAllIRBData - will return all IRB data including status, personnel, expiration date, etc.
* getIRBNumsBySunetID - will return a list of IRB Numbers that this person is named on
* getIRBAllBySunetID - will return a list of protocols and their status

# Privacy Function (deprecated):
* getPrivacySettings (deprecated) - returns the privacy categories and whether or not this project is approved 
                           for data in that category.
    
# Dependencies
This EM depends on the EM vertx Token Manager and EM Logger.
