<?php

/**
 * @file        Hazaar/HelperFunctions.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

/**
 * @brief Array value normalizer
 *
 * @detail Returns a value from an array if it exists. If it doesn't exist a default value can be specified.
 * Otherwise
 * null is returned.
 *
 * This helps prevent array key not found errors in the PHP interpreter.
 *
 * @since 1.0.0
 *       
 * @param mixed $array
 *            The array to search.
 * @param mixed $key
 *            The array key value to look for.
 * @param mixed $default
 *            An optional default value to return if the key does not exist.
 *            
 * @return mixed The value if it exists in the array. Returns the default if it does not. Default is null if not
 *         specified.
 */
function ake($array, $key, $default = NULL, $non_empty = FALSE) {

    if ((is_array($array) || $array instanceof \ArrayAccess) && isset($array[$key]) && $array[$key] !== NULL && (!$non_empty || ($non_empty && trim($array[$key]))))
        return $array[$key];
    
    if ($array instanceof \Hazaar\Model\Strict)
        return $array->ake($key, $default, $non_empty);
    
    return $default;

}

/**
 * @brief Normalize boolean values
 *
 * @detail This helper function will take a string representation of a boolean such as 't', 'true', 'yes', 'ok' and
 * return a boolean type value.
 *
 * @since 1.0.0
 *       
 * @param string $value
 *            The string representation of the boolean
 *            
 * @return boolean
 */
function boolify($value) {

    $value = strbool($value);
    
    if ($value == 'true')
        return TRUE;
    
    return FALSE;

}

/**
 * @brief Retrieve string value of boolean
 *
 * @detail Normalise boolean string to 'true' or 'false' based on various boolean representations
 *
 * @since 1.0.0
 *       
 * @param string $value
 *            The string representation of the boolean
 *            
 * @return string
 */
function strbool($value) {

    if (is_array($value))
        return FALSE;
    
    $value = strtolower(trim($value));
    
    if ($value == 't' || $value == 'true' || $value == 'on' || $value == 'yes') {
        
        return 'true';
    } elseif (preg_match('/(\!|not)\s*null/', $value)) {
        
        return 'true';
    } elseif ((int) $value != 0) {
        
        return 'true';
    }
    
    return 'false';

}

/**
 * @brief Test whether a value is a boolean
 *
 * @detail Checks for various representations of a boolean, including strings of 'true/false' and 'yes/no'.
 *
 * @since 2.0.0
 *       
 * @param string $value
 *            The string representation of the boolean
 *            
 * @return boolean
 */
function is_boolean($value) {

    if (!is_string($value))
        return FALSE;
    
    $value = strtolower(trim($value));
    
    $accepted = array(
        't',
        'true',
        'f',
        'false',
        'y',
        'yes',
        'n',
        'no',
        'on',
        'off'
    );
    
    return in_array($value, $accepted);

}

/**
 * The Yes/No function
 *
 * Simply returns Yes or No based on a boolean value.
 *
 * @param boolean A boolean type value.  Can be an actual boolean or 1/0 yes/no on/off, etc.
 * 
 * @returns string
 */
function yn($value){

    return boolify($value) ? 'Yes' : 'No';
    
}

/**
 * @brief Retreive first non-null value from parameter list
 *
 * @detail Takes a variable list of arguments and returns the first non-null value.
 *
 * @since 1.0.0
 * @return mixed The first non-NULL argument value, or NULL if all values are NULL.
 */
function coalesce() {

    foreach(func_get_args() as $arg) {
        
        if ($arg !== NULL)
            return $arg;
    }
    
    return NULL;

}

/**
 * @brief Test of array is multi-dimensional
 *
 * @detail Test an array to see if it's a multidimensional array and returns TRUE or FALSE.
 *
 * @since 1.0.0
 *       
 * @param array $array
 *            The array to test
 *            
 * @return boolean
 */
function is_multi_array(array $array) {

    foreach($array as $a)
        if (is_array($a))
            return TRUE;
    
    return FALSE;

}

/**
 * @brief Test of array is an associative array
 *
 * @detail Test an array to see if it is associative or numerically keyed. Returns TRUE for associative or FALSE
 * for numeric.
 *
 * @since 1.0.0
 *       
 * @param array $array
 *            The array to test
 *            
 * @return boolean
 */
function is_assoc(array $array) {

    return (bool) count(array_filter(array_keys($array), 'is_string'));

}

function array_flatten($array) {

    if (!is_array($array))
        return NULL;
    
    $items = array();
    
    foreach($array as $key => $value) {
        
        $items[] = $key . '=' . $value;
    }
    
    return implode(';', $items);

}

function array_unflatten($items, $delim = '=', $section_delim = ';') {

    if (!is_array($items))
        $items = explode($section_delim, $items);
    
    $result = array();
    
    foreach($items as $item) {
        
        $parts = explode($delim, $item, 2);
        
        if (count($parts) > 1) {
            
            list($key, $value) = $parts;
            
            $result[$key] = trim($value);
        } else {
            
            $result[] = trim($parts[0]);
        }
    }
    
    return $result;

}

function base64url_encode($data) {

    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');

}

function base64url_decode($data) {

    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) + (4 - (strlen($data) % 4) % 4), '=', STR_PAD_RIGHT));

}

/**
 * Seek the array cursor forward $count number of elements
 *
 * @param $array The
 *            array to seek
 *            
 * @param $count The
 *            number of elements to seek forward
 */
function array_seek(&$array, $count) {

    if (!is_array($array) || !$count > 0)
        return;
    
    for($i = 0; $i < $count; $i++)
        next($array);

}

/**
 * @brief Build a correctly formatted URL from argument list
 *
 * @detail This function will build a correctly formatted HTTP compliant URL using a list of parameters. If any
 * of
 * the parameters
 * are null then they will be omitted from the formatted output, including any extra values.
 *
 * For example, you can specify a username and a password which will be formatted as username:password\@.
 * However if you omit
 * the password you will simply get username\@.
 *
 * @param string $scheme
 *            The protocol to use. Usually http or https.
 * @param string $host
 *            Hostname
 * @param integer $port
 *            (optional) Port
 * @param string $user
 *            (optional) Username
 * @param string $pass
 *            (optional) User password. If set, a username is required.
 * @param string $path
 *            (optional) Path suffix
 * @param array $query
 *            (optional) Array of parameters to send. ie: the stuff after the '?'. Uses
 *            http_build_query to generate string.
 * @param string $fragment
 *            (optional) Anything to go after the '#'.
 */
function build_url($scheme = 'http', $host = 'localhost', $port = NULL, $user = NULL, $pass = NULL, $path = NULL, $query = array(), $fragment = NULL) {

    $url = strtolower(trim($scheme)) . '://';
    
    if ($user = trim($user) || ($user && $pass = trim($pass))) {
        
        $url .= $user . ($pass ? ':' . $pass : NULL) . '@';
    }
    
    $url .= trim($host);
    
    if (is_numeric($port = trim($port)) && $port != 80)
        $url .= ':' . $port;
    
    if ($path = trim($path))
        $url .= $path;
    
    if (is_array($query) && count($query) > 0)
        $url .= '?' . http_build_query($query);
    
    if ($fragment = trim($fragment))
        $url .= '#' . $fragment;
    
    return $url;

}

/**
 * @brief Byte to string formatter
 *
 * @detail Formats an integer representing a size in bytes to a human readable string representation.
 *
 * @since 1.0.0
 *       
 * @param int $bytes
 *            The byte value to convert to a string.
 * @param string $type
 *            The type to convert to. Type can be:
 *            * B (bytes)
 *            * K (kilobytes)
 *            * M (megabytes)
 *            * G (giabytes)
 *            * T (terrabytes)
 * @param int $precision
 *            The number of decimal places to show.
 *            
 * @return string The human readable byte string. eg: '100 MB'.
 */
function str_bytes($bytes, $type = NULL, $precision = NULL, $exclude_suffix = FALSE) {

    if ($type === NULL) {
        
        if ($bytes < pow(2, 10)) {
            
            $type = 'B';
        } elseif ($bytes < pow(2, 20)) {
            
            $type = 'K';
        } elseif ($bytes < pow(2, 30)) {
            
            $type = 'M';
        } else {
            
            $type = 'G';
        }
    }
    
    $type = strtoupper($type);
    
    $value = $bytes;
    
    $suffix = 'bytes';
    
    $prec = 0;
    
    switch ($type) {
        
        case 'K' :
            $value = $bytes / pow(2, 10);
            
            $suffix = 'KB';
            
            break;
        
        case 'M' :
            $value = $bytes / pow(2, 20);
            
            $suffix = 'MB';
            
            $prec = 2;
            
            break;
        
        case 'G' :
            $value = $bytes / pow(2, 30);
            
            $suffix = 'GB';
            
            $prec = 2;
            
            break;
        
        case 'T' :
            $value = $bytes / pow(2, 40);
            
            $suffix = 'TB';
            
            $prec = 2;
            
            break;
    }
    
    if ($precision !== NULL)
        $prec = $precision;
    
    return number_format($value, $prec) . ($exclude_suffix ? '' : ' ' . $suffix);

}

/**
 * @brief String to bytes formatter
 *
 * @detail Returns an integer value representing a number of bytes from the standard bytes size string supplied.
 *
 * @since 1.0.0
 *       
 * @param int $string
 *            The byte string value to convert to an integer. eg: '100MB'
 *            
 * @return int The number of bytes
 */
function bytes_str($string) {

    if (preg_match('/([\d\.]+)\s*(\w*)/', $string, $matches)) {
        
        $size = floatval($matches[1]);
        
        switch (strtoupper($matches[2])) {
            case 'K' :
            case 'KB' :
                $size = round($size * pow(2, 10));
                break;
            case 'M' :
            case 'MB' :
                $size = round($size * pow(2, 20));
                break;
            case 'G' :
            case 'GB' :
                
                $size = round($size * pow(2, 30));
                break;
            case 'T' :
            case 'TB' :
                $size = round($size * pow(2, 40));
                break;
        }
        
        return $size;
    }
    
    return FALSE;

}

/**
 * @brief Convert a string interval to seconds
 *
 * @detail This function can be used to convert a string interval such as '1 week' into seconds. Currently
 * supported intervals are seconds, minutes, hours, days and weeks. Months are not supported because
 * some crazy crackpot decided to make them all different lengths, so without knowing which month we're
 * talking about, converting them to seconds is impossible.
 *
 * Multiple interval support is also possible. Intervals can be separated with a comma (,) or the word
 * 'and', for example:
 *
 * <pre><code class="php">
 * $foo = seconds('1 week, 2 days');
 * $bar = seconds('1 week and 2 days');
 * </code></pre>
 *
 * Both of these function calls will yeild the same result.
 *
 * @since 1.0.0
 *       
 * @param string $interval
 *            The string interval to convert to seconds
 *            
 * @return int Number of seconds in the interval
 */
function seconds($interval) {

    if (is_numeric($interval))
        return $interval;
    
    $intervals = preg_split('/(\s+and|\s*,)\s+/', $interval);
    
    $value = 0;
    
    foreach($intervals as $interval) {
        
        if (!preg_match('/(\d+)\s+(\w+)/', $interval, $matches))
            return NULL;
        
        $val = $matches[1];
        
        switch (strtolower($matches[2])) {
            
            case 'second' :
            case 'seconds' :
                $value += $val;
                
                break;
            
            case 'minutes' :
                $value += ($val * 60);
                
                break;
            
            case 'hour' :
            case 'hours' :
                $value += ($val * 60 * 60);
                break;
            
            case 'day' :
            case 'days' :
                $value += ($val * 60 * 60 * 24);
                
                break;
            
            case 'week' :
            case 'weeks' :
                $value += ($val * 60 * 60 * 24 * 7);
                
                break;
            
            case 'year' :
            case 'years' :
                $value = ($val * 60 * 60 * 24 * 365.25);
                
                break;
        }
    }
    
    return $value;

}

/**
 * @brief Convert interval to minutes
 *
 * @detail Same as the seconds function except returns the number of minutes.
 *
 * @since 1.0.0
 *       
 * @return int Minutes in interval
 */
function minutes($interval) {

    return seconds($interval) / 60;

}

/**
 * @brief Convert interval to hours
 *
 * @detail Same as the seconds function except returns the number of hours.
 *
 * @since 1.0.0
 *       
 * @return int Hours in interval
 */
function hours($interval) {

    return minutes($interval) / 60;

}

/**
 * @brief Convert interval to days
 *
 * @detail Same as the seconds function except returns the number of days.
 *
 * @since 1.0.0
 *       
 * @return int Days in interval
 */
function days($interval) {

    return hours($interval) / 24;

}

/**
 * @brief Convert interval to weeks
 *
 * @detail Same as the seconds function except returns the number of weeks.
 *
 * @since 1.0.0
 *       
 * @return int Weeks in interval
 */
function weeks($interval) {

    return days($interval) / 7;

}

/**
 * @brief Convert interval to years
 *
 * @detail Same as the seconds function except returns the number of years.
 *
 * @since 1.0.0
 *       
 * @return int Years in interval
 */
function years($interval) {

    return days($interval) / 365.25;

}

/**
 * @brief Get the age of a date.
 *
 * @detail This helper function will return the number of years between a specified date and now. Useful for
 * getting an age.
 *
 * @since 1.0.0
 *       
 *        $retval int Number of years from the specified date to now.
 */
function age($date) {

    return years(time() - strtotime($date));

}

/**
 * @brief Convert interval to uptime string
 *
 * @detail This function will convert an integer of seconds into an uptime string similar to what is returned by
 * the unix uptime command. ie: '9 days 3:32:02'
 *
 * @since 1.0.0
 *       
 * @return int Minutes in interval
 */
function uptime($seconds) {

    $interval = $seconds . ' seconds';
    
    $d = floor(days($interval));
    
    $h = floor(hours($interval)) - ($d * 24);
    
    $m = floor(minutes($interval)) - (($h + ($d * 24)) * 60);
    
    $s = floor(seconds($interval)) - ((($m + ($h + ($d * 24)) * 60)) * 60);
    
    $o = '';
    
    if ($d == 1)
        $o .= "$d day ";
    elseif ($d > 1)
        $o .= "$d days ";
    
    $o .= $h . ':' . str_pad($m, 2, '0', STR_PAD_LEFT) . ':' . str_pad($s, 2, '0', STR_PAD_LEFT);
    
    return $o;

}

/**
 * @brief Fix a numeric string
 *
 * @detail Sometimes a numeric (int or float) will be stored as a string variable. This can cause
 * issues with functions that check the variable type to determine what to do with it. This
 * function allows you to simply pass a variable through it and it will 'fix' it. If it is
 * a string and is a numeric value, it will be converted to the appropriate variable type.
 *
 * If the value is a string and is meant to be a string, it will be left alone.
 *
 * @since 1.0.0
 *       
 * @param mixed $value
 *            The variable to type check and possibly fix.
 *            
 * @return mixed The fixed variable.
 */
function str_fixtype(&$value) {

    if (is_numeric($value)) {
        
        /*
         * NOTE: We can't use settype() here as it is unable to distinguish between a float and an int. So we
         * use this trick to force PHP to figure it out for itself. Adding zero will not affect the value
         * but will cause the type to be converted to int or float.
         */
        
        $value = $value + 0;
    }
    
    return $value;

}

/**
 * Helper function to get the status text for an HTTP response code
 *
 * @param integer $code
 *            The response code.
 *            
 * @return mixed A string containing the response text if the code is valid. False otherwise.
 */
function http_response_text($code) {

    $text = FALSE;
    
    if ($file = \Hazaar\Loader::getFilePath(FILE_PATH_SUPPORT, 'Http_Status.dat')) {
        
        if (preg_match('/^' . $code . '\s(.*)$/m', file_get_contents($file), $matches)) {
            
            $text = $matches[1];
        }
    }
    
    return $text;

}

if (!function_exists('getallheaders')) {

    function getallheaders() {

        if (!is_array($_SERVER))
            return array();
        
        $headers = array();
        foreach($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    
    }
}

// apache_request_headers replicement for nginx
if (!function_exists('apache_request_headers')) {

    function apache_request_headers() {

        return getallheaders();
    
    }
}

if (!function_exists('http_response_code')) {

    function http_response_code($code = NULL) {

        if ($code) {
            
            if ($text = http_response_text($code)) {
                
                header($_SERVER['SERVER_PROTOCOL'] . ' ' . $code . ' ' . $matches[1]);
                
                $_SERVER['HTTP_RESPONSE_CODE'] = $code;
                
                return TRUE;
            } else {
                
                die('Missing Http_Status.dat file!');
            }
        } else {
            
            return (array_key_exists('HTTP_RESPONSE_CODE', $_SERVER) ? $_SERVER['HTTP_RESPONSE_CODE'] : 200);
        }
        
        return FALSE;
    
    }
}

function guid() {

    $format = 'xxxxxxxx-yxxx-yxxx-yxxx-xxxxxxxxxxxx';
    
    $value = preg_replace_callback('/[xy]/', function ($c) {
        
        $num = rand(0, 15);
        $value = (($c[0] == 'x') ? $num : ($num & 0x3 | 0x8));
        
        return dechex($value);
    }, $format);
    
    return $value;

}

function dump($data = NULL) {

    if (function_exists('apache_request_headers')) {
        
        $h = apache_request_headers();
        
        if (ake($h, 'X-Requested-With') == 'XMLHttpRequest') {
            
            $dump = array(
                'data' => $data,
                'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
            );
            
            header('Content-Type: application/javascript');
            
            echo json_encode($dump);
            
            exit();
        }
    }
    
    if (getenv('HAZAAR_SID')) {
        
        echo "Hazaar Dump:\n\n";
        
        var_dump($data);
        
        echo "\n\n";
        
        debug_print_backtrace();
    } else {
        
        $style = "<style>
    body { padding: 0; margin: 0; }
    h2 { background: #554D7C; color: #fff; padding: 15px; }
    pre { margin: 30px; }
    </style>";
        
        echo "<html>\n\n<head>\n\t<title>Hazaar Dump</title>\n$style</head>\n\n<body>\n\n";
        
        echo "<h2>Dump</h2>\n\n";
        
        var_dump($data);
        
        echo "\n\n<h2>Backtrace</h2>\n\n<pre>\n";
        
        $e = new Exception();
        
        print_r(str_replace('/path/to/code/', '', $e->getTraceAsString()));
        
        echo "\n</pre>\n\n</body>\n\n</html>\n\n";
    }
    
    exit();

}

function preg_match_array($patterns, $subject, &$matches = NULL, $flags = 0, $offset = 0) {

    if (!is_array($patterns))
        return FALSE;
    
    foreach($patterns as $pattern) {
        
        if (preg_match($pattern, $subject, $matches, $flags, $offset))
            return 1;
    }
    
    return 0;

}
