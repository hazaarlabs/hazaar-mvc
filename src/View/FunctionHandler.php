<?php

namespace Hazaar\View;

use Hazaar\Application\URL;
use Hazaar\Util\DateTime;
use Hazaar\View;

class FunctionHandler
{
    private View $view;

    public function __construct(View $view)
    {
        $this->view = $view;
    }

    public function layout(): string
    {
        if (!$this->view instanceof Layout) {
            return '';
        }

        return $this->view->layout();
    }

    /**
     * Generates a URL based on the provided controller, action, parameters, and absolute flag.
     *
     * @param string       $controller the name of the controller
     * @param string       $action     the name of the action
     * @param array<mixed> $params     an array of parameters to be included in the URL
     * @param bool         $absolute   determines whether the generated URL should be absolute or relative
     *
     * @return URL the generated URL
     */
    public function url(?string $controller = null, ?string $action = null, array $params = [], bool $absolute = false): URL
    {
        return $this->view->application->getURL($controller, $action, $params, $absolute);
    }

    /**
     * Returns a date string formatted to the current set date format.
     */
    public function date(DateTime|string $date = 'now'): string
    {
        if (!$date instanceof DateTime) {
            $date = new DateTime($date);
        }

        return $date->date();
    }

    /**
     * Returns a time string formatted to the current set time format.
     */
    public function time(DateTime|string $time = 'now'): string
    {
        if (!$time instanceof DateTime) {
            $time = new DateTime($time);
        }

        return $time->time();
    }

    /**
     * Return a date/time type as a timestamp string.
     *
     * This is for making it quick and easy to output consistent timestamp strings.
     */
    public static function timestamp(DateTime|string $value = 'now'): string
    {
        if (!$value instanceof DateTime) {
            $value = new DateTime($value);
        }

        return $value->timestamp();
    }

    /**
     * Return a formatted date as a string.
     *
     * @param mixed  $value  This can be practically any date type.  Either a \Hazaar\Util\DateTime object, epoch int, or even a string.
     * @param string $format Optionally specify the format to display the date.  Otherwise the current default is used.
     *
     * @return string the nicely formatted datetime string
     */
    public static function datetime(mixed $value = 'now', ?string $format = null): string
    {
        if (!$value instanceof DateTime) {
            $value = new DateTime($value);
        }
        if ($format) {
            return $value->format($format);
        }

        return $value->datetime();
    }
}
