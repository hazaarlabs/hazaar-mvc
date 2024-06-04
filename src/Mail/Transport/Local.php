<?php

namespace Hazaar\Mail\Transport;

use Hazaar\Mail\Transport;
use Hazaar\Mail\Mime\Message;

class Local extends Transport
{
    public function send(
        array $to,
        ?string $subject = null,
        $message = null,
        array $headers = [],
        array $attachments = []
    ): bool {
        $sendmailFrom = '';

        $mailHeaders = [];

        if ($message instanceof Message && is_array($attachments) && count($attachments) > 0) {
            $message = $message->addParts($attachments);
        }
        
        foreach ($headers as $key => $value) {
            if (!$value) {
                continue;
            }

            if ('from' == strtolower($key)) {
                /*
                 * If this is the from header, try and extract the email address to use in sendmail -f.
                 * Sometimes emails will not send correctly if this fails
                 */
                if (preg_match('/[\w\s]*\<(.*)\>/', $value, $matches)) {
                    $sendmailFrom = $matches[1];
                } else {
                    $sendmailFrom = $value;
                }
            }

            $mailHeaders[] = $key.':   '.trim($value);
        }

        $mailHeaders = implode("\n", $mailHeaders);

        $params = [
            '-R' => 'hdrs',
        ];

        if ($sendmailFrom) {
            $params['-f'] = $sendmailFrom;
        }

        if (is_array($this->dsn) && count($this->dsn) > 0) {
            $params['-N'] = '"'.implode(',', array_map('strtolower', $this->dsn)).'"';
        }

        // The @ sign causes errors not to be thrown and allows things to continue.  the mail() command
        // will just return false when not successful.
        $ret = @mail($this->formatTo($to), $subject, (string)$message, $mailHeaders, count($params) > 0 ? array_flatten($params, ' ', ' ') : null);

        if (!$ret) {
            $error = error_get_last();

            throw new Exception\FailConnect(ake($error, 'message'), ake($error, 'type'));
        }

        return $ret;
    }

    private function formatTo($tolist)
    {
        return implode(', ', $tolist);
    }
}
