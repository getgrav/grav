<?php
namespace Grav\Common\Page\Medium;

use Grav\Common\Config\Config;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Grav;
use Grav\Common\GravTrait;
use Grav\Common\Data\Blueprint;
use Grav\Common\Data\Data;
use Grav\Common\Markdown\Parsedown;
use Gregwar\Image\Image as ImageFile;

/**
 * The Image medium holds information related to an individual image. These are then stored in the Media object.
 *
 * @author Grav
 * @license MIT
 *
 */
class Factory
{
    use GravTrait;

    public static function fromFile($file, $params = [])
    {
        if (!file_exists($file)) {
            return null;
        }

        $path = dirname($file);
        $filename = basename($file);
        $parts = explode('.', $filename);
        $ext = array_pop($parts);
        $basename = implode('.', $parts);

        $config = self::getGrav()['config'];

        $media_params = $config->get("media.".strtolower($ext));
        if (!$media_params) {
            return null;
        }

        $params += $media_params;

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
            'thumbnails' => []
        );

        $locator = self::getGrav()['locator'];

        $lookup = $locator->findResources('image://');
        foreach ($lookup as $lookupPath) {
            if (is_file($lookupPath . '/' . $params['thumb'])) {
                $params['thumbnails']['default'] = $lookupPath . '/' . $params['thumb'];
                break;
            }
        }

        return static::fromArray($params);
    }

    public static function fromArray($items = array(), Blueprint $blueprint = null)
    {
        $type = isset($items['type']) ? $items['type'] : null;

        switch ($type) {
            case 'image':
                return new ImageMedium($items, $blueprint);
                break;
            case 'thumbnail':
                return new ThumbnailImageMedium($items, $blueprint);
                break;
            case 'animated':
            case 'vector':
                return new StaticImageMedium($items, $blueprint);
                break;
            case 'video':
                return new VideoMedium($items, $blueprint);
                break;
            default:
                return new Medium($items, $blueprint);
                break;
        }
    }

    public static function scaledFromMedium($medium, $from, $to)
    {
        if (! $medium instanceof ImageMedium)
            return $medium;

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
        $file = preg_replace('|'. preg_quote(self::getGrav()['base_url_relative']) .'$|', '', GRAV_ROOT) . $file;

        $medium->set('debug', $debug);

        $size = filesize($file);

        $medium = self::fromFile($file);
        $medium->set('size', $size);

        return $medium;
    }
}