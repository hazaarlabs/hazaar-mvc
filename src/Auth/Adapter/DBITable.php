<?php

declare(strict_types=1);

namespace Hazaar\Auth\Adapter;

use Hazaar\Auth\Adapter;
use Hazaar\DBI\Adapter as DBIAdapter;
use Hazaar\Map;

class DBITable extends Adapter implements \Hazaar\Auth\Interfaces\Adapter
{
    private DBIAdapter $db;
    private string $table;
    private string $field_identity;
    private string $field_credential;
    private string $field_id;

    /**
     * Construct the new authentication object with the field names
     * in the model for id, user name, password and real name.
     *
     * @param array<mixed>|Map $cacheConfig The configuration options
     */
    public function __construct(
        DBIAdapter $db_adapter,
        ?string $table = null,
        ?string $field_identity = null,
        ?string $field_credential = null,
        ?string $field_id = 'id',
        array|Map $cacheConfig = []
    ) {
        $this->table = $table;
        $this->field_identity = $field_identity;
        $this->field_credential = $field_credential;
        $this->field_id = $field_id;
        $this->db = $db_adapter;
        parent::__construct($cacheConfig);
    }

    public function setTableName(string $table): void
    {
        $this->table = $table;
    }

    public function setIdentityField(string $identity): void
    {
        $this->field_identity = $identity;
    }

    public function setCredentialField(string $credential): void
    {
        $this->field_credential = $credential;
    }

    /*
     * We override the authenticate method from base.  This uses the
     * required values from the parent object to get the credentials
     * and perform the authentication.
     *
     * @param string $identity The user identity to authenticate
     * @param array<mixed> $extra Extra data to return with the authentication
     *
     * @return array<string, mixed>|bool The authentication data
     */
    public function queryAuth(string $identity, array $extra = []): array|bool
    {
        if (!$this->field_identity) {
            throw new \Exception('Identity field not set in '.get_class($this).'. This is required.');
        }
        if (!$this->field_credential) {
            throw new \Exception('Credential field not set in '.get_class($this).'. This is required.');
        }
        $fields = [$this->field_id, $this->field_identity, $this->field_credential];
        if (is_array($extra)) {
            $fields = array_merge($fields, $extra);
        }
        $criteria = [$this->field_identity => $identity];
        if (!($record = $this->db->table($this->table)->findOne($criteria, $fields))) {
            return false;
        }
        $auth = [
            'identity' => $record[$this->field_identity],
            'credential' => $record[$this->field_credential],
        ];
        unset($record[$this->field_identity], $record[$this->field_credential]);
        $auth['data'] = $record;

        return $auth;
    }

    public function addUser(string $identity, string $credential): bool|int
    {
        return $this->db->table($this->table)->insert([
            $this->field_identity => $identity,
            $this->field_credential => $this->getCredential($credential),
        ]);
    }

    public function delUser(string $identity): bool|int
    {
        return $this->db->table($this->table)->delete([
            $this->field_identity => $identity,
        ]);
    }
}
