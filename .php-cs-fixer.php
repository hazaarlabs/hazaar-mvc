<?php

use PhpCsFixer\Config;

return (new Config())
    ->setRules([
        '@PhpCsFixer' => true,
        'php_unit_test_class_requires_covers' => false,
        'nullable_type_declaration_for_default_null_value' => true,
    ])
;
