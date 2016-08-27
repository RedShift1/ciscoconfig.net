<?php
require_once '__autoload.php';

$Config = new Cisco\StandaloneAP("");
echo "<pre>";
print_r($Config->getOpts());

/*$Config->EnableSSH();
$Config->setOptVal('VLANs', '10,20');
$Config->Generate();
$Config->sortBlocks();
echo "<pre>";
print_r($Config->getOptsByGroup());
*/

// var_dump($Config->getNetAddrFromIP('192.168.1.0', 16));

?>
