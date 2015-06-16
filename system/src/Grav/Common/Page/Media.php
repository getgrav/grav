<?php
namespace Grav\Common\Page;

use Grav\Common\Getters;
use Grav\Common\Grav;
use Grav\Common\Config\Config;
use Grav\Common\GravTrait;
use Grav\Common\Page\Medium\Medium;
use Grav\Common\Page\Medium\MediumFactory;

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

        $iterator = new \FilesystemIterator($path, \FilesystemIterator::UNIX_PATHS | \FilesystemIterator::SKIP_DOTS);

        $media = [];

        /** @var \DirectoryIterator $info */
        foreach ($iterator as $path => $info) {
            // Ignore folders and Markdown files.
            if (!$info->isFile() || $info->getExtension() == 'md' || $info->getBasename() === '.DS_Store') {
                continue;
            }

            // Find out what type we're dealing with
            list($basename, $ext, $type, $extra) = $this->getFileParts($info->getFilename());

            $media["{$basename}.{$ext}"] = isset($media["{$basename}.{$ext}"]) ? $media["{$basename}.{$ext}"] : [];

            if ($type === 'alternative') {
                $media["{$basename}.{$ext}"][$type] = isset($media["{$basename}.{$ext}"][$type]) ? $media["{$basename}.{$ext}"][$type] : [];
                $media["{$basename}.{$ext}"][$type][$extra] = [ 'file' => $path, 'size' => $info->getSize() ];
            } else {
                $media["{$basename}.{$ext}"][$type] = [ 'file' => $path, 'size' => $info->getSize() ];
            }
        }

        foreach ($media as $name => $types) {
            // First prepare the alternatives in case there is no base medium
            if (!empty($types['alternative'])) {
                foreach ($types['alternative'] as $ratio => &$alt) {
                    $alt['file'] = MediumFactory::fromFile($alt['file']);

                    if (!$alt['file']) {
                        unset($types['alternative'][$ratio]);
                    } else {
                        $alt['file']->set('size', $alt['size']);
                    }
                }
            }

            // Create the base medium
            if (!empty($types['base'])) {
                $medium = MediumFactory::fromFile($types['base']['file']);
                $medium && $medium->set('size', $types['base']['size']);
            } else if (!empty($types['alternative'])) {
                $altMedium = reset($types['alternative']);
                $ratio = key($types['alternative']);

                $altMedium = $altMedium['file'];

                $medium = MediumFactory::scaledFromMedium($altMedium, $ratio, 1);
            }

            if (!$medium) {
                continue;
            }

            if (!empty($types['meta'])) {
                $medium->addMetaFile($types['meta']['file']);
            }

            if (!empty($types['thumb'])) {
                // We will not turn it into medium yet because user might never request the thumbnail
                // not wasting any resources on that, maybe we should do this for medium in general?
                $medium->set('thumbnails.page', $types['thumb']['file']);
            }

            // Build missing alternatives
            if (!empty($types['alternative'])) {
                $alternatives = $types['alternative'];

                $max = max(array_keys($alternatives));

                for ($i=2; $i < $max; $i++) {
                    if (isset($alternatives[$i])) {
                        continue;
                    }

                    $types['alternative'][$i] = MediumFactory::scaledFromMedium($alternatives[$max], $max, $i);
                }

                foreach ($types['alternative'] as $ratio => $altMedium) {
                    $medium->addAlternative($ratio, $altMedium['file']);
                }
            }

            $this->add($name, $medium);
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

        if (preg_match('/(.*)@(\d+)x\.(.*)$/', $filename, $matches)) {
            $name = $matches[1];
            $extension = $matches[3];
            $extra = (int) $matches[2];
            $type = 'alternative';

            if ($extra === 1) {
                $type = 'base';
                $extra = null;
            }
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
}
