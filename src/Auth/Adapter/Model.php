<?php

declare(strict_types=1);

namespace Hazaar\Auth\Adapter;

use Hazaar\Auth\Adapter;

abstract class Model extends Adapter
{
    /**
     * Create a new user record.
     *
     * @param string              $identity   The user identity/username
     * @param string              $credential The user credential/password
     * @param array<string,mixed> $data       Additional data to store with the user record
     */
    public function create(string $identity, string $credential, array $data = []): bool
    {
        return false;
    }

    /**
     * Update a user record.
     *
     * Use this method to set the user's password and/or update any additional data stored with the user record.
     *
     * @param string              $identity The user identity/username
     * @param array<string,mixed> $data     The data to update
     */
    public function update(string $identity, ?string $credential = null, array $data = []): bool
    {
        return false;
    }

    /**
     * Delete a user record.
     *
     * @param string $identity The user identity/username
     */
    public function delete(string $identity): bool
    {
        return false;
    }
}
