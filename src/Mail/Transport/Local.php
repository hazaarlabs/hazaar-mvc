<?php

namespace Hazaar\Mail\Transport;

use Hazaar\Mail\Transport;
use Hazaar\Mail\TransportMessage;

class Local extends Transport
{
    /**
     * @param array<mixed> $options
     */
    public function init(array $options): bool
    {
        if (!exec('which sendmail')) {
            throw new Exception\NoSendmail();
        }

        return parent::init($options);
    }

    public function send(TransportMessage $message): mixed
    {
        $sendmailFrom = '';
        $mailHeaders = [];
        foreach ($message->headers as $key => $value) {
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
        if (count($message->dsn) > 0) {
            $params['-N'] = '"'.implode(',', array_map('strtolower', $message->dsn)).'"';
        }
        // The @ sign causes errors not to be thrown and allows things to continue.  the mail() command
        // will just return false when not successful.
        $ret = @mail($this->formatTo($message->to), $message->subject, (string) $message->content, $mailHeaders);
        if (!$ret) {
            $error = error_get_last();

            throw new Exception\FailConnect($error['message'] ?? '', $error['type'] ?? 0);
        }

        return $ret;
    }

    /**
     * @param array<mixed> $tolist
     */
    private function formatTo(array $tolist): string
    {
        foreach ($tolist as &$item) {
            if (is_array($item)) {
                $item = $item['name'] ? $item['name'].'<'.$item['email'].'>' : $item['email'];
            }
        }

        return implode(', ', $tolist);
    }
}
