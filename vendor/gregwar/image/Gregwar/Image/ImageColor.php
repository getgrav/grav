<?php

namespace Gregwar\Image;

/**
 * Color manipulation class
 */
class ImageColor
{
    private static $colors = array(
        'black'     =>  0x000000,
        'silver'    =>  0xc0c0c0,
        'gray'      =>  0x808080,
        'teal'      =>  0x008080,
        'aqua'      =>  0x00ffff,
        'blue'      =>  0x0000ff,
        'navy'      =>  0x000080,
        'green'     =>  0x008000,
        'lime'      =>  0x00ff00,
        'white'     =>  0xffffff,
        'fuschia'   =>  0xff00ff,
        'purple'    =>  0x800080,
        'olive'     =>  0x808000,
        'yellow'    =>  0xffff00,
        'orange'    =>  0xffA500,
        'red'       =>  0xff0000,
        'maroon'    =>  0x800000,
        'transparent' => 0x7fffffff
    );

    public static function gdAllocate($image, $color)
    {
        $colorRGBA = self::parse($color);

        $b = ($colorRGBA)&0xff;
        $colorRGBA >>= 8;
        $g = ($colorRGBA)&0xff;
        $colorRGBA >>= 8;
        $r = ($colorRGBA)&0xff;
        $colorRGBA >>= 8;
        $a = ($colorRGBA)&0xff;

        $c = imagecolorallocatealpha($image, $r, $g, $b, $a);

        if ($color == 'transparent') {
            imagecolortransparent($image, $c);
        }

        return $c;
    }

    public static function parse($color)
    {
        // Direct color representation (ex: 0xff0000)
        if (!is_string($color) && is_numeric($color))
            return $color;

        // Color name (ex: "red")
        if (isset(self::$colors[$color]))
            return self::$colors[$color];

        if (is_string($color)) {
            $color_string = str_replace(' ', '', $color);

            // Color string (ex: "ff0000", "#ff0000" or "0xfff")
            if (preg_match('/^(#|0x|)([0-9a-f]{3,6})/i', $color_string, $matches)) {
                $col = $matches[2];

                if (strlen($col) == 6)
                    return hexdec($col);

                if (strlen($col) == 3) {
                    $r = '';
                    for ($i=0; $i<3; $i++)
                        $r.= $col[$i].$col[$i];
                    return hexdec($r);
                }
            }
            
            // Colors like "rgb(255, 0, 0)"
            if (preg_match('/^rgb\(([0-9]+),([0-9]+),([0-9]+)\)/i', $color_string, $matches)) {
                $r = $matches[1];
                $g = $matches[2];
                $b = $matches[3];
                if ($r>=0 && $r<=0xff && $g>=0 && $g<=0xff && $b>=0 && $b<=0xff) {
                    return ($r << 16) | ($g << 8) | ($b);
                }
            }
        }

        throw new \InvalidArgumentException('Invalid color: '.$color);
    }
}
