<?php

declare(strict_types=1);

/**
 * @file        Hazaar/File/Renderer/Imagick.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2018 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\File\Renderer;

class Imagick extends BaseRenderer
{
    private \Imagick $dst;
    private ?int $quality;

    public function __construct(?int $quality = null)
    {
        $this->dst = new \Imagick();
        $this->quality = $quality;
    }

    public function load(string $bytes, ?string $type = null): int
    {
        if (strlen($bytes) > 0) {
            $this->loaded = $this->dst->readImageBlob($bytes);
            $geometry = $this->dst->getImageGeometry();

            return $geometry['width'] * $geometry['height'];
        }

        return 0;
    }

    public function read(): false|string
    {
        return $this->dst->getImageBlob();
    }

    public function quality(?int $quality = null): false|int
    {
        if (null === $quality) {
            return $this->dst->getImageCompressionQuality();
        }

        if ($this->dst->setImageCompressionQuality($quality)) {
            return $quality;
        }

        return false;
    }

    public function width(): int
    {
        return $this->dst->getImageGeometry()['width'];
    }

    public function height(): int
    {
        return $this->dst->getImageGeometry()['height'];
    }

    public function compress(int $quality): void
    {
        $this->dst->setImageCompressionQuality($quality);
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
        $geo = $this->dst->getImageGeometry();
        if (null === $ratio) {
            $ratio = $geo['height'] / $geo['width'];
            if ($crop) {
                $keep_aspect = false;
            }
        } else {
            $ratio = floatval($ratio);
        }
        if ($reduce_only) {
            $width = min($geo['width'], (int) $width);
            $height = min($geo['height'], (int) $height);
            if ($geo['width'] <= $width && $geo['height'] <= $height) {
                return false;
            }
        }
        if (!$width > 0 && !$height > 0) { // Without a width or height, default to 100x100.
            $width = 100;
            $height = 100;
        } elseif (!$width > 0) { // Without a width, calculate it based on the aspect ratio
            $width = $keep_aspect ? (int) floor($height / $ratio) : $geo['width'];
        } elseif (!$height > 0) { // Without a height, calculate it based on the aspect ratio
            $height = $keep_aspect ? (int) floor($width * $ratio) : $geo['height'];
        }
        if (true === $crop) {
            $o_ratio = $geo['height'] / $geo['width'];
            $scale_width = $width;
            $scale_height = $height;
            if ($width > ($height / $o_ratio)) {
                $scale_height = $width * $o_ratio;
            } else {
                $scale_width = $height / $o_ratio;
            }
            $this->dst->resizeImage($scale_width, $scale_height, \Imagick::FILTER_CATROM, 0.9);
            $x = 0;
            $y = 0;
            if ($height < $scale_height) {
                if ('bottom' == $align || 'bottomleft' == $align || 'bottomright' == $align) {
                    $y = $scale_height - $height;
                } elseif (!('top' == $align || 'topleft' == $align || 'topright' == $align)) {
                    $y = (int) round(($scale_height - $height) / 2);
                }
            }
            if ($width < $scale_width) {
                if ('right' == $align || 'topright' == $align || 'bottomright' == $align) {
                    $x = $scale_width - $width;
                } elseif (!('left' == $align || 'topleft' == $align || 'bottomleft' == $align)) {
                    $x = (int) floor(($scale_width - $width) / 2);
                }
            }
            $x += $offsetLeft;
            $y += $offsetTop;
            $this->dst->cropImage($width, $height, $x, $y);
        } else {
            $this->dst->resizeImage($width, $height, \Imagick::FILTER_CATROM, 0.9);
        }
        if ($this->quality) {
            $this->dst->setImageCompressionQuality($this->quality);
            $this->quality = null;
        }

        return true;
    }

    public function expand(
        ?int $xwidth = null,
        ?int $xheight = null,
        string $align = 'topleft',
        int $offsetTop = 0,
        int $offsetLeft = 0
    ): bool {
        $geo = $this->dst->getImageGeometry();
        $width = $geo['width'];
        $height = $geo['height'];
        $x = 0;
        $y = 0;
        if ($xwidth) {
            $width = $geo['width'] + (int) $xwidth;
            if ('right' == $align || 'topright' == $align || 'bottomright' == $align) {
                $x = (int) $xwidth;
            } elseif (!('left' == $align || 'topleft' == $align || 'bottomleft' == $align)) {
                $x = (int) $xwidth / 2;
            }
        }
        if ($xheight) {
            $height = $geo['height'] + (int) $xheight;
            if ('bottom' == $align || 'bottomleft' == $align || 'bottomright' == $align) {
                $y = (int) $xheight;
            } elseif (!('top' == $align || 'topleft' == $align || 'topright' == $align)) {
                $y = (int) $xheight;
            }
        }
        $x += $offsetLeft;
        $y += $offsetTop;
        $bg = $this->dst->getImageBackgroundColor();
        $new = new \Imagick();
        $new->newImage($width, $height, $bg);
        $new->setImageFormat($this->dst->getImageFormat());
        $new->compositeImage($this->dst, \Imagick::COMPOSITE_DEFAULT, $x, $y);

        return true;
    }

    public function importPDF(string $content, int $dpi = 160): bool
    {
        $this->dst = new \Imagick();
        $this->dst->setResolution($dpi, $dpi);

        return $this->dst->readImageBlob($content);
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
            switch ($filter) {
                case 'blur':  // Blur
                    $value = floatval($values[0]);
                    $this->dst->blurImage($value * 2, $value);

                    break;

                case 'sharpen': // Sharpen
                    $value = floatval($values[0]);
                    $this->dst->sharpenImage($value * 2, $value);

                    break;

                case 'c': // Contrast
                    $value = (int)$values[0];
                    if ($value > 0) {
                        for ($i = 1; $i < $value; ++$i) {
                            $this->dst->contrastImage(true);
                        }
                    } else {
                        for ($i = 0; $i > $value; --$i) {
                            $this->dst->contrastImage(false);
                        }
                    }

                    break;

                case 'b': // Brightness
                    $value = (int)$values[0];
                    $this->dst->modulateImage($value, 100, 100);

                    break;

                case 's': // Saturation
                    $value = (int)$values[0];
                    $this->dst->modulateImage(100, $value, 100);

                    break;

                case 'h': // Hue
                    $value = (int)$values[0];
                    $this->dst->modulateImage(100, 100, $value);

                    break;

                case 'mod': // Brightness, Saturation & Hue
                    list($brightness, $saturation, $hue) = $values;
                    $this->dst->modulateImage(floatval($brightness), floatval($saturation), floatval($hue));

                    break;

                case 'noise': // Add Noise
                    $value = floatval($values[0]);
                    for ($i = 0; $i < $value; ++$i) {
                        $this->dst->addNoiseImage(\Imagick::NOISE_UNIFORM);
                    }

                    break;

                case 'border': // Add a border
                    $width = (int)$values[0];
                    $color = ake($values, 1, 'black');
                    $this->dst->borderImage($color, $width, $width);

                    break;

                case 'edge': // Edge enhancement
                    $value = floatval($values[0]);
                    $this->dst->edgeImage($value);

                    break;

                case 'emboss': // Emboss
                    $value = floatval($values[0]);
                    $this->dst->embossImage($value * 2, $value);

                    break;

                case 'flip':  // Vertially flip the image
                    $this->dst->flipImage();

                    break;

                case 'flop':  // Horizontally flip the image
                    $this->dst->flopImage();

                    break;

                case 'oil':  // Oil painting effect
                    $value = floatval($values[0]);
                    $this->dst->oilPaintImage($value);

                    break;

                case 'solarize':  // Solarize effect
                    $value = (int)$values[0];
                    $this->dst->solarizeImage($value);

                    break;

                case 'swirl':  // Swirl effect
                    $value = (int)$values[0];
                    $this->dst->swirlImage($value);

                    break;

                case 'transpose':  // Vertical mirror
                    $this->dst->transposeImage();

                    break;

                case 'transverse':  // Horizontal mirror
                    $this->dst->transverseImage();

                    break;

                case 'vignette':  // Add a vignette
                    $value = ake($values, 0);
                    $offset = ake($values, 1, 0);
                    $this->dst->setImageBackgroundColor('black');
                    $this->dst->vignetteImage(0, $value, $offset, $offset);

                    break;

                case 'wave':  // Wave effect
                    list($amp, $len) = $values;
                    $this->dst->waveImage(floatval($amp), floatval($len));

                    break;
            }
        }

        return true;
    }
}
