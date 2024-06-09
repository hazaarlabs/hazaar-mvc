<?php

declare(strict_types=1);

namespace Hazaar\Auth\Interfaces;

interface Adapter
{
    /**
     * Query the authentication adapter.
     *
     * @param string        $identity The identity
     * @param array<string> $extra    The extra data
     *
     * @return array<string, mixed>|bool The result
     */
    public function queryAuth(string $identity, array $extra = []): array|bool;
}
