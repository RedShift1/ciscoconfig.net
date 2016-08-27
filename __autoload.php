<?php
function __autoload($Name) {
    $File = 'lib/' . str_replace('\\', DIRECTORY_SEPARATOR, $Name) . '.php';
    include_once($File);
}

spl_autoload_register(__NAMESPACE__ . '\__autoload');
?>