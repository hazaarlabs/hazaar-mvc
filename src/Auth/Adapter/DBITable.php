<?php

declare(strict_types=1);

namespace Hazaar\Auth\Adapter;

use Hazaar\Auth\Adapter;
use Hazaar\DBI\Adapter as DBIAdapter;
use Hazaar\DBI\Table;
use Hazaar\Map;

class DBITable extends Adapter implements \Hazaar\Auth\Interfaces\Adapter
{
    private Table $table;

    /**
     * Construct the new authentication object with the field names
     * in the model for id, user name, password and real name.
     */
    public function __construct(
        DBIAdapter $dbi,
        $config = null
    ) {
        parent::__construct($config);
        $this->options->enhance([
            'table' => null,
            'fields' => [
                'identity' => 'identity',
                'credential' => 'credential',
            ],
        ]);
        $this->table = $dbi->table($this->options->get('table', 'users'));
    }

    /*
     * We override the authenticate method from base.  This uses the
     * required values from the parent object to get the credentials
     * and perform the authentication.
     *
     * @param string $identity The user identity to authenticate
     * @param array<string> $extra Extra data to return with the authentication
     *
     * @return array<string, mixed>|bool The authentication data
     */
    public function queryAuth(string $identity, array $extra = null)
    {
        $fields = array_merge($this->options->get('fields')->values(), $extra);
        $criteria = [
            $this->options['fields']['identity'] => $identity,
        ];
        if (!($record = $this->table->findOne($criteria, $fields))) {
            return false;
        }
        $auth = [
            'identity' => $record[$this->options['fields']['identity']],
            'credential' => $record[$this->options['fields']['credential']],
        ];
        unset($record[$this->options['fields']['identity']], $record[$this->options['fields']['credential']]);
        $auth['data'] = $record;

        return $auth;
    }

    public function create(string $identity, string $credential): bool
    {
        $this->setIdentity($identity);
        $result = $this->table->insert(
            [
                $this->options['fields']['identity'] => $identity,
                $this->options['fields']['credential'] => $this->getCredentialHash($credential),
            ]
        );

        if (!(is_int($result) && $result > 0)) {
            throw new \Exception(ake($this->table->errorInfo(), 2, 'Unknown error updating user password'), 1);
        }

        return true;
    }

    public function update(string $identity, string $credential): bool
    {
        $this->setIdentity($identity);
        $result = $this->table->update(
            [
                $this->options['fields']['identity'] => $identity,
            ],
            [
                $this->options['fields']['credential'] => $this->getCredentialHash($credential),
            ]
        );

        if (!(is_int($result) && $result > 0)) {
            throw new \Exception(ake($this->table->errorInfo(), 2, 'Unknown error updating user password'), 1);
        }

        return true;
    }

    public function delete(string $identity): bool
    {
        $result = $this->table->delete(
            [
                $this->options['fields']['identity'] => $identity,
            ]
        );

        if (!(is_int($result) && $result > 0)) {
            throw new \Exception(ake($this->table->errorInfo(), 2, 'Unknown error updating user password'), 1);
        }

        return true;
    }
}
