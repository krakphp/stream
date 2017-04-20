<?php

use Krak\Stream;

describe('Krak Stream', function() {
    describe('#fromStr', function() {
        it('creates a stream from a string', function() {
            $stream = Stream\fromStr('abc');
            assert(stream_get_contents($stream) == 'abc');
        });
    });
    describe('#fromPath', function() {
        it('creates a stream from a path', function() {
            $stream = Stream\fromPath('php://memory', 'rw');
            fwrite($stream, "abc");
            rewind($stream);
            assert(stream_get_contents($stream) == 'abc');
        });
    });
    describe('#toStr', function() {
        it('copies a stream into a string', function() {
            assert(Stream\toStr(Stream\fromStr('abc')) == 'abc');
        });
    });
    describe('#appendFilter', function() {
        it('appends a filter onto a stream', function() {
            $stream = Stream\fromStr('abc');
            Stream\appendFilter($stream, Stream\createFilter('string.toupper'));
            Stream\appendFilter($stream, Stream\createFilter('string.tolower'));
            assert(Stream\toStr($stream) == 'abc');
        });
    });
    describe('#prependFilter', function() {
        it('prepends a filter onto a stream', function() {
            $stream = Stream\fromStr('abc');
            Stream\prependFilter($stream, Stream\createFilter('string.toupper'));
            Stream\prependFilter($stream, Stream\createFilter('string.tolower'));
            assert(Stream\toStr($stream) == 'ABC');
        });
    });

    $test_stream = function($stream_name, $description, $stream, $input, $output) {
        describe('#' . $stream_name, function() use ($input, $stream, $output, $description) {
            it($description, function() use ($input, $stream, $output) {
                $src = Stream\fromStr($input);
                Stream\appendFilter($src, $stream);
                $data = Stream\toStr($src);
                assert($data == $output);
            });
        });
    };

    $test_stream('uppercase', 'uppercases the stream', Stream\uppercase(), 'abc', 'ABC');
    $test_stream('lowercase', 'lowercases the stream', Stream\lowercase(), 'ABC', 'abc');
    $test_stream('base64Encode', 'base64 encodes the binary stream', Stream\base64Encode(), 'abc', 'YWJj');
    $test_stream('base64Decode', 'base64 decodes the binary stream', Stream\base64Decode(), 'YWJj', 'abc');
    $test_stream('hex', 'hex encodes the binary stream', Stream\hex(), 'abc', '616263');
    $test_stream('unhex', 'hex decodes the binary stream', Stream\unhex(), '616263', 'abc');
    $test_stream('unhex', 'hex decodes the binary stream', Stream\unhex(), '616263', 'abc');
    $test_stream('chunk', 'chunks the input into specific sizes to be filtered', Stream\chunk(2, function($chunk) {
        return strlen($chunk);
    }), 'abc', '21');

    describe('#pipe', function() {
        it('pipes data from src through filters into dst', function() {
            $src = Stream\fromStr('abc');
            $dst = fopen("php://temp", "rw");
            $dst1 = fopen("php://temp", "rw");

            Stream\pipe($src, [Stream\uppercase()], $dst);
            rewind($src);
            Stream\pipe($src, [], $dst1);
            assert(Stream\toStr($dst) == 'ABC' && Stream\toStr($dst1) == 'abc');
        });
    });

    describe('#prependChunkHeader', function() {
        it('prepends a long as a header for the chunk size given', function() {
            $src = Stream\fromStr(pack('CCC', 0, 1, 2));
            $dst = fopen("php://temp", "rw");

            Stream\pipe($src, [
                Stream\chunk(2, Stream\prependChunkHeader(Stream\identity()))
            ], $dst);

            $encoded = Stream\toStr($dst);
            assert($encoded === pack('VCCVC', 2, 0, 1, 1, 2));
        });
    });
    describe('#chunkFromHeader', function() {
        it('chunks the data according to headers from small stream', function() {
            $chunked_data = pack('Va*Va*Va*Va*Va*', 1, 'a', 2, 'bb', 3, 'ccc', 4, 'dddd', 4, 'eeee');
            $src = Stream\fromStr($chunked_data);
            $dst = Stream\fromStr('');

            Stream\pipe($src, [
                Stream\chunkFromHeader(Stream\identity())
            ], $dst);

            assert(Stream\toStr($dst) === 'abbcccddddeeee');
        });
        // php by default reads 8192 bytes from a stream, this tests the chunkFromHeader
        // algorithm to see if it handles getting data chunked at weird places
        it('chunks the data according to headers from large stream', function() {
            for ($i = 0; $i < 12; $i++) {
                $chunked_data = pack(
                    'Va*Va*',
                    8180 + $i, str_repeat('a', 8180 + $i),
                    16, str_repeat('b', 16)
                );
                $src = Stream\fromStr($chunked_data);
                Stream\appendFilter($src, Stream\chunkFromHeader(Stream\identity()));

                $res = '';
                while (!feof($src)) {
                    $res .= fread($src, 4);
                }

                assert($res === str_repeat('a', 8180 + $i) . str_repeat('b', 16));
            }
        });
    });
});
