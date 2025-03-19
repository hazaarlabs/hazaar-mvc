<?php

declare(strict_types=1);

namespace Hazaar\Logger\Backend;

use Hazaar\Logger\Backend;

class Chain extends Backend
{
    /**
     * @var array<Backend>
     */
    private array $backends = [];

    public function init(): void
    {
        $this->setDefaultOption('chain', ['backend' => ['file']]);
        $chain = $this->getOption('chain');
        if (is_array($chain['backend'])) {
            foreach ($chain['backend'] as $backendName) {
                $backendClass = 'Hazaar_Logger_Backend_'.ucfirst($backendName);
                $backend = new $backendClass([]);
                $this->backends[] = $backend;
                foreach ($backend->getCapabilities() as $capability) {
                    $this->addCapability($capability);
                }
            }
        }
    }

    public function postRun(): void
    {
        foreach ($this->backends as $backend) {
            $backend->postRun();
        }
    }

    public function write(string $message, int $level = LOG_INFO, ?string $tag = null): void
    {
        foreach ($this->backends as $backend) {
            $backend->write($message, $level, $tag);
        }
    }

    public function trace(): void
    {
        foreach ($this->backends as $backend) {
            if ($backend->can('write_trace')) {
                $backend->trace();
            }
        }
    }
}
