<?php

declare(strict_types=1);

namespace Hazaar\File;

use Hazaar\Application;
use Hazaar\File;
use Hazaar\HTTP\Client;
use Hazaar\HTTP\Request;

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
    public const PDF_PORTRAIT = 'Portrait';

    /**
     * PDF generated as landscape (horizontal).
     */
    public const PDF_LANDSCAPE = 'Landscape';
    private ?string $sourceURL;
    private ?Temp $tmp = null;
    private string $status = '';
    private string $orient = 'Portrait';
    private string $size = 'A4';
    private bool $toc = false;
    private int $copies = 1;
    private bool $grayscale = false;
    private string $title = 'PDF Document';

    /**
     * @var array<string,int|string>
     */
    private array $margins;

    private string $lastError;

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
        $cmd = $this->getCommand();
        if (!file_exists($cmd)) {
            // Attempt to install the required file
            if (!$this->install()) {
                throw new Exception\WKPDF\InstallFailed($cmd, $this->lastError);
            }
        }
        if (!is_executable($cmd)) {
            throw new Exception\WKPDF\NotExecutable($cmd);
        }
        $this->tmp = new Temp(mt_rand().'.html');
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
     * Whether to automatically generate a TOC (table of contents) or not.
     *
     * By default TOC is disabled.
     *
     * @param bool $enabled true use TOC, false disable TOC
     */
    public function setTOC(bool $enabled): void
    {
        $this->toc = $enabled;
    }

    /**
     * Set the number of copies to be printed.
     *
     * By default it is one.
     *
     * @param int $count number of page copies
     */
    public function setCopies(int $count): void
    {
        $this->copies = $count;
    }

    /**
     * Whether to print in grayscale or not.
     *
     * By default it is OFF.
     *
     * @param bool $mode true to print in grayscale, false in full color
     */
    public function setGrayscale(bool $mode): void
    {
        $this->grayscale = $mode;
    }

    /**
     * Set PDF title. If empty, HTML &lt;title&gt; of first document is used.
     *
     * By default it is empty.
     */
    public function setTitle(string $text): void
    {
        $this->title = $text;
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
        $this->sourceURL = null;

        return parent::setContents($content);
    }

    /**
     * Set source URL of content.
     *
     * @param string $url A url accessible from the host that will be rendered to a PDF
     */
    public function setSource(string $url): void
    {
        $this->setContents();
        $this->sourceURL = $url;
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

    public function install(): bool
    {
        try {
            $required_programs = ['ar' => 'ar -V', 'tar' => 'tar --version'];
            foreach ($required_programs as $prog => $test) {
                $result = shell_exec($test);
                if (null === $result || false === $result) {
                    throw new \Exception("The program '{$prog}' is required for automated installation of wkhtmltopdf.");
                }
            }
            $cmd = $this->getCommand();
            $target = Application::getInstance()->runtimePath('bin', true);
            if (!is_writable($target)) {
                throw new \Hazaar\Exception('The runtime binary directory is not writable!');
            }
            $tmp_path = new TempDir();
            $asset_suffix = '.bullseye_'.(('x86_64' == php_uname('m')) ? 'amd64' : 'i386').'.deb';
            $client = new Client();
            $request = new Request('https://api.github.com/repos/wkhtmltopdf/packaging/releases');
            if (!($response = $client->send($request))) {
                throw new \Hazaar\Exception('No response returned from Github API call!');
            }
            if (200 != $response->status) {
                throw new \Hazaar\Exception('Got '.$response->status.' from Github API call!');
            }
            $releases = $response->body();
            $sourceURL = null;
            foreach ($releases as $info) {
                if (!(($info instanceof \stdClass) && ($assets = ake($info, 'assets')))) {
                    continue;
                }
                foreach ($assets as $index => $asset) {
                    if (26 === $index) {
                        echo '';
                    }
                    if (substr($asset->name, -strlen($asset_suffix), strlen($asset_suffix)) != $asset_suffix) {
                        continue;
                    }
                    $sourceURL = ake($asset, 'browser_download_url');

                    break 2;
                }
            }
            if (!$sourceURL) {
                throw new \Hazaar\Exception('Unable to automatically install WKHTMLTOPDF.  I was unable to determine the latest release execute source!');
            }
            $tmp_file = $tmp_path.DIRECTORY_SEPARATOR.basename($sourceURL);
            if (!file_exists($tmp_file)) {
                copy($sourceURL, $tmp_file);
                if (!file_exists($tmp_file)) {
                    throw new \Hazaar\Exception('Failed to download installation file!');
                }
            }
            $cwd = getcwd();
            chdir((string) $tmp_path);
            shell_exec('ar x '.$tmp_file.' data.tar.xz');
            shell_exec('tar -xf data.tar.xz ./usr/local/bin/wkhtmltopdf');
            $bin_file = '.'.DIRECTORY_SEPARATOR.'usr'
                .DIRECTORY_SEPARATOR.'local'
                .DIRECTORY_SEPARATOR.'bin'
                .DIRECTORY_SEPARATOR.'wkhtmltopdf';
            if (!file_exists($bin_file)) {
                throw new \Hazaar\Exception('Unable to find executable in release file!');
            }
            copy($bin_file, $cmd);
            @chmod($cmd, 0755);
            chdir($cwd);
            if (!file_exists($cmd)) {
                throw new \Hazaar\Exception('Executable not found after installation!');
            }

            return true;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
        }

        return false;
    }

    public function setMargin(
        int|string $top,
        null|int|string $right = null,
        null|int|string $bottom = null,
        null|int|string $left = null
    ): void {
        if (null === $right) {
            $right = $top;
        }
        if (null === $bottom) {
            $bottom = $top;
        }
        if (null === $left) {
            $left = $right;
        }
        $this->margins = ['T' => $top, 'R' => $right, 'B' => $bottom, 'L' => $left];
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
    protected function render(string &$bytes): bool
    {
        if ($this->sourceURL) {
            $web = $this->sourceURL;
        } else {
            if ('%PDF-' === substr($bytes, 0, 5)) {
                return false;
            }
            $this->tmp->putContents($bytes);
            $web = $this->tmp;
        }
        if (!file_exists($cmd = $this->getCommand())) {
            throw new \Hazaar\Exception('PDF converter executable not found!');
        }
        // number of copies
        $cmd .= (($this->copies > 1) ? ' --copies '.$this->copies : '');
        // orientation
        $cmd .= ' --orientation '.$this->orient;
        // page size
        $cmd .= ' --page-size '.$this->size;
        // table of contents
        $cmd .= ($this->toc ? ' --toc' : '');
        // grayscale
        $cmd .= ($this->grayscale ? ' --grayscale' : '');
        // title
        $cmd .= (('' != $this->title) ? ' --title "'.$this->title.'"' : '');
        if (is_array($this->margins)) {
            foreach ($this->margins as $arg => $value) {
                $cmd .= ' -'.$arg.' '.(is_int($value) ? $value.'mm' : $value);
            }
        }
        // URL and optional to write to STDOUT (with quiet)
        $cmd .= ' -q -l "'.$web.'" -';
        $pipes = self::_pipeExec($cmd);
        if (false !== strpos(strtolower($pipes['stderr']), 'error')) {
            throw new Exception\WKPDF\SystemError($pipes['stderr']);
        }
        if ('' == $pipes['stdout']) {
            throw new Exception\WKPDF\NoData($pipes['stderr']);
        }
        if (((int) $pipes['return']) > 1) {
            throw new Exception\WKPDF\ExecError((int) $pipes['return']);
        }
        $this->status = $pipes['stderr'];
        $bytes = $pipes['stdout'];
        if ($this->tmp->exists()) {
            $this->tmp->unlink();
        }

        return true;
    }

    private function getCommand(): string
    {
        if ($cmd = trim(shell_exec('which wkhtmltopdf'))) {
            return $cmd;
        }
        $path = Application::getInstance()->runtimePath('bin');
        $cmd = 'wkhtmltox';

        return $path.DIRECTORY_SEPARATOR.$cmd;
    }

    /**
     * Advanced execution routine.
     *
     * @param string $cmd   the command to execute
     * @param string $input any input not in arguments
     *
     * @return array<mixed> an array of execution data; stdout, stderr and return "error" code
     */
    private static function _pipeExec(string $cmd, string $input = ''): array
    {
        $proc = proc_open($cmd, [
            0 => [
                'pipe',
                'r',
            ],
            1 => [
                'pipe',
                'w',
            ],
            2 => [
                'pipe',
                'w',
            ],
        ], $pipes);
        fwrite($pipes[0], $input);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $rtn = proc_close($proc);

        return [
            'stdout' => $stdout,
            'stderr' => $stderr,
            'return' => $rtn,
        ];
    }
}
