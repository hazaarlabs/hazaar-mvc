<?php

namespace Hazaar\Mail\Transport;

class Local extends \Hazaar\Mail\Transport {

    public function send($to, $subject = NULL, $message = NULL, $headers = array(), $dsn_types = array()) {

        $sendmail_from = '';

        $mail_headers = array();

        foreach($headers as $key => $value) {

            if(!$value)
                continue;

            if(strtolower($key) == 'from') {

                /*
                 * If this is the from header, try and extract the email address to use in sendmail -f.
                 * Sometimes emails will not send correctly if this fails
                 */
                if(preg_match('/[\w\s]*\<(.*)\>/', $value, $matches))
                    $sendmail_from = $matches[1];
                else
                    $sendmail_from = $value;

            }

            $mail_headers[] = $key . ':   ' . trim($value);

        }

        $mail_headers = implode("\n", $mail_headers);

        $params = [];

        if($sendmail_from)
            $params['-f'] = $sendmail_from;

        if(is_array($dsn_types) && count($dsn_types) > 0)
            $params['-N'] = '"' . implode(',', array_map('strtolower', $dsn_types)) . '"';

        //The @ sign causes errors not to be thrown and allows things to continue.  the mail() command
        //will just return false when not successful.
        $ret = @mail($this->formatTo($to), $subject, $message, $mail_headers, (count($params) > 0 ? array_flatten($params, ' ', ' ') : NULL));

        if(!$ret){

            $error = error_get_last();

            throw new Exception\FailConnect(ake($error, 'message'), ake($error, 'type'));

        }

        return $ret;

    }

    private function formatTo($tolist) {

        return implode(', ', $tolist);

    }

}
