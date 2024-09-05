<?php

declare(strict_types=1);

namespace Hazaar\Controller\Response;

use Hazaar\Controller\Response\HTTP\OK;
use Hazaar\File;
use Hazaar\File\PDF as PDFFile;

class PDF extends OK
{
    private PDFFile $pdf_file;
    private bool $downloadable = false;

    /**
     * Constructor: initialize command line and reserve temporary file.
     */
    public function __construct(null|PDFFile|string $file = null, bool $downloadable = false)
    {
        if (is_string($file)) {
            $file = new PDFFile($file);
        } elseif ($file instanceof File) {
            $file = new PDFFile($file->fullpath(), $file->getManager(), $file->relativepath());
        }
        $this->pdf_file = ($file instanceof PDFFile) ? $file : new PDFFile();
        $this->downloadable = $downloadable;
    }

    /**
     * @param array<mixed> $args
     */
    public function __call(string $method, array $args): mixed
    {
        if (method_exists($this->pdf_file, $method)) {
            return call_user_func_array([$this->pdf_file, $method], $args);
        }

        return false;
    }

    public function writeOutput(): void
    {
        $this->setContentType('application/pdf');
        if (true === $this->downloadable) {
            $this->setHeader('Content-Description', 'File Transfer');
            $this->setHeader('Cache-Control', 'public, must-revalidate, max-age=0');
            // HTTP/1.1
            $this->setHeader('Pragma', 'public');
            $this->setHeader('Expires', 'Sat, 26 Jul 1997 05:00:00 GMT');
            // Date in the past
            $this->setHeader('Last-Modified', gmdate('D, d M Y H:i:s').' GMT');
            // force download dialog
            $this->setHeader('Content-Type', 'application/force-download');
            $this->setHeader('Content-Type', 'application/octet-stream', false);
            $this->setHeader('Content-Type', 'application/download', false);
            $this->setHeader('Content-Type', 'application/pdf', false);
            // use the Content-Disposition header to supply a recommended filename
            $this->setHeader('Content-Disposition', 'attachment; filename="'.$this->pdf_file->basename().'";');
            $this->setHeader('Content-Transfer-Encoding', 'binary');
        } else {
            $this->setHeader('Cache-Control', 'public, must-revalidate, max-age=0');
            // HTTP/1.1
            $this->setHeader('Pragma', 'public');
            $this->setHeader('Expires', 'Sat, 26 Jul 1997 05:00:00 GMT');
            // Date in the past
            $this->setHeader('Last-Modified', gmdate('D, d M Y H:i:s').' GMT');
            if ($filename = $this->pdf_file->basename()) {
                $this->setHeader('Content-Disposition', 'inline; filename="'.$filename.'";');
            }
        }

        parent::writeOutput();
    }

    public function getContent(): string
    {
        return $this->pdf_file->getContents();
    }
}
