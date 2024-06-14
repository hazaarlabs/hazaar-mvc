<?php

namespace Hazaar\Mail\Transport;

use Hazaar\Mail\Transport;
use Hazaar\Mail\Mime\Message;

class Local extends Transport
{
    public function send(
        array $recipients,
        array $from,
        ?string $subject = null,
        $message = null,
        array $headers = [],
        array $attachments = []
    ): bool {
        $mailHeaders = [];

        if ($message instanceof Message && is_array($attachments) && count($attachments) > 0) {
            $message = $message->addParts($attachments);
        }

        foreach($recipients as $type => $addresses) {
            foreach($addresses as $address) {
                $headers[ucfirst($type)][] = self::encodeEmailAddress($address[0], ake($address, 1));
            }
        }

        $headers['From'] = self::encodeEmailAddress($from[0], ake($from, 1));

        foreach ($headers as $key => $value) {
            if (!$value) {
                continue;
            }
            if(is_array($value)){
                foreach($value as $v){
                    $mailHeaders[] = $key.':   '.trim($v);
                }
            }else{
                $mailHeaders[] = $key.':   '.trim($value);
            }
        }

        $mailHeaders = implode("\n", $mailHeaders);

        $params = [
            '-R' => 'hdrs',
            '-f' => $from[0]
        ];

        if (is_array($this->dsn) && count($this->dsn) > 0) {
            $params['-N'] = '"'.implode(',', array_map('strtolower', $this->dsn)).'"';
        }
        
        // The @ sign causes errors not to be thrown and allows things to continue.  the mail() command
        // will just return false when not successful.
        $ret = @mail($this->formatTo($recipients['to']), $subject, (string)$message, $mailHeaders, count($params) > 0 ? array_flatten($params, ' ', ' ') : null);

        if (!$ret) {
            $error = error_get_last();

            throw new Exception\FailConnect(ake($error, 'message'), ake($error, 'type'));
        }

        return $ret;
    }

    private function formatTo($tolist)
    {
        return implode(', ', array_map(function($item){return $item[0];}, $tolist));
    }
}
