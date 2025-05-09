<?php

require __DIR__ . '/vendor/autoload.php';

echo \passthru('php ' . __DIR__ . '/box.phar compile');

@\mkdir(__DIR__ . '/build', recursive: true);
\copy(__DIR__ . '/vendor/boson-php/runtime/bin/libboson-windows-x86_64.dll',
    __DIR__ . '/build/libboson-windows-x86_64.dll');

$output = \fopen(__DIR__ . '/build/open-ray.exe', 'wb+');
$ini = <<<'INI'
    ffi.enable=1
    opcache.enable=1
    opcache.enable_cli=1
    opcache.jit_buffer_size=128M
    INI;

\stream_copy_to_stream(\fopen(__DIR__ . '/micro.sfx', 'rb'), $output);
\fwrite($output, "\xfd\xf6\x69\xe6");
\fwrite($output, \pack('N', \strlen($ini)));
\fwrite($output, $ini);
\fwrite($output, \file_get_contents(__DIR__ . '/app.phar'));
\fclose($output);

echo \passthru(__DIR__ . '/build/open-ray.exe');
