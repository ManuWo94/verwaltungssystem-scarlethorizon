<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Fange alle Fehler ab
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo "PHP Error [$errno]: $errstr in $errfile on line $errline<br>\n";
    return false;
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo "Fatal Error: {$error['message']} in {$error['file']} on line {$error['line']}<br>\n";
    }
});

echo "Starting civil_cases.php test...<br>\n";

// Inkludiere die Datei
try {
    ob_start();
    include 'modules/civil_cases.php';
    $output = ob_get_clean();
    
    echo "SUCCESS! civil_cases.php loaded without errors.<br>\n";
    echo "Output length: " . strlen($output) . " bytes<br>\n";
    
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "<br>\n";
    echo "File: " . $e->getFile() . "<br>\n";
    echo "Line: " . $e->getLine() . "<br>\n";
    echo "Trace:<pre>" . $e->getTraceAsString() . "</pre>\n";
} catch (Error $e) {
    echo "ERROR: " . $e->getMessage() . "<br>\n";
    echo "File: " . $e->getFile() . "<br>\n";
    echo "Line: " . $e->getLine() . "<br>\n";
    echo "Trace:<pre>" . $e->getTraceAsString() . "</pre>\n";
}
