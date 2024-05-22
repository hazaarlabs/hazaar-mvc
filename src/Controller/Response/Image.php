<?php

declare(strict_types=1);

namespace Hazaar\Controller\Response;

use Hazaar\File as FileObject;
use Hazaar\File\Image as ImageObject;
use Hazaar\File\Manager;

class Image extends File
{
    protected ImageObject $file;

    public function __construct(
        null|File|string $filename = null,
        ?int $quality = null,
        ?Manager $manager = null
    ) {
        $this->file = new ImageObject($filename, $quality, $manager);
    }

    public function load(FileObject|string $file, ?Manager $manager = null): bool
    {
        $file = new ImageObject($file, 100, $manager);

        return parent::load($file);
    }

    public function setFormat(string $format): void
    {
        $this->file->setMimeContentType('image/'.$format);
    }

    public function width(): ?int
    {
        return $this->file->width();
    }

    public function height(): ?int
    {
        return $this->file->height();
    }

    public function quality(?int $quality = null): ?int
    {
        return $this->file->quality($quality);
    }

    public function encodeDataStream(): void
    {
        if ($content = $this->getContent()) {
            $this->setContent('data:'.$this->getContentType().';base64,'.base64_encode($content));
            $this->setContentType('text/css');
        }
    }

    public function getContent(): string
    {
        return $this->file->getContents();
    }

    public function fileExists(): bool
    {
        return $this->file->exists();
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
        return $this->file->resize(
            $width,
            $height,
            $crop,
            $align,
            $keep_aspect,
            $reduce_only,
            $ratio,
            $offsetTop,
            $offsetLeft
        );
    }

    public function expand(
        ?int $width = null,
        ?int $height = null,
        string $align = 'topleft',
        int $offsettop = 0,
        int $offsetleft = 0
    ): bool {
        return $this->file->expand(
            $width,
            $height,
            $align,
            $offsettop,
            $offsetleft
        );
    }
}
