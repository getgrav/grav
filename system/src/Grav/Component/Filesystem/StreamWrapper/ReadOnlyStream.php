<?php
namespace Grav\Component\Filesystem\StreamWrapper;

use Grav\Component\Filesystem\ResourceLocator;

class ReadOnlyStream extends Stream implements StreamInterface
{
    /**
     * @var ResourceLocator
     */
    protected static $locator;

    public function stream_open($uri, $mode, $options, &$opened_url)
    {
        if (!in_array($mode, ['r', 'rb', 'rt'])) {
            if ($options & STREAM_REPORT_ERRORS) {
                trigger_error('stream_open() write modes not supported for read-only stream wrappers', E_USER_WARNING);
            }
            return false;
        }

        $path = $this->getPath($uri);

        if (!$path) {
            return false;
        }

        $this->handle = ($options & STREAM_REPORT_ERRORS) ? fopen($path, $mode) : @fopen($path, $mode);

        return (bool) $this->handle;
    }

    public function stream_lock($operation)
    {
        // Disallow exclusive lock or non-blocking lock requests
        if (!in_array($operation, [LOCK_SH, LOCK_UN, LOCK_SH | LOCK_NB])) {
            trigger_error(
                'stream_lock() exclusive lock operations not supported for read-only stream wrappers',
                E_USER_WARNING
            );
            return false;
        }

        return flock($this->handle, $operation);
    }

    public function stream_write($data)
    {
        throw new \BadMethodCallException('stream_write() not supported for read-only stream wrappers');
    }

    public function unlink($uri)
    {
        throw new \BadMethodCallException('unlink() not supported for read-only stream wrappers');
    }

    public function rename($from_uri, $to_uri)
    {
        throw new \BadMethodCallException('rename() not supported for read-only stream wrappers');
    }

    public function mkdir($uri, $mode, $options)
    {
        throw new \BadMethodCallException('mkdir() not supported for read-only stream wrappers');
    }

    public function rmdir($uri, $options)
    {
        throw new \BadMethodCallException('rmdir() not supported for read-only stream wrappers');
    }
}
