<?php

declare(strict_types=1);

namespace Hazaar\Controller\Helper;

use Hazaar\Controller\Helper;
use Hazaar\Controller\Response\File;
use Hazaar\Controller\Response\HTML;
use Hazaar\Controller\Response\Image;
use Hazaar\Controller\Response\JSON;
use Hazaar\Controller\Response\PDF;
use Hazaar\Controller\Response\Text;
use Hazaar\Controller\Response\View;
use Hazaar\Controller\Response\XML;
use Hazaar\File\Manager;

class Response extends Helper
{
    public function file(File|string $file, ?Manager $manager = null): File
    {
        return new File($file, $manager);
    }

    public function html(string $content, int $status = 200): HTML
    {
        return new HTML($content, $status);
    }

    public function image(
        string $filename,
        int $quality = 8,
        ?Manager $manager = null
    ): Image {
        return new Image($filename, $quality, $manager);
    }

    /**
     * @param array<mixed>|\stdClass $data
     */
    public function json(array|\stdClass $data, int $status = 200): JSON
    {
        return new JSON($data, $status);
    }

    public function PDF(string $file, bool $downloadable = true): PDF
    {
        return new PDF($file, $downloadable);
    }

    public function text(string $content, int $status = 200): Text
    {
        return new Text($content, $status);
    }

    public function view(string $name): View
    {
        return new View($name);
    }

    public function xml(string $content, int $status = 200): XML
    {
        return new XML($content, $status);
    }
}
