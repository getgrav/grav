<?php declare(strict_types=1);

namespace Grav\Framework\Contracts\Image;

/**
 * Image save interface.
 */
interface ImageSaveInterface
{
    /**
     * Save the image as a gif.
     *
     * @param string $file
     * @return $this
     */
    public function saveGif(string $file);

    /**
     * Save the image as a png.
     *
     * @param string $file
     * @return $this
     */
    public function savePng(string $file);

    /**
     * Save the image as a Webp.
     *
     * @param string $file
     * @param int $quality
     * @return $this
     */
    public function saveWebp(string $file, int $quality);

    /**
     * Save the image as a jpeg.
     *
     * @param string $file
     * @param int $quality
     * @return $this
     */
    public function saveJpeg(string $file, int $quality);
}
