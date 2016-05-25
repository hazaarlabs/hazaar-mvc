<?php
namespace Hazaar\Controller\Response\Exception;

class WKPDFExecError extends \Hazaar\Exception {

    function __construct($code) {

        parent::__construct('WKPDF shell error, return code ' . $code . '.');

    }

}
