<?php

namespace Hazaar\Mail\Transport;

class Local extends \Hazaar\Mail\Transport {

    public function send($to, $subject = NULL, $message = NULL, $extra_headers = array()) {

        $sendmail_from = '';

        foreach($extra_headers as $key => $value) {

            if(strtolower($key) == 'from') {

                /*
                 * If this is the from header, try and extract the email address to use in sendmail -f.
                 * Sometimes emails will not send correctly if this fails
                 */
                if(preg_match('/[\w\s]*\<(.*)\>/', $value, $matches)) {

                    $sendmail_from = $matches[1];

                } else {

                    $sendmail_from = $value;

                }

            }

            $headers[] = $key . ': ' . trim($value);

        }

        //The @ sign causes errors not to be thrown and allows things to continue.  the mail() comment will just return false when not successful.
        $ret = @mail($this->formatTo($to), $subject, $message, implode("\n", $headers), ($sendmail_from ? '-f ' . $sendmail_from : NULL));

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
