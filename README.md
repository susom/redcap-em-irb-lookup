
#IRB Validity Check

The IRB EM is a helper class for accessing IRB Status data.

There are currently 3 IRB functions which will return IRB status data. To
access the service, include these statements:
```php
$irb_num = 12345;
$IRB = \ExternalModules\ExternalModules::getModuleInstance('irb');
$valid = $IRB->isIRBValid($irb_num);
    or
$personnel = $IRB->getIRBPersonnel($irb_num);
    or
$irb_data = $IRB->getAllIRBData($irb_num);
```

# Functions:
    * isIRBValid - will return true or false
    * getIRBPersonnel - will return a json encoded list of people named on the IRB
    * getAllIRBData - will return all IRB data including status, personnel, expiration date, etc.

#Dependencies
This EM uses the vertx_token_manager and em_logger EMs.

```$xslt


```