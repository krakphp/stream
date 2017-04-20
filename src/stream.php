<?php

namespace Krak\Stream;

stream_filter_register('krak.chunk', ChunkStreamFilter::class);

/** Stream Factories

    The following functions create stream resources.

    from* methods create read streams
    to* methods create write streams
 **/

function fromStr($data, $mode = "rw") {
    $stream = fopen("php://temp", $mode);
    fwrite($stream, $data);
    rewind($stream);
    return $stream;
}

function fromPath($path, $mode = "r") {
    return fopen($path, $mode);
}

function fromStdin($mode = "r") {
    return fopen("php://stdin", $mode);
}

function toPath($path, $mode = "w") {
    return fopen($path, $mode);
}

function toOutput($mode = "w") {
    return fopen("php://output", $mode);
}

function toStdout($mode = "w") {
    return fopen("php://stdout", $mode);
}

/** Stream Utilities

    Useful functions for manipulating streams and filters
**/

function toStr($data) {
    return stream_get_contents($data);
}

/** Appends the set of filters to source, copies data to dst, then removes filters */
function pipe($src, array $filters, $dst) {
    $filter_resources = array_map(function($filter) use ($src) {
        return appendFilter($src, $filter);
    }, $filters);

    stream_copy_to_stream($src, $dst);

    $filter_resources = array_reverse($filter_resources);
    array_map('stream_filter_remove', $filter_resources);
}

function appendFilter($stream, $filter) {
    if ($filter[2] === null)  {
        array_pop($filter);
    }

    return stream_filter_append($stream, ...$filter);
}

function prependFilter($stream, $filter) {
    if ($filter[2] === null)  {
        array_pop($filter);
    }

    return stream_filter_prepend($stream, ...$filter);
}

function removeFilter($filter) {
    return stream_filter_remove($filter);
}

function createFilter($name, $params = null, $read_write = STREAM_FILTER_READ) {
    return [$name, $read_write, $params];
}

/** Stream Filters **/

function chunkFilter(callable $handler) {
    return createFilter('krak.chunk', $handler);
}

function uppercase() {
    return createFilter('string.toupper');
}

function lowercase() {
    return createFilter('string.tolower');
}

function base64Encode() {
    return createFilter('convert.base64-encode');
}

function base64Decode() {
    return createFilter('convert.base64-decode');
}

function hex() {
    return chunkFilter('bin2hex');
}

function unhex() {
    return chunkFilter('hex2bin');
}

function chunk($size, callable $handler) {
    $buffer = '';
    return chunkFilter(function($chunk) use ($size, $handler, &$buffer) {
        if (is_null($chunk)) {
            return $handler($buffer);
        }

        $buffer .= $chunk;
        if (strlen($buffer) < $size) {
            return;
        }

        $res = '';
        while (strlen($buffer) >= $size) {
            $res .= $handler(substr($buffer, 0, $size));
            $buffer = substr($buffer, $size);
        }

        return $res;
    });
}

function chunkFromHeader(callable $handler) {
    $buffer = '';
    $size = null;
    return chunkFilter(function($chunk) use ($handler, &$buffer, &$size) {
        if (is_null($chunk)) {
            if ($buffer) {
                throw new \RuntimeException("Invalid headers were found in the chunked data.");
            }
            return;
        }

        $buffer .= $chunk;
        if ($size === null) {
            list($size, $buffer) = _unpackHeaderFromChunk($buffer);
        }

        $res = '';
        while ($size !== null && strlen($buffer) >= $size) {
            $res .= $handler(substr($buffer, 0, $size));
            $buffer = substr($buffer, $size);
            list($size, $buffer) = _unpackHeaderFromChunk($buffer);
        }

        return $res;
    });
}

function encrypt(\Krak\Crypto\Crypt $crypt, $chunk_size = 8192) {
    return chunk($chunk_size, prependChunkHeader(function($chunk) use ($crypt) {
        return $crypt->encrypt($chunk);
    }));
}

function decrypt(\Krak\Crypto\Crypt $crypt) {
    return chunkFromHeader(function($chunk) use ($crypt) {
        return $crypt->decrypt($chunk);
    });
}


/** Stream Filter Utilities **/

function prependChunkHeader(callable $handler) {
    return function($chunk) use ($handler) {
        $res = $handler($chunk);
        return is_null($res) ? $res : pack('V', strlen($res)) . $res;
    };
}

function identity() {
    return function($chunk) {
        return $chunk;
    };
}

function _unpackHeaderFromChunk($chunk) {
    if (strlen($chunk) < 4) {
        return [null, $chunk];
    }
    $data = unpack('Vsize', $chunk);
    $size = $data['size'];
    return [$size, substr($chunk, 4)];
}
