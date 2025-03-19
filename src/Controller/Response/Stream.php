<?php

declare(strict_types=1);

namespace Hazaar\Controller\Response;

use Hazaar\Controller\Response\HTTP\OK;

class Stream extends OK
{
    private string $final;
    private string $type = 's';

    /**
     * @param array<mixed>|\Exception $finalPacket
     */
    public function __construct(array|\Exception|string $finalPacket)
    {
        parent::__construct('text/plain');

        if ($finalPacket instanceof \Exception) {
            $error = [
                'ok' => false,
                'error' => [
                    'type' => $finalPacket->getCode(),
                    'status' => 'Stream Error',
                    'str' => $finalPacket->getMessage(),
                ],
            ];
            if (ini_get('display_errors')) {
                $error['error']['line'] = $finalPacket->getLine();
                $error['error']['file'] = $finalPacket->getFile();
                $error['trace'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            }
            $this->type = 'e';
            $this->final = json_encode($error);
        } elseif (is_array($finalPacket)) {
            $this->final = json_encode($finalPacket);
            $this->type = 'a';
        } else {
            $this->final = $finalPacket;
        }
    }

    public function writeoutput(): void
    {
        echo "\0".$this->type.$this->final;
        flush();
    }
}
