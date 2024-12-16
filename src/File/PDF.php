<?php

declare(strict_types=1);

namespace Hazaar\File;

use Dompdf\Dompdf;
use Hazaar\File;

/**
 * PDF File class for generating PDFs from HTML.
 *
 * This class can be used to generate PDFs from HTML sources.  It extends the Hazaar\File class so all of it's methods
 * are available.  The difference here is that when calling Hazaar\File\PDF::set_content(), the content is filtered
 * throught the PDF generator.  So you can set the content as HTML and the actual stored content will be in PDF format.
 *
 * If the content being set is already in PDF format, there is protection coded in that does not attempt to re-render
 * the content.
 */
class PDF extends File
{
    /**
     * PDF generated as landscape (vertical).
     */
    public const PDF_PORTRAIT = 'portrait';

    /**
     * PDF generated as landscape (horizontal).
     */
    public const PDF_LANDSCAPE = 'landscape';
    private ?string $sourceURL = null;
    private string $status = '';
    private string $orient = self::PDF_PORTRAIT;
    private string $size = 'A4';

    /**
     * Hazaar\File\PDF constructor.
     *
     * @param string  $file          Filename to use if the file will be written to disk.  If null, the file can exist only in memory.
     * @param Manager $manager       An optional file manager for accessing public file data
     * @param string  $relative_path internal relative path when accessing the file through a Hazaar\File\Dir object
     *
     * @throws Exception\WKPDF\InstallFailed the automated installation of WKPDFtoHTML failed
     * @throws Exception\WKPDF\NotExecutable the WKPDFtoHTML executable exists but failed to execute
     */
    public function __construct(?string $file = null, ?Manager $manager = null, ?string $relative_path = null)
    {
        if (!class_exists('Dompdf\Dompdf')) {
            throw new \Exception('The DomPdf library is required to generate PDF files.  Please install the library using composer.');
        }
        parent::__construct($file, $manager, $relative_path);
        parent::registerFilter(FILE_FILTER_SET, 'render');
    }

    /**
     * Set orientation, use constants from this class.
     *
     * By default orientation is portrait.
     *
     * @param string $mode use constants from this class
     */
    public function setOrientation(string $mode): void
    {
        $this->orient = $mode;
    }

    /**
     * Set page/paper size.
     *
     * By default page size is A4.
     *
     * @param string $size Formal paper size (eg; A4, letter...)
     */
    public function setPageSize(string $size): void
    {
        $this->size = $size;
    }

    /**
     * Set the HTML content of the new PDF file.
     *
     * This content should be HTML format and will be converted into a PDF immediately and stored in memory.  set_content()
     * will not directly write the file to storage.  If you want to do that, use put_content() instead.
     *
     * @param string $content The HTML content to convert into a PDF
     */
    public function setContents(?string $content = null): ?int
    {
        return parent::setContents($content);
    }

    /**
     * Set source URL of content.
     *
     * @param string $url A url accessible from the host that will be rendered to a PDF
     */
    public function setSource(string $url): void
    {
        $this->sourceURL = $url;
        $this->setContents();
    }

    /**
     * Returns WKPDF print status.
     *
     * @return string WPDF print status
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    public function mimeContentType(): string
    {
        return 'application/pdf';
    }

    /**
     * FILE_FILTER_SET Content filter to convert HTML to PDF.
     *
     * This is the guts of it really.  This is the method that converts the HTML content being set into a PDF.
     */
    protected function render(?string &$bytes): bool
    {
        if ($this->sourceURL) {
            $sourceHTML = file_get_contents($this->sourceURL);
            if (false === $sourceHTML) {
                throw new \Exception('Failed to load source URL: '.$this->sourceURL);
            }
            $bytes = $sourceHTML;
        } elseif (null === $bytes) {
            return false;
        } else {
            if ('%PDF-' === substr($bytes, 0, 5)) {
                return false;
            }
        }
        $dompdf = new Dompdf();
        $dompdf->loadHtml($bytes);
        $dompdf->setPaper($this->size, $this->orient);
        $dompdf->render();
        $bytes = $dompdf->output() ?? '';

        return true;
    }
}
