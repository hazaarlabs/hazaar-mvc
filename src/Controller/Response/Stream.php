<?php

declare(strict_types=1);

namespace Hazaar\Controller\Response;

use Hazaar\Controller\Response\HTTP\OK;

class Stream extends OK
{
    private string $final;
    private string $type = 's';

    /**
     * @param array<mixed>|\Exception $final_packet
     */
    public function __construct(array|\Exception|string $final_packet)
    {
        parent::__construct('text/plain');

        if ($final_packet instanceof \Exception) {
            $error = [
                'ok' => false,
                'error' => [
                    'type' => $final_packet->getCode(),
                    'status' => 'Stream Error',
                    'str' => $final_packet->getMessage(),
                ],
            ];
            if (ini_get('display_errors')) {
                $error['error']['line'] = $final_packet->getLine();
                $error['error']['file'] = $final_packet->getFile();
                $error['trace'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            }
            $this->type = 'e';
            $this->final = json_encode($error);
        } elseif (is_array($final_packet)) {
            $this->final = json_encode($final_packet);
            $this->type = 'a';
        } else {
            $this->final = $final_packet;
        }
    }

    public function __writeoutput(): void
    {
        echo "\0".$this->type.$this->final;
        flush();
    }
}
