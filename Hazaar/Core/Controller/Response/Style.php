<?php

namespace Hazaar\Controller\Response;

class Style extends File {

    private $compress = FALSE;

    function __construct($source = NULL) {

        parent::__construct();

        $this->setContentType('text/css');

        if($source)
            $this->load($source);

    }

    public function load($filename, $backend = NULL) {

        parent::load($filename, $backend);

        if($this->file->extension() == 'less') {

            if(! ($file = \Hazaar\Loader::getFilePath(FILE_PATH_SUPPORT, 'LessPHP/lessc.inc.php'))) {

                throw new Exception\NoLessSupport();

            }

            require_once($file);

            $less = new \lessc;

            $this->setContent($less->compile($this->getContent()));

            $this->setContentType('text/css');

        }

    }

    public function setCompression($toggle) {

        $this->compress = $toggle;

    }

    public function getContent() {

        if($buffer = parent::getContent()) {

            /* Compress the style sheet if we are configured to compress */
            if($this->getContentType() == 'text/css' && $this->compress) {

                /* remove any comments */
                $buffer = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $buffer);

                /* remove tabs, spaces and new lines */
                $buffer = str_replace(array(
                                          "\r\n",
                                          "\r",
                                          "\n",
                                          "\t",
                                          '  ',
                                          '    ',
                                          '    '
                                      ), '', $buffer);

            }

        }

        return $buffer;

    }

}