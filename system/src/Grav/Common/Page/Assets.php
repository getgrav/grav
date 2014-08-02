<?php
namespace Grav\Common\Page;

use Grav\Common\Getters;
use Grav\Common\Registry;
use Grav\Config;
use Symfony\Component\Yaml\Yaml;

/**
 * Assets is a holder object that contains references to the assets of page. This object is created and
 * populated during the getAssets() method in the Pages object
 *
 * @author RocketTheme
 * @license MIT
 */
class Assets extends Getters
{
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

            // Get asset instance creating it if it didn't exist.
            $asset = $this->get("{$basename}.{$ext}", true);
            if (!$asset) {
                continue;
            }

            // Assign meta files to the asset.
            if ($meta) {
                $asset->addMetaFile($meta);
            }
        }
    }

    /**
     * Get asset by basename and extension.
     *
     * @param string $filename
     * @param bool   $create
     * @return Asset|null
     */
    public function get($filename, $create = false)
    {
        if ($create && !isset($this->instances[$filename])) {
            $parts = explode('.', $filename);
            $ext = array_pop($parts);
            $basename = implode('.', $parts);

            /** @var Config $config */
            $config = Registry::get('Config');

            // Check if asset type has been configured.
            $params = $config->get("assets.{$ext}");
            if (!$params) {
                return null;
            }

            $filePath = $this->path . '/' . $filename;
            $params += array(
                'type' => 'file',
                'thumb' => 'assets/thumb.png',
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

            $this->add(new Asset($params));
        }

        return isset($this->instances[$filename]) ? $this->instances[$filename] : null;
    }

    /**
     * Get a list of all assets.
     *
     * @return array|Asset[]
     */
    public function all()
    {
        return $this->instances;
    }

    /**
     * Get a list of all image assets.
     *
     * @return array|Asset[]
     */
    public function images()
    {
        return $this->images;
    }

    /**
     * Get a list of all video assets.
     *
     * @return array|Asset[]
     */
    public function videos()
    {
        return $this->videos;
    }

    /**
     * Get a list of all file assets.
     *
     * @return array|Asset[]
     */
    public function files()
    {
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
