<?php

namespace Hazaar\Parser;

require_once (SUPPORT_PATH . '/text/classTextile.php');

/**
 * The Textile text parser (wrapper)
 * 
 * This is a wrapper for the popular classTextile script that merely makes it easier to implement textile parsing in your Hazaar apps.
 * 
 * For details on the methods available in this class, see: https://github.com/textile/php-textile/blob/v2.5.4/classTextile.php
 * 
 * Example Usage:
 * 
 * <pre><code class="php">
 * $textile = new \Hazaar\Parser\Textile();
 * 
 * $output = $textile->textileThis($yourTextileContent);
 * </code></pre>
 *
 */
class Textile extends \Textile {
    
}