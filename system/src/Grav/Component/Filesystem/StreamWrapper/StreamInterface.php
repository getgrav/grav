<?php
namespace Grav\Component\Filesystem\StreamWrapper;

/**
 * Generic PHP stream wrapper interface.
 *
 * @see http://www.php.net/manual/class.streamwrapper.php
 */
interface StreamInterface
{
    /**
     * Support for fopen(), file_get_contents(), file_put_contents() etc.
     *
     * @param string $uri        A string containing the URI to the file to open.
     * @param string $mode       The file mode ("r", "wb" etc.).
     * @param int    $options    A bit mask of STREAM_USE_PATH and STREAM_REPORT_ERRORS.
     * @param string $opened_url A string containing the path actually opened.
     *
     * @return bool Returns TRUE if file was opened successfully.
     * @see http://php.net/manual/streamwrapper.stream-open.php
     */
    public function stream_open($uri, $mode, $options, &$opened_url);

    /**
     * Support for fclose().
     *
     * @return bool TRUE if stream was successfully closed.
     * @see http://php.net/manual/streamwrapper.stream-close.php
     */
    public function stream_close();

    /**
     * Support for flock().
     *
     * @param $operation
     *     One of the following:
     *     - LOCK_SH to acquire a shared lock (reader).
     *     - LOCK_EX to acquire an exclusive lock (writer).
     *     - LOCK_UN to release a lock (shared or exclusive).
     *     - LOCK_NB if you don't want flock() to block while locking (not
     *     supported on Windows).
     *
     * @return bool Always returns TRUE at the present time.
     * @see http://php.net/manual/streamwrapper.stream-lock.php
     */
    public function stream_lock($operation);

    /**
     * Support for touch(), chmod(), chown(), chgrp().
     *
     * @param $path
     *     The file path or URL to set metadata. Note that in the case of a URL, it must be a :// delimited URL.
     *     Other URL forms are not supported.
     *
     * @param $option
     *     One of:
     *     - STREAM_META_TOUCH      The method was called in response to touch()
     *     - STREAM_META_OWNER_NAME The method was called in response to chown() with string parameter
     *     - STREAM_META_OWNER      The method was called in response to chown()
     *     - STREAM_META_GROUP_NAME The method was called in response to chgrp()
     *     - STREAM_META_GROUP      The method was called in response to chgrp()
     *     - STREAM_META_ACCESS     The method was called in response to chmod()
     *
     * @param $value
     *     If option is
     *     - STREAM_META_TOUCH:         Array consisting of two arguments of the touch() function.
     *     - STREAM_META_OWNER_NAME or
     *       STREAM_META_GROUP_NAME:    The name of the owner user/group as string.
     *     - STREAM_META_OWNER or
     *       STREAM_META_GROUP:         The value owner user/group argument as integer.
     *     - STREAM_META_ACCESS:        The argument of the chmod() as integer.


     *
     * @return bool
     * @see http://php.net/manual/en/streamwrapper.stream-metadata.php
     */
    public function stream_metadata($path, $option, $value);

    /**
     * Support for fread(), file_get_contents() etc.
     *
     * @param $count
     *   Maximum number of bytes to be read.
     *
     * @return string|bool The string that was read, or FALSE in case of an error.
     * @see http://php.net/manual/streamwrapper.stream-read.php
     */
    public function stream_read($count);

    /**
     * Support for fwrite(), file_put_contents() etc.
     *
     * @param $data
     *   The string to be written.
     *
     * @return int The number of bytes written (integer).
     * @see http://php.net/manual/streamwrapper.stream-write.php
     */
    public function stream_write($data);

    /**
     * Support for feof().
     *
     * @return bool TRUE if end-of-file has been reached.
     * @see http://php.net/manual/streamwrapper.stream-eof.php
     */
    public function stream_eof();

    /**
     * Support for fseek().
     *
     * @param $offset
     *   The byte offset to got to.
     * @param $whence
     *   SEEK_SET, SEEK_CUR, or SEEK_END.
     *
     * @return bool TRUE on success.
     * @see http://php.net/manual/streamwrapper.stream-seek.php
     */
    public function stream_seek($offset, $whence);

    /**
     * Support for fflush().
     *
     * @return bool TRUE if data was successfully stored (or there was no data to store).
     * @see http://php.net/manual/streamwrapper.stream-flush.php
     */
    public function stream_flush();

    /**
     * Support for ftell().
     *
     * @return int The current offset in bytes from the beginning of file.
     * @see http://php.net/manual/streamwrapper.stream-tell.php
     */
    public function stream_tell();

    /**
     * Support for fstat().
     *
     * @return array An array with file status, or FALSE in case of an error - see fstat()
     * @see http://php.net/manual/streamwrapper.stream-stat.php
     */
    public function stream_stat();

    /**
     * Support for unlink().
     *
     * @param $uri
     *   A string containing the URI to the resource to delete.
     *
     * @return
     *   TRUE if resource was successfully deleted.
     * @see http://php.net/manual/streamwrapper.unlink.php
     */
    public function unlink($uri);

    /**
     * Support for rename().
     *
     * @param $from_uri ,
     *                  The URI to the file to rename.
     * @param $to_uri
     *                  The new URI for file.
     *
     * @return bool TRUE if file was successfully renamed.
     * @see http://php.net/manual/streamwrapper.rename.php
     */
    public function rename($from_uri, $to_uri);

    /**
     * Support for mkdir().
     *
     * @param $uri
     *   A string containing the URI to the directory to create.
     * @param $mode
     *   Permission flags - see mkdir().
     * @param $options
     *   A bit mask of STREAM_REPORT_ERRORS and STREAM_MKDIR_RECURSIVE.
     *
     * @return bool TRUE if directory was successfully created.
     * @see http://php.net/manual/streamwrapper.mkdir.php
     */
    public function mkdir($uri, $mode, $options);

    /**
     * Support for rmdir().
     *
     * @param $uri
     *   A string containing the URI to the directory to delete.
     * @param $options
     *   A bit mask of STREAM_REPORT_ERRORS.
     *
     * @return
     *   TRUE if directory was successfully removed.
     *
     * @see http://php.net/manual/streamwrapper.rmdir.php
     */
    public function rmdir($uri, $options);

    /**
     * Support for stat().
     *
     * @param $uri
     *   A string containing the URI to get information about.
     * @param $flags
     *   A bit mask of STREAM_URL_STAT_LINK and STREAM_URL_STAT_QUIET.
     *
     * @return array An array with file status, or FALSE in case of an error - see fstat()
     * @see http://php.net/manual/streamwrapper.url-stat.php
     */
    public function url_stat($uri, $flags);

    /**
     * Support for opendir().
     *
     * @param $uri
     *   A string containing the URI to the directory to open.
     * @param $options
     *   Unknown (parameter is not documented in PHP Manual).
     *
     * @return bool TRUE on success.
     * @see http://php.net/manual/streamwrapper.dir-opendir.php
     */
    public function dir_opendir($uri, $options);

    /**
     * Support for readdir().
     *
     * @return string The next filename, or FALSE if there are no more files in the directory.
     * @see http://php.net/manual/streamwrapper.dir-readdir.php
     */
    public function dir_readdir();

    /**
     * Support for rewinddir().
     *
     * @return bool TRUE on success.
     * @see http://php.net/manual/streamwrapper.dir-rewinddir.php
     */
    public function dir_rewinddir();

    /**
     * Support for closedir().
     *
     * @return bool TRUE on success.
     * @see http://php.net/manual/streamwrapper.dir-closedir.php
     */
    public function dir_closedir();
}
