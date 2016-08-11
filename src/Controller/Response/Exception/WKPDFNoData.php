<?php
namespace Hazaar\Controller\Response\Exception;

class WKPDFNoData extends \Hazaar\Exception {

    function __construct($error) {

        parent::__construct('WKPDF didn\'t return any data. <pre>' . $error . '</pre>');

    }

}
