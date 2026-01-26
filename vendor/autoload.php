<?php
// Minimal PSR-4 autoloader for bKash\PGW namespace
spl_autoload_register(function ($class) {
    $prefix = 'bKash\\PGW\\';
    $base_dir = __DIR__ . '/../includes/classes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});
