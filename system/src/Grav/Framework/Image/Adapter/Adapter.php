<?php declare(strict_types=1);

namespace Grav\Framework\Image\Adapter;

use Grav\Framework\Contracts\Image\ImageAdapterInterface;

/**
 * Abstract Image Adapter.
 */
abstract class Adapter implements ImageAdapterInterface
{
    protected int $orientation = 1;
    protected int $scale = 1;

    /**
     * @return int
     */
    public function getRetinaScale(): int
    {
        return $this->scale;
    }

    /**
     * {@inheritdoc}
     */
    public function setRetinaScale(int $scale)
    {
        $this->scale = $scale;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function fixOrientation()
    {
        return $this->applyExifOrientation($this->orientation);
    }

    /**
     * {@inheritdoc}
     */
    public function applyExifOrientation(int $exif_orientation)
    {
        switch ($exif_orientation) {
            case 1: // do nothing
                break;

            case 2: // horizontal flip
                $this->flip(false, true);
                break;

            case 3: // 180 rotate left
                $this->rotate(180.0);
                break;

            case 4: // vertical flip
                $this->flip(true, false);
                break;

            case 5: // vertical flip + 90 rotate right
                $this->flip(true, false);
                $this->rotate(-90.0);
                break;

            case 6: // 90 rotate right
                $this->rotate(-90.0);
                break;

            case 7: // horizontal flip + 90 rotate right
                $this->flip(false, true);
                $this->rotate(-90.0);
                break;

            case 8: // 90 rotate left
                $this->rotate(90.0);
                break;
        }

        return $this;
    }
}
