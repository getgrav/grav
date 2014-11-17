<?php
namespace Grav\Common\Page;

use Grav\Common\Getters;
use Grav\Common\Grav;
use Grav\Common\Config\Config;
use Grav\Common\GravTrait;

/**
 * Media is a holder object that contains references to the media of page. This object is created and
 * populated during the getMedia() method in the Pages object
 *
 * @author RocketTheme
 * @license MIT
 */
class Media extends Getters
{
    use GravTrait;

    protected $gettersVariable = 'instances';
    protected $path;

    protected $instances = array();
    protected $images = array();
    protected $videos = array();
    protected $files = array();

    /**
     * @param $path
     */
    public function __construct($path)
    {
        // Handle special cases where page doesn't exist in filesystem.
        if (!is_dir($path)) {
            return;
        }

        $this->path = $path;

        $iterator = new \DirectoryIterator($path);

        /** @var \DirectoryIterator $info */
        foreach ($iterator as $info) {
            // Ignore folders and Markdown files.
            if ($info->isDot() || !$info->isFile() || $info->getExtension() == 'md') {
                continue;
            }

            // Find out the real filename, in case of we are at the metadata.
            $filename = $info->getFilename();
            list($basename, $ext, $meta) = $this->getFileParts($filename);

            // Get medium instance creating it if it didn't exist.
            $medium = $this->get("{$basename}.{$ext}", true);
            if (!$medium) {
                continue;
            }

            //set file size
            $medium->set('size', $info->getSize());

            // Assign meta files to the medium.
            if ($meta) {
                $medium->addMetaFile($meta);
            }
        }
    }

    /**
     * Get medium by basename and extension.
     *
     * @param string $filename
     * @param bool   $create
     * @return Medium|null
     */
    public function get($filename, $create = false)
    {
        if ($create && !isset($this->instances[$filename])) {
            $parts = explode('.', $filename);
            $ext = array_pop($parts);
            $basename = implode('.', $parts);

            /** @var Config $config */
            $config = self::$grav['config'];

            // Check if medium type has been configured.
            $params = $config->get("media.".strtolower($ext));
            if (!$params) {
                return null;
            }

            $filePath = $this->path . '/' . $filename;

            // Add default settings for undefined variables.
            $params += $config->get('media.defaults');
            $params += array(
                'type' => 'file',
                'thumb' => 'media/thumb.png',
                'mime' => 'application/octet-stream',
                'name' => $filename,
                'filename' => $filename,
                'basename' => $basename,
                'extension' => $ext,
                'path' => $this->path,
                'modified' => filemtime($filePath),
            );

            $lookup = array(
                USER_DIR . 'images/',
                SYSTEM_DIR . 'images/',
            );
            foreach ($lookup as $path) {
                if (is_file($path . $params['thumb'])) {
                    $params['thumb'] = $path . $params['thumb'];
                    break;
                }
            }

            $this->add(new Medium($params));
        }

        return isset($this->instances[$filename]) ? $this->instances[$filename] : null;
    }

    /**
     * Get a list of all media.
     *
     * @return array|Medium[]
     */
    public function all()
    {
        ksort($this->instances, SORT_NATURAL | SORT_FLAG_CASE);
        return $this->instances;
    }

    /**
     * Get a list of all image media.
     *
     * @return array|Medium[]
     */
    public function images()
    {
        ksort($this->images, SORT_NATURAL | SORT_FLAG_CASE);
        return $this->images;
    }

    /**
     * Get a list of all video media.
     *
     * @return array|Medium[]
     */
    public function videos()
    {
        ksort($this->videos, SORT_NATURAL | SORT_FLAG_CASE);
        return $this->videos;
    }

    /**
     * Get a list of all file media.
     *
     * @return array|Medium[]
     */
    public function files()
    {
        ksort($this->files, SORT_NATURAL | SORT_FLAG_CASE);
        return $this->files;
    }

    /**
     * @internal
     */
    protected function add($file)
    {
        $this->instances[$file->filename] = $file;
        switch ($file->type) {
            case 'image':
                $this->images[$file->filename] = $file;
                break;
            case 'video':
                $this->videos[$file->filename] = $file;
                break;
            default:
                $this->files[$file->filename] = $file;
        }
    }

    /**
     * Get filename, extension and meta part.
     *
     * @param  string $filename
     * @return array
     */
    protected function getFileParts($filename)
    {
        $fileParts = explode('.', $filename);

        $name = array_shift($fileParts);
        $extension = null;
        while (($part = array_shift($fileParts)) !== null) {
            if ($part != 'meta') {
                if (isset($extension)) {
                    $name .= '.' . $extension;
                }
                $extension = $part;
            } else {
                break;
            }
        }
        $meta = implode('.', $fileParts);

        return array($name, $extension, $meta);
    }
}
