<?php

declare(strict_types=1);

namespace Hazaar\File;

use Hazaar\File;
use Hazaar\File\Renderer\BaseRenderer;

class Image extends File
{
    private BaseRenderer $renderer;

    public function __construct(?string $filename = null, ?int $quality = null, ?Manager $manager = null, string $renderer = 'default')
    {
        parent::__construct($filename, $manager);
        $this->renderer = $this->getRenderer($renderer, $quality);
    }

    public function setContents(?string $bytes): int
    {
        return $this->renderer->load($bytes, $this->type());
    }

    public function getContents(int $offset = -1, ?int $maxlen = null): string
    {
        if (!$this->renderer->loaded()) {
            $this->renderer->load(parent::getContents($offset, $maxlen), $this->type());
        }

        return $this->renderer->read();
    }

    public function thumbnail(): bool
    {
        $this->checkLoaded();

        return $this->renderer->resize(100, 100);
    }

    public function quality(?int $quality = null): int
    {
        $this->checkLoaded();

        return $this->renderer->quality($quality);
    }

    public function width(): int
    {
        $this->checkLoaded();

        return $this->renderer->width();
    }

    public function height(): int
    {
        $this->checkLoaded();

        return $this->renderer->height();
    }

    public function hasContents(): bool
    {
        $this->checkLoaded();

        return $this->renderer->width() > 0;
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
        $this->checkLoaded();

        return $this->renderer->resize($width, $height, $crop, $align, $keep_aspect, $reduce_only, $ratio, $offsetTop, $offsetLeft);
    }

    public function expand(
        ?int $xwidth = null,
        ?int $xheight = null,
        string $align = 'topleft',
        int $offsetTop = 0,
        int $offsetLeft = 0
    ): bool {
        $this->checkLoaded();

        return $this->renderer->expand($xwidth, $xheight, $align, $offsetTop, $offsetLeft);
    }

    private function getRenderer(string $renderer, ?int $quality = null): BaseRenderer
    {
        switch (strtolower($renderer)) {
            case 'imagick':
            case 'default':
                if (in_array('imagick', get_loaded_extensions())) {
                    return new Renderer\Imagick($quality);
                }

                // no break
            case 'gd':
            default:
                return new Renderer\GD($quality);
        }
    }

    private function checkLoaded(): void
    {
        if (!$this->renderer->loaded()) {
            $this->renderer->load(parent::getContents(), $this->type());
        }
    }
}
