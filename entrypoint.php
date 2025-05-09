<?php

use Serafim\OpenRay\Application;

Phar::mapPhar('app.phar');
Phar::mount('libboson-windows-x86_64.dll', __DIR__ . '/libboson-windows-x86_64.dll');

require_once 'phar://app.phar/vendor/autoload.php';

if (\PHP_OS_FAMILY === 'Windows') {
    $ffi = \FFI::cdef('bool FreeConsole(void);', 'kernel32.dll');
    $ffi->FreeConsole();
}

$app = new Application('libboson-windows-x86_64.dll');

__HALT_COMPILER();
