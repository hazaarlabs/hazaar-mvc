<?php
namespace Hazaar\Controller\Response\Exception;

class WKPDFSystemError extends \Hazaar\Exception {

    function __construct($error) {

        parent::__construct('WKPDF system error: <pre>' . $error . '</pre>');

    }

}
