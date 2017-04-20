<?php

require_once __DIR__ . '/vendor/autoload.php';

use Krak\Stream;
use Krak\Crypto;

$key = random_bytes(16);
$crypt = new Crypto\OpenSSLCrypt($key);

$src = Stream\fromStr('abc def ghi jkl');
$dst = Stream\toOutput();
Stream\pipe($src, [
    Stream\uppercase(), // utilizes string.toupper filter
    Stream\chunkFilter(function($chunk) {
        return str_replace('ABC', 'XYZ', $chunk);
    }),
    Stream\chunkFilter('str_rot13'), // performs rot13 on stream
    Stream\hex(), // performs bin2hex
    Stream\encrypt($crypt),
    Stream\base64Encode(),

    // below are the inverse functions of above which will undo the transformations

    Stream\base64Decode(),
    Stream\decrypt($crypt),
    Stream\unhex(),
    Stream\createFilter('string.rot13'), // creates a filter from a registered php filter
    Stream\chunkFilter(function($chunk) {
        return str_replace('XYZ', 'ABC', $chunk);
    }),
    Stream\lowercase(),
], $dst);

echo "\n";
