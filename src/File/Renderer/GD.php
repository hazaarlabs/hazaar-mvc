<?php
/**
 * @file        Hazaar/File/Renderer/GD.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2018 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\File\Renderer;

use Hazaar\Exception;

class GD extends BaseRenderer
{
    private ?\GdImage $img = null;
    private string $type;
    private ?int $quality = null;

    public function __construct(?int $quality = null)
    {
        if (!extension_loaded('gd')) {
            throw new \Exception('GD extension is not loaded');
        }
        $this->quality = $quality;
    }

    public function load(string $bytes, ?string $type = null): int
    {
        if (null === $type) {
            if ($info = getimagesizefromstring($bytes)) {
                $type = substr($info['mime'], 6);
            }
        }
        if ($this->img = imagecreatefromstring($bytes)) {
            $this->loaded = true;
        }
        $this->type = $type;

        return imagesx($this->img) * imagesy($this->img);
    }

    public function read(): false|string
    {
        ob_start();

        switch ($this->type) {
            case 'gif':
                imagegif($this->img);

                break;

            case 'png':
                imagesavealpha($this->img, true);
                if ($this->quality) {
                    imagepng($this->img, null, ($this->quality / 10) - 1);
                    $this->quality = null;
                } else {
                    imagepng($this->img);
                }

                break;

            case 'jpeg':
            default:
                if (is_numeric($this->quality)) {
                    imagejpeg($this->img, null, (int) $this->quality);
                    $this->quality = null;
                } else {
                    imagejpeg($this->img);
                }

                break;
        }

        return ob_get_clean();
    }

    public function quality(?int $quality = null): false|int
    {
        return $this->quality = $quality;
    }

    public function width(): int
    {
        if (!$this->img) {
            return 0;
        }

        return imagesx($this->img);
    }

    public function height(): int
    {
        if (!$this->img) {
            return 0;
        }

        return imagesy($this->img);
    }

    public function compress(int $quality): void
    {
        $this->quality($quality);
    }

    public function resize(
        ?int $width = null,
        ?int $height = null,
        bool $crop = false,
        ?string $align = null,
        bool $keep_aspect = true,
        bool $reduce_only = true,
        ?float $ratio = null,
        int $offsetTop = 0,
        int $offsetLeft = 0
    ): bool {
        // Initialize the source dimenstions
        if (!$this->img) {
            return false;
        }
        $src_x = 0;
        $src_y = 0;
        $src_w = imagesx($this->img);
        $src_h = imagesy($this->img);
        if (!$ratio) {
            $ratio = $src_h / $src_w;
        }
        if ($reduce_only) {
            $width = min($src_w, (int) $width);
            $height = min($src_h, (int) $height);
            if ($src_w <= $width && $src_h <= $height) {
                return false;
            }
        }
        /*
         * Stretching is the default behavior, but if we haven't been given a height
         * we are going to maintain the aspect ratio when resizing.  This overrides
         * the $crop = true because we can't crop if we have no height constraint
         *
         * If we aren't given a width OR a height, make a 100x100 thumbnail
         */
        if (!$width > 0 && !$height > 0) {
            $width = 100;
            $height = 100;
        } elseif (!$width > 0) {
            $width = $keep_aspect ? floor($height / $ratio) : $src_w;
            $crop = false;
        } elseif (!$height > 0) {
            if ($ratio > 1) {
                $height = $width;
                $width = floor($height / $ratio);
            } else {
                $height = floor($width * $ratio);
            }
            $crop = false;
        }
        $dst = imagecreatetruecolor($width, $height);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        // Check if we are cropping and figure out what area we want
        if (true == $crop) {
            // The target height scaled to the original image
            $scale_height = ($src_w / $width) * $height;
            // The target height scaled to the original image
            $scale_width = ($src_h / $height) * $width;
            if ($scale_height < $src_h) {
                if ('top' == $align) {
                    $src_y = 0;
                } elseif ('bottom' == $align) {
                    $src_y = ceil($src_h - $scale_height);
                } else {
                    $src_y = ceil(($src_h / 2) - ($scale_height / 2));
                }
                $src_h = ceil($scale_height);
            }
            if ($scale_width < $src_w) {
                if ('left' == $align) {
                    $src_x = 0;
                } elseif ('right' == $align) {
                    $src_x = ceil($src_w - $scale_width);
                } else {
                    $src_x = ceil(($src_w / 2) - ($scale_width / 2));
                }
                $src_w = ceil($scale_width);
            }
        }
        // Do the actual resize
        if (!imagecopyresampled($dst, $this->img, 0, 0, $src_x, $src_y, $width, $height, $src_w, $src_h)) {
            return false;
        }
        $this->img = $dst;

        return true;
    }

    public function expand(
        ?int $xwidth = null,
        ?int $xheight = null,
        string $align = 'topleft',
        int $offsetTop = 0,
        int $offsetLeft = 0
    ): bool {
        return false;
    }

    /**
     * @param array<string>|string $filter_def
     */
    public function filter(array|string $filter_def): bool
    {
        $filters = [];
        if (is_array($filter_def)) {
            $filters = $filter_def;
        } else {
            $parts = explode(';', $filter_def);
            foreach ($parts as $part) {
                $values = explode(':', $part);
                $key = array_shift($values);
                $filters[$key] = $values;
            }
        }
        foreach ($filters as $filter => $values) {
            $ret = false;

            switch ($filter) {
                case 'blur':  // Blur
                    $value = (int) ($values[0] ?? 1);
                    for ($i = 0; $i < $value; ++$i) {
                        if (!($ret = imagefilter($this->img, IMG_FILTER_GAUSSIAN_BLUR))) {
                            break;
                        }
                    }

                    break;

                case 'sharpen': // Sharpen
                    $value = (int) ($values[0] ?? 1);
                    for ($i = 0; $i < $value; ++$i) {
                        if (!($ret = imagefilter($this->img, IMG_FILTER_SMOOTH, -10))) {
                            break;
                        }
                    }

                    break;

                case 'c': // Contrast
                    $ret = imagefilter($this->img, IMG_FILTER_CONTRAST, (int) ($values[0] ?? 1));

                    break;

                case 'b': // Brightness
                    $ret = imagefilter($this->img, IMG_FILTER_BRIGHTNESS, (int) ($values[0] ?? 1));

                    break;

                case 's': // Saturation
                    $value = (int) ($values[0] ?? 1);
                    $ret = imagefilter($this->img, IMG_FILTER_COLORIZE, -$value, -$value, -$value);

                    break;

                case 'h': // Hue
                    list($red, $green, $blue) = $values;
                    $ret = imagefilter($this->img, IMG_FILTER_COLORIZE, (int)$red, (int)$green, (int)$blue);

                    break;

                case 'edge': // Edge enhancement
                    $ret = imagefilter($this->img, IMG_FILTER_EDGEDETECT);

                    break;

                case 'emboss': // Emboss
                    $ret = imagefilter($this->img, IMG_FILTER_EMBOSS);

                    break;

                case 'flip':  // Vertially flip the image
                    $ret = imageflip($this->img, IMG_FLIP_VERTICAL);

                    break;

                case 'flop':  // Horizontally flip the image
                    $ret = imageflip($this->img, IMG_FLIP_HORIZONTAL);

                    break;
            }
            if (false == $ret) {
                throw new \Exception('There was an error applying filter: '.$filter);
            }
        }

        return true;
    }
}
