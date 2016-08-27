<?php
require_once('__autoload.php');

header("Content-Type: application/json; charset=UTF-8");
// TODO: Limit class selection from $_GET

function getTemplates()
{
    return array(
        'BasicRouter' => 'Basic Cisco router, 2 interfaces',
        'TelenetCFN' => 'Router with switch EHWIC for Telenet CFN',
        'StackableSwitch' => 'Stackable layer 2 switch',
        'DMVPN' => 'DMVPN',
        'StandaloneAP' => 'Standalone access point'
    );
}

function getTemplateOptions($templateName)
{
    $ClassName = "Cisco\\{$templateName}";
    $C = new $ClassName;
    return $C->GetOpts();
}

function getTemplateOptionsByGroup($templateName)
{
    $ClassName = "Cisco\\{$templateName}";
    $C = new $ClassName;
    return $C->GetOptsByGroup();
}

function generate($templateName, $Opts = array())
{
    $ClassName = "Cisco\\{$templateName}";
    $C = new $ClassName;
    $Options = array();
    foreach($Opts as $Entry) {
        if(preg_match("#^(.*)\[(.*)\]\[\]$#", $Entry['name'], $Matches)) {
            $Options[$Matches[1]][$Matches[2]][] = $Entry['value'];
        } else {
            $Options[$Entry['name']] = $Entry['value'];
        }
    }
    
    foreach ($C->GetOpts() as $Name => $Spec) {
        if(!isset($Options[$Name])) {
            continue;
        }

        if($Spec->getType() == 'bool') {
            if($Options[$Name] == 'true') {
                $C->setOptVal($Name, true);
            } else {
                $C->setOptVal($Name, false);
            }
        } else {
            $C->setOptVal($Name, $Options[$Name]);
        }
    }
    $C->Generate();
    $C->sortBlocks();
    return htmlentities($C->getConfig());
}

if(isset($_GET['template'])) {
    if(!array_key_exists($_GET['template'], getTemplates())){
        $Response['success'] = false;
        $Response['errortype'] = 'generic';
        $Response['errormsg'] = 'Invalid template';
        echo json_encode($Response);
        exit;
    }
}

switch ($_GET['f']) {
    case "getTemplates":
        $Return = getTemplates();
        break;
    case "getTemplateOptions":
        $Return = getTemplateOptions($_GET['template']);
        break;
    case "getTemplateOptionsByGroup":
        $Return = getTemplateOptionsByGroup($_GET['template']);
        break;
    case "generate":
        $Return = generate($_GET['template'], $_GET['options']);
        break;
}

$Response['success'] = true;
$Response['data'] = $Return;


echo json_encode($Response);
