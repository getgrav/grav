<?php
namespace Grav\Common\Page\Medium;

use Grav\Common\GravTrait;
use Grav\Common\Data\Blueprint;

/**
 * MediumFactory can be used to more easily create various Medium objects from files or arrays, it should
 * contain most logic for instantiating a Medium object.
 *
 * @author Grav
 * @license MIT
 *
 */
class MediumFactory
{
    use GravTrait;

    /**
     * Create Medium from a file
     *
     * @param  string $file
     * @param  array  $params
     * @return Medium
     */
    public static function fromFile($file, array $params = [])
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
        $params += [
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
        ];

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

    /**
     * Create Medium from array of parameters
     *
     * @param  array          $items
     * @param  Blueprint|null $blueprint
     * @return Medium
     */
    public static function fromArray(array $items = [], Blueprint $blueprint = null)
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

    /**
     * Create a new ImageMedium by scaling another ImageMedium object.
     *
     * @param  ImageMedium $medium
     * @param  int         $from
     * @param  int         $to
     * @return Medium
     */
    public static function scaledFromMedium($medium, $from, $to)
    {
        if (! $medium instanceof ImageMedium) {
            return $medium;
        }

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

        $file = $medium->resize($width, $height)->path();

        $medium->set('debug', $debug);

        $size = filesize($file);

        $medium = self::fromFile($file);
        $medium->set('size', $size);

        return $medium;
    }
}
