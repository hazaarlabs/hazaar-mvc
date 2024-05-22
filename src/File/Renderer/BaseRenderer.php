<?php

declare(strict_types=1);

/**
 * BaseRenderer short summary.
 *
 * BaseRenderer description.
 *
 * @version 1.0
 *
 * @author jamiec
 */

namespace Hazaar\File\Renderer;

interface ImageRendererInterface
{
    public function load(string $bytes, string $type): int;

    public function loaded(): bool;

    public function read(): false|string;

    public function quality(int $quality = null): false|int;

    public function width(): int;

    public function height(): int;

    public function compress(int $quality): void;

    public function resize(
        int $width = null,
        int $height = null,
        bool $crop = false,
        string $align = null,
        bool $keep_aspect = true,
        bool $reduce_only = true,
        float $ratio = null,
        int $offsetTop = 0,
        int $offsetLeft = 0
    ): bool;

    public function expand(
        int $xwidth = null,
        int $xheight = null,
        string $align = 'topleft',
        int $offsetTop = 0,
        int $offsetLeft = 0
    ): bool;
}
abstract class BaseRenderer implements ImageRendererInterface
{
    protected bool $loaded = false;

    public function loaded(): bool
    {
        return $this->loaded;
    }
}
