<?php
namespace Grav\Common\Page;

use Grav\Common\Getters;
use Grav\Common\Grav;
use Grav\Common\Config\Config;
use Grav\Common\GravTrait;
use Grav\Common\Page\Medium\Medium;

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
    protected $audios = array();
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
            list($basename, $ext, $type, $extra) = $this->getFileParts($filename);

            // Get medium instance if it already exists.
            $medium = $this->get("{$basename}.{$ext}");

            if ($type !== 'alternative') {
                
                $medium = $medium ? $medium : $this->createMedium("{$path}/{$basename}.{$ext}");

                if (!$medium) {
                    continue;
                }

                switch ($type) {
                    case 'base':
                        $medium->set('size', $info->getSize());
                        break;
                    case 'meta':
                        $medium->addMetaFile("{$path}/{$basename}.{$ext}{$extra}");
                        break;
                    case 'thumb':
                        $thumbnail = $this->createMedium("{$path}/{$basename}.{$ext}{$extra}");
                        $thumbnail->set('size', $info->getSize());
                        $medium->set('thumb', $thumbnail);
                }
            } else {
                $altMedium = $this->createMedium($info->getPathname());
                
                if (!$altMedium) {
                    continue;
                }

                $altMedium->set('size', $info->getSize());

                if (!$medium) {
                    $medium = $this->createMedium("{$path}/${basename}.${ext}");

                    if ($medium) {
                        $medium->set('size', filesize("{$path}/${basename}.${ext}"));
                    }
                }

                $medium = $medium ? $medium : $this->scaleMedium($altMedium, $extra, 1);
                
                $medium->addAlternative($this->parseRatio($extra), $altMedium);
            }

            $this->add("{$basename}.{$ext}", $medium);
        }
        foreach ($this->all() as $medium) {

            $thumb = $medium->get('thumb');

            if ($thumb && !$thumb instanceof Medium) {
                $thumb = $this->createMedium($thumb);

                if ($thumb) {
                    $thumb->set('size', filesize($thumb));
                    $medium->set('thumb', $thumb);
                } else {
                    $medium->set('thumb', null);
                }
            }

            if ($medium->get('type') == 'image') {
                $alternatives = $medium->getAlternatives();

                if (empty($alternatives)) {
                    continue;
                }

                $max = max(array_keys($alternatives));

                for ($i=2; $i < $max; $i++) {

                    if (isset($alternatives[$i])) {
                        continue;
                    }

                    $medium->addAlternative($i, $this->scaleMedium($alternatives[$max], $max, $i));
                }
            }
        }
    }



    /**
     * Get medium by filename.
     *
     * @param string $filename
     * @return Medium|null
     */
    public function get($filename)
    {
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
     * Get a list of all audio media.
     *
     * @return array|Medium[]
     */
    public function audios()
    {
        ksort($this->audios, SORT_NATURAL | SORT_FLAG_CASE);
        return $this->audios;
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
     * Create a Medium object from a file
     *
     * @param string $file
     * 
     * @return Medium|null
     */
    protected function createMedium($file)
    {
        if (!file_exists($file)) {
            return null;
        }

        $path = dirname($file);
        $filename = basename($file);
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

        // Add default settings for undefined variables.
        $params += $config->get('media.defaults');
        $params += array(
            'type' => 'file',
            'thumb' => 'media/thumb.png',
            'mime' => 'application/octet-stream',
            'filepath' => $file,
            'filename' => $filename,
            'basename' => $basename,
            'extension' => $ext,
            'path' => $path,
            'modified' => filemtime($file),
        );

        $locator = self::$grav['locator'];

        $lookup = $locator->findResources('image://');
        foreach ($lookup as $lookupPath) {
            if (is_file($lookupPath . $params['thumb'])) {
                $params['thumb'] = $lookupPath . $params['thumb'];
                break;
            }
        }

        return Medium::factory($params);
    }

    protected function scaleMedium($medium, $from, $to)
    {
        $from = $this->parseRatio($from);
        $to = $this->parseRatio($to);

        if ($to > $from) {
            return $medium;
        }

        $ratio = $to / $from;
        $width = (int) ($medium->get('width') * $ratio);
        $height = (int) ($medium->get('height') * $ratio);

        $basename = $medium->get('basename');
        $basename = str_replace('@'.$from.'x', '@'.$to.'x', $basename);

        $debug = $medium->get('debug');
        $medium->set('debug', false);

        $file = $medium->resize($width, $height)->setPrettyName($basename)->url();
        $file = preg_replace('|'. preg_quote(self::$grav['base_url_relative']) .'$|', '', GRAV_ROOT) . $file;

        $medium->set('debug', $debug);

        $size = filesize($file);

        $medium = $this->createMedium($file);
        $medium->set('size', $size);

        return $medium;
    }


    /**
     * @internal
     */
    protected function add($name, $file)
    {
        $this->instances[$name] = $file;
        switch ($file->type) {
            case 'image':
                $this->images[$name] = $file;
                break;
            case 'video':
                $this->videos[$name] = $file;
                break;
            case 'audio':
                $this->audios[$name] = $file;
                break;
            default:
                $this->files[$name] = $file;
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
        $type = 'base';
        $extra = null;

        if (preg_match('/(.*)@(\d+x)\.(.*)$/', $filename, $matches)) {
            $name = $matches[1];
            $extension = $matches[3];
            $type = 'alternative';
            $extra = $matches[2];
        } else {
            $extension = null;
            while (($part = array_shift($fileParts)) !== null) {
                if ($part != 'meta' && $part != 'thumb') {
                    if (isset($extension)) {
                        $name .= '.' . $extension;
                    }
                    $extension = $part;
                } else {
                    $type = $part;
                    $extra = '.' . $part . '.' . implode('.', $fileParts);
                    break;
                }
            }
        }

        return array($name, $extension, $type, $extra);
    }

    protected function parseRatio($ratio)
    {
        if (!is_numeric($ratio)) {
            $ratio = (float) trim($ratio, 'x');
        }

        return $ratio;
    }
}
