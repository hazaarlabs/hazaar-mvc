<?php
/**
 * @file        Hazaar/Closure.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar;

/**
 * @brief       Closure Class
 *
 * @since       1.0.0
 */
class Closure implements \JsonSerializable {

    protected $closure;

    protected $reflection;

    private $code;

    function __construct($function = NULL) {

        if($function instanceof \Closure) {

            $this->closure = $function;

            $this->reflection = new \ReflectionFunction($function);

            $this->code = $this->_fetchCode();

        }elseif($function instanceof \stdClass && isset($function->code)){

            $this->code = $function->code;

            eval('$function = ' . rtrim($function->code, ' ;') . ';');

            $this->closure = $function;

            $this->reflection = new \ReflectionFunction($function);

        }
        
    }

    public function __invoke() {

        $args = func_get_args();

        return $this->reflection->invokeArgs($args);

    }

    public function getClosure() {

        return $this->closure;

    }

    protected function _fetchCode() {

        $file = new \SplFileObject($this->reflection->getFileName());

        $file->seek($this->reflection->getStartLine() - 1);

        $code = '';

        while($file->key() < $this->reflection->getEndLine()) {

            $code .= $file->current();

            $file->next();

        }

        // Only keep the code defining that closure
        $begin = strpos($code, 'function');

        $end = strrpos($code, '}');

        return substr($code, $begin, $end - $begin + 1);

    }

    public function getCode() {

        return $this->code;

    }

    public function loadCodeFromString($string) {

        $this->code = $string;

        $this->__wakeup();

    }

    public function __toString() {

        return $this->getCode();

    }

    public function getParameters() {

        return $this->reflection->getParameters();

    }

    public function __sleep() {

        return ['code'];

    }

    public function __wakeup() {

        eval('$_function = ' . $this->code . ';');

        if(isset($_function) && $_function instanceof \Closure) {

            $this->closure = $_function;

            $this->reflection = new \ReflectionFunction($_function);

        } else {

            throw new \Hazaar\Exception('Bad code: ' . $this->code);

        }

    }

    public function jsonSerialize() : mixed {

        return [
            'code' => $this->code
        ];

    }

}
