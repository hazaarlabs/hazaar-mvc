<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Application/Request/Http.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Application\Request;

use Hazaar\Application\Request;
use Hazaar\Controller\Response;
use Hazaar\HTTP\Client;
use Hazaar\Loader;
use Hazaar\Session;

/**
 * @brief       Controller HTTP Request Class
 *
 * @detail      The HTTP controller request class is a representational object for an HTTP request.  The Application
 *              object will create a HTTP request object upon each execution.  This object contains all details of
 *              the current request including request data, headers and any request body content.
 *
 *              If you want to generate your own HTTP request object to pass to another method or function that requires
 *              one, see [[Hazaar\Http\Request]].
 */
class HTTP extends Request
{
    public static string $pathParam = 'hzpath';
    public static string $queryParam = 'hzqs';

    /**
     * Request body.  This is only used in certain circumstances such as with XML-RPC or REST.
     */
    public string $body = '';

    /**
     * Array of headers, one line per element.
     *
     * @var array<mixed>
     */
    private array $headers = [];

    /**
     * @detail      The HTTP init method takes only a single optional argument which is the
     *              request array provided by PHP ($_REQUEST).
     *
     *              The constructor will also get all the request headers and the request content and
     *              from there will use the [[Hazaar\Application\Request]] parent class to determine the
     *              name of the Controller and Action that is being requested via it's evaluate() method.
     *
     * @param array<mixed> $request Optional reference to $_REQUEST
     */
    public function init(?array $server, ?array $request = null, bool $processRequestBody = false): string
    {
        $this->method = ake($server, 'REQUEST_METHOD', 'GET');
        if (function_exists('getallheaders')) {
            $this->headers = getallheaders();
        }
        if (true === $processRequestBody) {
            $this->body = @file_get_contents('php://input');
        }
        $encryptionHeader = ucwords(strtolower(Client::$encryptionHeader), '-');
        if (array_key_exists($encryptionHeader, $this->headers)) {
            $iv = base64_decode($this->headers[$encryptionHeader]);
            if (!($keyfile = Loader::getFilePath(FILE_PATH_CONFIG, '.key'))) {
                throw new \Exception('Unable to encrypt.  No key provided and no default keyfile!');
            }
            Response::$encryptionKey = trim(file_get_contents($keyfile));
            $this->body = openssl_decrypt(
                base64_decode($this->body),
                Client::$encryptionDefaultCipher,
                Response::$encryptionKey,
                OPENSSL_RAW_DATA,
                $iv
            );
            if (false === $this->body) {
                throw new \Exception('Received an encrypted request but was unable to decrypt the body!', 500);
            }
        }
        $contentType = explode(';', $this->getHeader('Content-Type'));
        if ($this->body && !empty($contentType[0])) {
            switch ($contentType[0]) {
                case 'text/json':
                case 'application/json':
                case 'application/javascript':
                case 'application/x-javascript':
                    if ($json_body = json_decode($this->body, true)) {
                        $request = array_merge($request, is_array($json_body) ? $json_body : [$json_body]);
                    }

                    break;

                case 'text/html':
                case 'application/x-www-form-urlencoded':
                    parse_str($this->body, $params);
                    $request = array_merge($request, $params);

                    break;
            }
        }
        if (is_array($request) && count($request) > 0) {
            $this->setParams($request);
        }
        if (array_key_exists(HTTP::$queryParam, $this->params)) {
            parse_str(base64_decode($this->params[HTTP::$queryParam]), $params);
            $this->params = array_merge($this->params, $params);
            unset($this->params[HTTP::$queryParam]);
        }
        if (array_key_exists(HTTP::$pathParam, $this->params)) {
            return trim($this->params[HTTP::$pathParam], '/');
        }
        $requestURI = urldecode(ake($server, 'REQUEST_URI', '/'));
        // Figure out the PHP environment variables to use to find the controller that's being called
        if ($pos = strpos($requestURI, '?')) {
            $requestURI = substr($requestURI, 0, $pos);
        }
        $path = pathinfo(ake($server, 'SCRIPT_NAME', ''));
        if ('index.php' === $path['basename']) {
            // If we are hosted in a sub-directory we need to rip off the base dir to find our relative target
            if (($len = strlen($path['dirname'])) > 1) {
                $requestURI = substr($requestURI, $len);
            }
        }

        return substr($requestURI, 1);
    }

    /**
     * @detail      Test if the request method is GET.  This is a convenience method for quickly determining the
     *              request method.
     *
     * @return bool True if method is GET.  False otherwise.
     */
    public function isGet(): bool
    {
        return 'GET' == $this->method;
    }

    /**
     * @detail      Test if the request method is PUT.  This is a convenience method for quickly determining the
     *              request method.
     *
     * @return bool True if method is PUT.  False otherwise.
     */
    public function isPut(): bool
    {
        return 'PUT' == $this->method;
    }

    /**
     * @detail      Test if the request method is POST.  This is a convenience method for quickly determining the
     *              request method.
     *
     * @return bool True if method is POST.  False otherwise.
     */
    public function isPost(): bool
    {
        return 'POST' == $this->method;
    }

    /**
     * @detail      Test if the request method is DELETE.  This is a convenience method for quickly determining the
     *              request method.
     *
     * @return bool True if method is DELETE.  False otherwise.
     */
    public function isDelete(): bool
    {
        return 'DELETE' == $this->method;
    }

    /**
     * @detail      Get all the HTTP request headers sent by the client browser.
     *
     * @return array<string> an array of headers with the key as the header name and the value as the header value
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @detail      Check if a header was sent in the HTTP request.
     *
     * @param $header string The header to check
     *
     * @return bool TRUE if the header was sent
     */
    public function hasHeader(string $header): bool
    {
        return array_key_exists($header, $this->headers);
    }

    /**
     * @detail      Get a single header value
     *
     * @param $header string The header value to get
     *
     * @return string Returns the header value if it exists.  Null otherwise.
     */
    public function getHeader(string $header): string
    {
        return ake($this->headers, $header, '');
    }

    /**
     * Return the current request content type.
     *
     * This is a helpful method for doing a few things in one go as it will only return a content type
     * if the request is a POST method.  Otherwise it will safely return a FALSE value.
     *
     * @return false|string The content type of the POST request.  False if the request is not a POST request.
     */
    public function getContentType(): false|string
    {
        if ($this->isPost() || $this->isPut() || $this->isDelete()) {
            return $this->getHeader('Content-Type');
        }

        return false;
    }

    /**
     * @detail      Test if the request originated from an XMLHttpRequest object.  This object is used when sending an
     *              AJAX request from withing a JavaScript function.  All of the major JavaScript libraries (jQuery,
     *              extJS, etc) will set the X-Requested-With header to indicate that the request is an AJAX request.
     *
     *              Using this in your application will allow you to determine how to respond to the request.  For
     *              example, you might want to forgo rendering a view and instead return a JSON response.
     *
     * @return bool True to indicate the X-Requested-With is set.  False otherwise.
     */
    public function isXmlHttpRequest(): bool
    {
        if (array_key_exists('X-Requested-With', $this->headers)) {
            if ('XMLHttpRequest' == $this->headers['X-Requested-With']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @detail      Returns the URI of the page this request was redirected from.
     *
     * @return string Original request URI
     */
    public function redirectURI(): ?string
    {
        $sess = new Session();
        if ($sess->has('REDIRECT_URI') && $sess['REDIRECT_URI'] != $_SERVER['REQUEST_URI']) {
            return $sess['REDIRECT_URI'];
        }

        return null;
    }

    /**
     * @detail      Returns the body of the request.  This will normally be null unless the request is a POST or PUT.
     *
     * @return string the request body
     */
    public function getRequestBody(): string
    {
        return $this->body;
    }

    /**
     * @detail      Returns the JSON decoded body of the request.  This will normally be null unless the request is
     *              a POST or PUT and content-type is application/json.
     */
    public function getJSONBody(?bool $assoc = null, int $depth = 512): mixed
    {
        return ('application/json' === substr($this->getContentType(), 0, 16)) ? json_decode($this->body, $assoc, $depth) : null;
    }

    /**
     * Get the remote IP address of the requesting host.
     *
     * This will try to determine the correct IP to return.  By default it will return the $_SERVER['REMOTE_ADDR']
     * value, but if the connection is via a reverse proxy (such as Haproxy) then it will possibly have the standard
     * X-Forwarded-For header, so if that header exists then that value will be returned.
     */
    public static function getRemoteAddr(): ?string
    {
        $forwarded_ip = getenv('HTTP_X_FORWARDED_FOR') ?:
            getenv('HTTP_X_FORWARDED') ?:
            getenv('HTTP_FORWARDED_FOR') ?:
            getenv('HTTP_FORWARDED');
        if (!$forwarded_ip) {
            return getenv('HTTP_CLIENT_IP') ?:
                getenv('REMOTE_ADDR') ?: null;
        }
        $forwarded = explode(',', $forwarded_ip);

        return trim($forwarded[0]);
    }

    /**
     * Detect if a request originated on a mobile device.
     *
     * This method will return true to indicate that the requesting device is a mobile browser.  It uses the freely
     * available
     * script from detectmobilebrowsers.com
     *
     * @return bool true to indicate requesting device is a mobile browser, false otherwise
     */
    public function isMobileDevice(): bool
    {
        $useragent = $_SERVER['HTTP_USER_AGENT'];
        $ret = preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i', $useragent) || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($useragent, 0, 4));

        return $ret;
    }

    /**
     * Get the referer URL from the HTTP request headers.
     *
     * @return null|string the referer URL if available, null otherwise
     */
    public function referer(): ?string
    {
        return ake($_SERVER, 'HTTP_REFERER');
    }
}
