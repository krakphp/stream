<?php

namespace Krak\Stream;

/** Simplifies the PHP filter interface by sending chunks to callbacks */
class ChunkStreamFilter extends \php_user_filter
{
    private $callback;
    private $consumed = 0;

    public function onCreate() {
        $this->callback = $this->params;
        return true;
    }

    public function filter($in, $out, &$consumed, $closing) {
        $chunk = '';
        $consumed = 0;

        while ($bucket = stream_bucket_make_writeable($in)) {
            $chunk .= $bucket->data;
            $this->consumed += strlen($chunk);
        }

        $cb = $this->callback;

        $res = null;
        if (strlen($chunk) > 0 || !$closing) {
            $res = $cb($chunk);
        }

        if ($closing) {
            $res = (string) $res . (string) $cb(null);
        }

        if (is_null($res)) {
            return PSFS_FEED_ME;
        } else {
            $consumed = $this->consumed;
            $this->consumed = 0;
            $bucket = stream_bucket_new($this->stream, $res);
            stream_bucket_append($out, $bucket);
            return PSFS_PASS_ON;
        }
    }
}
