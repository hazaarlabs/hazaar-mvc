<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML script class.
 *
 * @detail      Displays an HTML &lt;script&gt; element.
 *
 * @since       1.1
 */
class Script extends Block {

    /**
     * @detail      The HTML script constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The content can be loaded from various sources and can be specified as one of the
     *              following:
     *
     *              * A string of JavaScript that will comprise the content of the script block.
     *              * A filename to a local file from which the script will be loaded.
     *              * A URL to a file that will cause the HREF parameter to be set.
     *
     * @param       string $type The type of script.  Usually text/javascript.
     *
     * @param       array $parameters Optional parameters to apply to the span.
     */
    function __construct($content = NULL, $type = 'text/javascript', $params = []) {

        if(! $type)
            $type = 'text/javascript';

        if($content) {

            if(preg_match('/^http[s]?\:\/\//', $content)) {

                $params['src'] = $content;

                $content = NULL;

            } elseif(preg_match('/^file?\:\/\/(.*)/', $content, $matches)) {

                $content = $matches[1];

            }

            if(strlen($content) < 255) {

                if(substr($content, 0, 1) == '/' && file_exists($content)) {

                    $content = file_get_contents($content);

                } elseif($file = \Hazaar\Loader::getInstance()->getFilePath(FILE_PATH_VIEW, 'scripts/' . $content)) {

                    $params['src'] = new \Hazaar\Application\Url('script/' . $content);

                    $content = NULL;

                }

            }

        }

        if($type)
            $params['type'] = $type;

        parent::__construct('script', $content, $params);

    }

    public function src($file) {

        if(\Hazaar\Loader::getInstance()->getFilePath(FILE_PATH_VIEW, 'scripts/' . $file)) {

            $file = new \Hazaar\Application\Url('/script/' . $file);

        }

        return parent::src($file);

    }

}

