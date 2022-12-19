<?php

if(!function_exists('getallheaders')){

    function getallheaders(){

        return hazaar_request_headers();

    }

}

// apache_request_headers replicement for nginx
if(!function_exists('apache_request_headers')){

    function apache_request_headers() {

        return hazaar_request_headers();

    }

}

if(!function_exists('http_response_code')){

    function http_response_code($code = NULL) {

        if ($code) {

            if ($text = http_response_text($code)) {

                header($_SERVER['SERVER_PROTOCOL'] . ' ' . $code . ' ' . $text);

                $_SERVER['HTTP_RESPONSE_CODE'] = $code;

                return true;

            } else {

                dieDieDie('Missing Http_Status.dat file!');

            }

        } else {

            return (array_key_exists('HTTP_RESPONSE_CODE', $_SERVER) ? $_SERVER['HTTP_RESPONSE_CODE'] : 200);

        }

        return false;

    }
}

if(!function_exists('money_format')){

    /**
     * Replacement for the "built-in" PHP function money_format(), which isn't always built-in.
     *
     * Some systems, particularly Windows (also possible BSD) the built-in money_format() function
     * will not be defined.  This is because it's just a wrapper for a C function called strfmon()
     * which isn't available on all platforms (such as Windows).
     *
     * @param mixed $format The format specification, see
     *
     * @param mixed $number The number to be formatted.
     *
     * @return string The formatted number.
     */
    function money_format($format, $number) {

        $regex  = '/%((?:[\^!\-]|\+|\(|\=.)*)([0-9]+)?(?:#([0-9]+))?(?:\.([0-9]+))?([in%])/';

        if (setlocale(LC_MONETARY, 0) == 'C')
            setlocale(LC_MONETARY, '');

        $locale = localeconv();

        preg_match_all($regex, $format, $matches, PREG_SET_ORDER);

        foreach ($matches as $fmatch) {

            $value = floatval($number);

            $flags = [
                'fillchar'  => preg_match('/\=(.)/', $fmatch[1], $match) ?
                               $match[1] : ' ',
                'nogroup'   => preg_match('/\^/', $fmatch[1]) > 0,
                'usesignal' => preg_match('/\+|\(/', $fmatch[1], $match) ?
                               $match[0] : '+',
                'nosimbol'  => preg_match('/\!/', $fmatch[1]) > 0,
                'isleft'    => preg_match('/\-/', $fmatch[1]) > 0
            ];

            $width      = trim($fmatch[2] ?? '') ? (int)$fmatch[2] : 0;

            $left       = trim($fmatch[3] ?? '') ? (int)$fmatch[3] : 0;

            $right      = trim($fmatch[4] ?? '') ? (int)$fmatch[4] : $locale['int_frac_digits'];

            $conversion = $fmatch[5];

            $positive = true;

            if ($value < 0) {

                $positive = false;

                $value  *= -1;

            }

            $letter = $positive ? 'p' : 'n';

            $prefix = $suffix = $cprefix = $csuffix = $signal = '';

            $signal = $positive ? $locale['positive_sign'] : $locale['negative_sign'];

            switch (true) {
                case $locale["{$letter}_sign_posn"] == 1 && $flags['usesignal'] == '+':
                    $prefix = $signal;
                    break;

                case $locale["{$letter}_sign_posn"] == 2 && $flags['usesignal'] == '+':
                    $suffix = $signal;
                    break;

                case $locale["{$letter}_sign_posn"] == 3 && $flags['usesignal'] == '+':
                    $cprefix = $signal;
                    break;

                case $locale["{$letter}_sign_posn"] == 4 && $flags['usesignal'] == '+':
                    $csuffix = $signal;
                    break;

                case $flags['usesignal'] == '(':
                case $locale["{$letter}_sign_posn"] == 0:
                    $prefix = '(';
                    $suffix = ')';
                    break;

            }

            if (!$flags['nosimbol']) {

                $currency = $cprefix . ($conversion == 'i' ? $locale['int_curr_symbol'] : $locale['currency_symbol']) . $csuffix;

            } else {

                $currency = '';

            }

            $space  = $locale["{$letter}_sep_by_space"] ? ' ' : '';

            $value = number_format($value, $right, $locale['mon_decimal_point'], $flags['nogroup'] ? '' : $locale['mon_thousands_sep']);

            $value = @explode($locale['mon_decimal_point'], $value);

            $n = strlen($prefix) + strlen($currency) + strlen($value[0]);

            if ($left > 0 && $left > $n)
                $value[0] = str_repeat($flags['fillchar'], $left - $n) . $value[0];

            $value = implode($locale['mon_decimal_point'], $value);

            if ($locale["{$letter}_cs_precedes"])
                $value = $prefix . $currency . $space . $value . $suffix;
            else
                $value = $prefix . $value . $space . $currency . $suffix;

            if ($width > 0)
                $value = str_pad($value, $width, $flags['fillchar'], $flags['isleft'] ? STR_PAD_RIGHT : STR_PAD_LEFT);

            $format = str_replace($fmatch[0], $value, $format);

        }

        return $format;

    }

}

if (!function_exists('str_putcsv')) {

    /**
     * Convert an array into a CSV line.
     *
     * @param array $input
     *
     * @param string $delimiter Defaults to comma (,)
     *
     * @param string $enclosure Defaults to double quote (")
     *
     * @return string
     */
    function str_putcsv($input, $delimiter = ',', $enclosure = '"', $escape_char = "\\") {

        $fp = fopen('php://temp', 'r+b');

        fputcsv($fp, $input, $delimiter, $enclosure, $escape_char);

        rewind($fp);

        $data = rtrim(stream_get_contents($fp), "\n");

        fclose($fp);

        return $data;

    }

}

if(!function_exists('strftime')){

    function strftime($format, $timestamp = null){

        return str_ftime($format, $timestamp);
        
    }
    
}