# Stream

This library provides a simple abstraction over php streams and filters enabling developers to manipulate streams with extreme ease.

## Installation

Install with composer at `krak/stream`

## Usage

This example shows kind of a kitchen sink example of what filters are available and how to utilize them.

```php
<?php

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
```

The output of this would simply be: `abc def ghi jkl`.
