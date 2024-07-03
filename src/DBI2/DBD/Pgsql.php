<?php

declare(strict_types=1);

namespace Hazaar\DBI2\DBD;

use Hazaar\Map;

class Pgsql implements Interfaces\Driver
{
    use Traits\PDO {
        Traits\PDO::query as pdoQuery; // Alias the trait's query method to pdoQuery
    }
    use Traits\PDO\Transaction;
    use Traits\SQL;
    use Traits\SQL\Constraint;
    use Traits\SQL\Extension;
    use Traits\SQL\Index;
    use Traits\SQL\Schema;
    use Traits\SQL\Table;
    use Traits\SQL\View;
    use Traits\SQL\StoredFunction;
    use Traits\SQL\Trigger;
    use Traits\SQL\Sequence;

    /**
     * @var array<string>
     */
    public static array $dsnElements = [
        'host',
        'port',
        'dbname',
        'user',
        'password',
    ];

    public function __construct(Map $config)
    {
        $this->initQueryBuilder($config->get('schema', 'public'));
        $driverOptions = [];
        if ($config->has('options')) {
            $driverOptions = $config['options']->toArray();
            foreach ($driverOptions as $key => $value) {
                if (($constKey = constant('\PDO::'.$key)) === null) {
                    continue;
                }
                $driverOptions[$constKey] = $value;
                unset($driverOptions[$key]);
            }
        }
        $this->connect($this->mkdsn($config), $config->get('user'), $config->get('password'), $driverOptions);
    }
}
