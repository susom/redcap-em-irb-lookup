
# IRB Validity Check

The IRB EM is a helper class for accessing IRB Status data.

There are currently 5 IRB functions which will return IRB status data. To
access the service, include these statements:
```php
$irb_num = 12345;
$IRB = \ExternalModules\ExternalModules::getModuleInstance('irb');
$valid = $IRB->isIRBValid($irb_num);
    or
$personnel = $IRB->getIRBPersonnel($irb_num);
    or
$irb_data = $IRB->getAllIRBData($irb_num);
    or
$irb_numbers = $IRB->getIRBNumsBySunetID($sunet_id)
    or
$irb_info = $IRB->getIRBAllBySunetID($sunet_id)
```

# Functions:
    * isIRBValid - will return true or false
    * getIRBPersonnel - will return a json encoded list of people named on the IRB
    * getAllIRBData - will return all IRB data including status, personnel, expiration date, etc.
    * getIRBNumsBySunetID - will return a list of IRB Numbers that this person is named on
    * getIRBAllBySunetID - will return a list of protocols and their status

# Dependencies
This EM depends on vertx Token Manager and EM Logger.

```$xslt


```