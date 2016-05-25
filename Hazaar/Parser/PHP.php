<?php

namespace Hazaar\Parser;

class PHP {

    private $source;

    private $size = 0;

    private $comment = null;

    private $functions = array();

    private $namespaces = array();

    private $interfaces = array();

    private $classes = array();

    private $db_parser = null;

    public function __construct($filename, $parse_docblocks = false) {

        if(file_exists($filename)) {

            if($parse_docblocks) {

                $this->db_parser = new DocBlock();

            }

            $tokens = token_get_all(file_get_contents($filename));

            $array = $this->fixTokenArray($tokens);

            $this->source = realpath($filename);

            $this->size = filesize($filename);

            $current_namespace = null;

            while($token = next($array)) {

                if(is_array($token)) {

                    switch($token['type']) {
                        case T_FUNCTION :
                            $this->functions[] = $this->parseFunction($array, false, $current_namespace['name']);
                            break;

                        case T_NAMESPACE :
                            $this->namespaces[] = $current_namespace = $this->parseNamespace($array);

                            break;

                        case T_INTERFACE :
                            $this->interfaces[] = $this->parseClass($array, $current_namespace['name']);

                            break;

                        case T_CLASS :
                            $this->classes[] = $this->parseClass($array, $current_namespace['name']);

                            break;

                        case T_DOC_COMMENT :
                            if($this->db_parser instanceof DocBlock) {

                                $this->db_parser->setComment($token['value']);

                                if(array_key_exists('file', $this->db_parser->tags))
                                    $this->comment = $this->db_parser->toArray();

                            } else {

                                $this->comment = $token['value'];

                            }

                            break;
                    }

                }

            }

        }

    }

    public function getInfo() {

        $info = array(
            'source' => $this->source,
            'size' => $this->size,
            'comment' => $this->comment,
            'functions' => $this->functions,
            'namespaces' => $this->namespaces,
            'interfaces' => $this->interfaces,
            'classes' => $this->classes
        );

        return $info;

    }

    private function fixTokenArray(&$ta) {

        $ar = array();

        while(list($key, $val) = each($ta)) {

            if(is_array($val) && $val[0] != T_WHITESPACE) {

                $ar[] = array(
                    'type' => $val[0],
                    'type_name' => token_name($val[0]),
                    'value' => $val[1],
                    'line' => $val[2]
                );

            } elseif($val == '{' || $val == '}' || $val == ';' || $val == ',') {

                $ar[] = $val;

            }

        }

        return $ar;

    }

    private function getTypedValue(&$ar, $prev_after = true) {

        $token = current($ar);

        $value = null;

        if($token['type'] == T_CONSTANT_ENCAPSED_STRING) {

            $value = trim($token['value'], "'");

        } elseif($token['type'] == T_LNUMBER) {

            $value = (int)$token['value'];

        } elseif($token['type'] == T_DNUMBER) {

            $value = (float)$token['value'];

        } elseif($token['type'] == T_ARRAY) {

            $value = array();

            while($token = next($ar)) {

                if(is_array($token)) {

                    if($token['type'] == T_CONSTANT_ENCAPSED_STRING || $token['type'] == T_DNUMBER || $token['type'] == T_LNUMBER) {

                        $key = $this->getTypedValue($ar, false);

                        $token = next($ar);

                        if(is_array($token) && $token['type'] == T_DOUBLE_ARROW) {

                            $token = next($ar);

                            $value[$key] = $this->getTypedValue($ar, false);

                        } else {

                            prev($ar);

                            $value[] = $this->getTypedValue($ar);

                        }

                    } elseif($token['type'] == T_ARRAY) {

                        $value[] = $this->getTypedValue($ar);

                    } else {

                        break;

                    }

                } else {

                    break;

                }

            }

            prev($ar);

        } elseif($token['type'] == T_STRING) {

            $value = strtolower($token['value']);

            if($value == 'false')
                $value = false;
            elseif($value == 'true')
                $value = true;
            elseif($value == 'null')
                $value = null;
            elseif($value == 'array')
                $value = array();

        } elseif($prev_after) {

            prev($ar);

        }

        return $value;

    }

    private function checkDocComment(&$ar, $double_jump = false) {

        $doc = null;

        if($double_jump)
            prev($ar);

        /**
         * Peak at the previous token to see if it is a comment and if so return the comment.
         */
        if($token = prev($ar)) {

            if(is_array($token) && $token['type'] == T_DOC_COMMENT) {

                if($this->db_parser instanceof DocBlock) {

                    $this->db_parser->setComment($token['value']);

                    if(!array_key_exists('file', $this->db_parser)) {

                        $doc = $this->db_parser->toArray();

                        unset($doc['comment']);

                    }

                } else {

                    $doc = $token['value'];

                }

            }

            next($ar);

        }

        if($double_jump)
            next($ar);

        return $doc;

    }

    private function parseNamespace(&$ar) {

        $token = current($ar);

        if($token['type'] == T_NAMESPACE) {

            $namespace = array(
                'name' => array(),
                'line' => $token['line']
            );

            if($comment = $this->checkDocComment($ar)) {

                $namespace['comment'] = $comment;

            }

            while($token = next($ar)) {

                if(is_array($token)) {

                    if($token['type'] == T_NS_SEPARATOR)
                        continue;

                    $namespace['name'][] = $token['value'];

                } elseif($token == ';') {

                    return $namespace;

                }

            }

        }

        return null;

    }

    private function parseConstant(&$ar) {

        $token = current($ar);

        if($token['type'] == T_CONST) {

            $token = next($ar);

            $const = array(
                'name' => $token['value'],
                'line' => $token['line']
            );

            if($comment = $this->checkDocComment($ar, true)) {

                $const['comment'] = $comment;

            }

            $token = next($ar);

            $const['value'] = $this->getTypedValue($ar, false);

            return $const;

        }

        return null;

    }

    private function parseProperty(&$ar) {

        $token = current($ar);

        if($token['type'] == T_VARIABLE) {

            $prop = array(
                'line' => $token['line'],
                'static' => false
            );

            $count = 0;

            while($token = prev($ar)) {

                if(!is_array($token))
                    break;

                if($token['type'] == T_PRIVATE || $token['type'] == T_PUBLIC || $token['type'] == T_PROTECTED) {

                    $prop['type'] = $token['value'];

                } elseif($token['type'] == T_STATIC) {

                    $prop['static'] = true;

                } else {

                    break;

                }

                $count++;

            }

            for($i = 0; $i < $count; $i++)
                next($ar);

            if($comment = $this->checkDocComment($ar, ($count > 1))) {

                $prop['comment'] = $comment;

            }

            $token = next($ar);

            if($token['type'] == T_VARIABLE) {

                $prop['name'] = $token['value'];

            }

            $token = next($ar);

            if(is_array($token)) {

                $prop['value'] = $this->getTypedValue($ar);

            }

            return $prop;

        }

        return null;

    }

    private function parseFunction(&$ar, $ns = null) {

        $token = current($ar);

        if($token['type'] == T_FUNCTION) {

            $func = array(
                'static' => false,
                'line' => $token['line']
            );

            if(is_array($ns))
                $func['namespace'] = $ns;

            $count = 0;

            while($token = prev($ar)) {

                if(!is_array($token))
                    break;

                if($token['type'] == T_PRIVATE || $token['type'] == T_PUBLIC || $token['type'] == T_PROTECTED) {

                    $func['type'] = $token['value'];

                } elseif($token['type'] == T_STATIC) {

                    $func['static'] = true;

                } else {

                    if($count == 0)
                        $count++;

                    break;

                }

                $count++;

            }

            for($i = 0; $i < $count; $i++)
                next($ar);

            if($comment = $this->checkDocComment($ar, ($count > 1))) {

                $func['comment'] = $comment;

            }

            $token = next($ar);

            if($token['type'] == T_FUNCTION)
                $token = next($ar);

            if($token['type'] == T_STRING) {

                $func['name'] = $token['value'];

            }

            $depth = 0;

            $type = null;

            $p_token = null;

            while($token = next($ar)) {

                if(!is_array($token) || $token['type'] == T_CURLY_OPEN) {

                    /**
                     * The T_CURLY_OPEN is a hack because PHP decided to treat open curly braces different depending on
                     * whether they are in a string constant or not.  This wouldn't normally be a problem, except that
                     * the close brace is ALWAYS treated the same.
                     */
                    if($token == '{' || (is_array($token) && $token['type'] == T_CURLY_OPEN)) {

                        $depth++;

                    } elseif($token == '}') {

                        $depth--;

                        if($depth == 0)
                            break;

                    } elseif($depth == 0 && $token == ';') {

                        return $func;

                    }

                } elseif($depth == 0) {

                    if($token['type'] == T_VARIABLE) {

                        $param = array('name' => $token['value']);

                        if($type)
                            $param['type'] = $type;

                        $token = next($ar);

                        if(is_array($token)) {

                            if($token['type'] != T_VARIABLE) {

                                $param['value'] = $this->getTypedValue($ar);

                            } else {

                                prev($ar);

                            }

                        } else {

                            prev($ar);

                        }

                        $func['params'][] = $param;

                        $type = null;

                    } else {

                        $type .= $token['value'];

                    }

                }

                $p_token = $token;

            }

            return $func;

        }

        return null;

    }

    private function parseClass(&$ar, $ns = null) {

        $token = current($ar);

        if($token['type'] == T_CLASS || $token['type'] == T_INTERFACE) {

            $class_info = array(
                'line' => $token['line'],
                'abstract' => false
            );

            $token = prev($ar);

            if(is_array($token) && $token['type'] == T_ABSTRACT) {

                $class_info['abstract'] = true;

            }

            $token = next($ar);

            if($comment = $this->checkDocComment($ar, $class_info['abstract'])) {

                $class_info['comment'] = $comment;

            }

            if(is_array($ns))
                $class_info['namespace'] = $ns;

            prev($ar);

            while($token = next($ar)) {

                if(!is_array($token)) {

                    if($token == '}')
                        break;

                } else {

                    switch($token['type']) {
                        case T_INTERFACE :
                        case T_CLASS :
                            $token = next($ar);

                            $class_info['name'] = $token['value'];

                            break;

                        case T_EXTENDS :
                            $extends = '';

                            while($token = next($ar)) {

                                if(!is_array($token) || !in_array($token['type'], array(
                                        T_NS_SEPARATOR,
                                        T_STRING
                                    )))
                                    break;

                                $extends .= $token['value'];

                            }

                            prev($ar);

                            $class_info['extends'] = $extends;

                            break;

                        case T_IMPLEMENTS :
                            $implements = '';

                            while($token = next($ar)) {

                                if($token == ',') {

                                    $class_info['implements'][] = $implements;

                                    $implements = '';

                                    continue;

                                } elseif(!is_array($token) || !in_array($token['type'], array(
                                        T_NS_SEPARATOR,
                                        T_STRING
                                    )))
                                    break;

                                $implements .= $token['value'];

                            }

                            prev($ar);

                            $class_info['implements'][] = $implements;

                            break;

                        case T_VARIABLE :
                            $prop = $this->parseProperty($ar);

                            $class_info['properties'][] = $prop;

                            break;

                        case T_FUNCTION :
                            $func = $this->parseFunction($ar);

                            $class_info['methods'][] = $func;

                            break;

                        case T_CONST :
                            $const = $this->parseConstant($ar);

                            $class_info['constants'][] = $const;

                            break;
                    }

                }

            }

            return $class_info;

        }

        return null;

    }

}