<?php

namespace Hazaar\Controller\Response;

class PDF extends \Hazaar\Controller\Response\HTTP\OK {

    private $html       = '';

    private $source_url = NULL;

    private $cmd        = '';

    private $tmp        = '';

    private $status     = '';

    private $orient     = 'Portrait';

    private $size       = 'A4';

    private $toc        = FALSE;

    private $copies     = 1;

    private $grayscale  = FALSE;

    private $title      = '';

    /**
     * PDF generated as landscape (vertical).
     */
    const PDF_PORTRAIT = 'Portrait';

    /**
     * PDF generated as landscape (horizontal).
     */
    const PDF_LANDSCAPE = 'Landscape';

    /*
     * Access methods
     */

    const PDF_DOWNLOAD = 'D';

    const PDF_EMBEDDED = 'E';

    const PDF_ASSTRING = 'S';

    private $mode       = self::PDF_DOWNLOAD;

    private $last_error = NULL;

    /**
     * Constructor: initialize command line and reserve temporary file.
     */
    public function __construct($mode = self::PDF_EMBEDDED) {

        $this->cmd = $this->getCommand();

        if(! file_exists($this->cmd)) {

            /*
             * Attempt to install the required file
             */

            if(! $this->install()) {

                throw new Exception\WKPDFInstallFailed($this->cmd, $this->last_error);

            }

        }

        if(! is_executable($this->cmd)) {

            throw new Exception\WKPDFNotExecutable($this->cmd);

        }

        do {

            $this->tmp = '/tmp/' . mt_rand() . '.html';

        } while(file_exists($this->tmp));

        $this->mode = $mode;

    }

    static private function getCommand() {

        $path = realpath(APPLICATION_PATH . '/../library/Hazaar/Support');

        $cmd = 'wkhtmltopdf';

        if(php_uname('m') == 'x86_64')
            $cmd .= '-amd64';

        return $path . '/' . $cmd;

    }

    /**
     * Advanced execution routine.
     * @param string $cmd The command to execute.
     * @param string $input Any input not in arguments.
     * @return array An array of execution data; stdout, stderr and return "error" code.
     */
    private static function _pipeExec($cmd, $input = '') {

        $proc = proc_open($cmd, array(
            0 => array(
                'pipe',
                'r'
            ),
            1 => array(
                'pipe',
                'w'
            ),
            2 => array(
                'pipe',
                'w'
            )
        ), $pipes);

        fwrite($pipes[0], $input);

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);

        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[2]);

        $rtn = proc_close($proc);

        return array(
            'stdout' => $stdout,
            'stderr' => $stderr,
            'return' => $rtn
        );

    }

    /**
     * Set orientation, use constants from this class.
     * By default orientation is portrait.
     * @param string $mode Use constants from this class.
     */
    public function setOrientation($mode) {

        $this->orient = $mode;

    }

    /**
     * Set page/paper size.
     * By default page size is A4.
     * @param string $size Formal paper size (eg; A4, letter...)
     */
    public function setPageSize($size) {

        $this->size = $size;

    }

    /**
     * Whether to automatically generate a TOC (table of contents) or not.
     * By default TOC is disabled.
     * @param boolean $enabled True use TOC, false disable TOC.
     */
    public function setTOC($enabled) {

        $this->toc = $enabled;

    }

    /**
     * Set the number of copies to be printed.
     * By default it is one.
     * @param integer $count Number of page copies.
     */
    public function setCopies($count) {

        $this->copies = $count;

    }

    /**
     * Whether to print in grayscale or not.
     * By default it is OFF.
     * @param boolean True to print in grayscale, false in full color.
     */
    public function setGrayscale($mode) {

        $this->grayscale = $mode;

    }

    /**
     * Set PDF title. If empty, HTML <title> of first document is used.
     * By default it is empty.
     * @param string Title text.
     */
    public function setTitle($text) {

        $this->title = $text;

    }

    /**
     * Set html content.
     * @param string $html New html content. It *replaces* any previous content.
     */
    public function setHtml($html) {

        $this->html = $html;

        $this->source_url = NULL;

    }

    /**
     * Set source URL of content.
     * @param string $url A url accessible from the host that will be rendered to a PDF
     */
    public function setSource($url) {

        $this->source_url = $url;

        $this->html = '';

    }

    /**
     * Returns WKPDF print status.
     * @return string WPDF print status.
     */
    public function getStatus() {

        return $this->status;

    }

    /**
     * Convert HTML to PDF.
     */
    private function render() {

        if($this->source_url) {

            $web = $this->source_url;

        } else {

            file_put_contents($this->tmp, $this->html);

            $web = $this->tmp;

        }

        $cmd = '"' . $this->cmd . '"';

        // number of copies
        $cmd .= (($this->copies > 1) ? ' --copies ' . $this->copies : '');

        // orientation
        $cmd .= ' --orientation ' . $this->orient;

        // page size
        $cmd .= ' --page-size ' . $this->size;

        // table of contents
        $cmd .= ($this->toc ? ' --toc' : '');

        // grayscale
        $cmd .= ($this->grayscale ? ' --grayscale' : '');

        // title
        $cmd .= (($this->title != '') ? ' --title "' . $this->title . '"' : '');

        // URL and optional to write to STDOUT
        $cmd .= ' -l "' . $web . '" -';

        $this->content = self::_pipeExec($cmd);

        if(strpos(strtolower($this->content['stderr']), 'error') !== FALSE)
            throw new Exception\WKPDFSystemError($this->content['stderr']);

        if($this->content['stdout'] == '')
            throw new Exception\WKPDFNoData($this->content['stderr']);

        if(((int)$this->content['return']) > 1)
            throw new Exception\WKPDFExecError((int)$this->content['return']);

        $this->status = $this->content['stderr'];

        $this->content = $this->content['stdout'];

        if(file_exists($this->tmp))
            unlink($this->tmp);

    }

    public function setMode($mode) {

        $this->mode = $mode;

    }

    public function write() {

        $this->render();

        switch($this->mode) {
            case self::PDF_DOWNLOAD:

                $this->setHeader('Content-Description', 'File Transfer');

                $this->setHeader('Cache-Control', 'public, must-revalidate, max-age=0');
                // HTTP/1.1

                $this->setHeader('Pragma', 'public');

                $this->setHeader('Expires', 'Sat, 26 Jul 1997 05:00:00 GMT');
                // Date in the past

                $this->setHeader('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT');

                // force download dialog
                $this->setHeader('Content-Type', 'application/force-download');

                $this->setHeader('Content-Type', 'application/octet-stream', FALSE);

                $this->setHeader('Content-Type', 'application/download', FALSE);

                $this->setHeader('Content-Type', 'application/pdf', FALSE);

                // use the Content-Disposition header to supply a recommended filename
                $this->setHeader('Content-Disposition', 'attachment; filename="' . basename($file) . '";');

                $this->setHeader('Content-Transfer-Encoding', 'binary');

                break;

            case self::PDF_ASSTRING:

                //Do Nothing

                break;

            case self::PDF_EMBEDDED:

                $this->setHeader('Content-Type', 'application/pdf');

                $this->setHeader('Cache-Control', 'public, must-revalidate, max-age=0');
                // HTTP/1.1

                $this->setHeader('Pragma', 'public');

                $this->setHeader('Expires', 'Sat, 26 Jul 1997 05:00:00 GMT');
                // Date in the past

                $this->setHeader('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT');

                if(isset($file))
                    $this->setHeader('Content-Disposition', 'inline; filename="' . basename($file) . '";');

                break;
        }

        return parent::write();

    }

    public function install() {

        try {

            $arch = php_uname('m');

            $target = realpath(APPLICATION_PATH . '/../library/Hazaar/Support');

            $msg = 'Done';

            if(! is_writable($target)) {

                $msg = "The target directory is not writable<p>If you would like to automatically install the wkhtmltopdf executable then please execute:";

                $uid = posix_getuid();

                $user = posix_getpwuid($uid);

                $gid = posix_getgid();

                $group = posix_getgrgid($gid);

                $owner_user = fileowner($target);

                $owner_group = filegroup($target);

                $perms = fileperms($target);

                if($owner_user == $uid && ! ($perms & 0x0010)) {//Owner but no write priv

                    $msg .= "<pre>chmod u+wx $target</pre>";

                } elseif($owner_group != $gid) {

                    $msg .= "<pre>chgrp $group[name] $target</pre>";

                }

                if(! ($perms & 0x0010)) {

                    $msg .= "<pre>chmod g+wx $target</pre>";

                }

                throw new \Exception($msg);

            }

            $tmp_path = '/tmp/wkhtmltopdf-' . $arch . '.bz2';

            if(! file_exists($tmp_path)) {

                if($arch == 'x86_64') {

                    $source = 'http://wkhtmltopdf.googlecode.com/files/wkhtmltopdf-0.11.0_rc1-static-amd64.tar.bz2';

                } else {

                    $source = 'http://wkhtmltopdf.googlecode.com/files/wkhtmltopdf-0.11.0_rc1-static-i386.tar.bz2';

                }

                copy($source, $tmp_path);

                if(! file_exists($tmp_path))
                    throw new Exception\WKPDFInstallFailed('Failed to download installation file!');

            }

            $out = shell_exec("tar xjf $tmp_path --directory $target 2>&1");

            if(! file_exists(PDF::getCommand()))
                throw new Exception\WKPDFInstallFailed('Executable not found after installation!');

            return TRUE;

        } catch(\Exception $e) {

            $this->last_error = $e->getMessage();

        }

        return FALSE;

    }

}