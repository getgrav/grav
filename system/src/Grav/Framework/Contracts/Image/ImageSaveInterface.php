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
     * @param string|null $filepath
     * @return $this
     */
    public function saveGif(?string $filepath);

    /**
     * Save the image as a png.
     *
     * @param string|null $filepath
     * @return $this
     */
    public function savePng(?string $filepath);

    /**
     * Save the image as a Webp.
     *
     * @param string|null $filepath
     * @param int $quality
     * @return $this
     */
    public function saveWebp(?string $filepath, int $quality);

    /**
     * Save the image as a jpeg.
     *
     * @param string|null $filepath
     * @param int $quality
     * @return $this
     */
    public function saveJpeg(?string $filepath, int $quality);
}
