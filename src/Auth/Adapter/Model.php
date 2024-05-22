<?php

declare(strict_types=1);

namespace Hazaar\Auth\Adapter;

use Hazaar\Cache;
use Hazaar\Map;

abstract class Model extends Session
{
    private string $field_identity;
    private string $field_credential;

    /*
     * Construct the new authentication object with the field names
     * in the model for id, user name, password and real name.
     *
     * @param array<mixed>|Map $cacheConfig The configuration options
     */
    public function __construct(
        array|Map $cacheConfig = [],
        Cache $cacheBackend = null
    ) {
        parent::__construct($cacheConfig, $cacheBackend);
        $this->init();
    }

    public function init(): void {}

    /**
     * @param array<mixed> $data
     */
    public function insert(array $data): bool
    {
        return false;
    }

    /**
     * @param array<mixed> $criteria
     */
    public function delete(array $criteria): bool
    {
        return false;
    }

    public function setIdentityField(string $identity): void
    {
        $this->field_identity = $identity;
    }

    public function setCredentialField(string $credential): void
    {
        $this->field_credential = $credential;
    }

    public function addUser(string $identity, string $credential): bool
    {
        return $this->insert([
            $this->field_identity => $identity,
            $this->field_credential => $credential,
        ]);
    }

    public function delUser(string $identity): bool
    {
        return $this->delete([$this->field_identity => $identity]);
    }
}
