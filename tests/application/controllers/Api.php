<?php

namespace Application\Controllers;

use Hazaar\Controller\REST;

/**
 * @internal
 */
class Api extends REST
{
    public function testGET(?int $id = null, string $word = 'default'): mixed
    {
        return ['ok' => true, 'message' => 'success', 'id' => $id, 'word' => $word];
    }
}
