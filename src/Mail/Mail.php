<?php

namespace Hazaar;

use Hazaar\Mail\Template;

/**
 * Common class for sending emails via different transport mechanisms.
 *
 * This class provides a common interface for send emails via transport backends.  These backends can be anything from
 * local sendmail execution via the PHP mail() command, or directly using SMTP.
 *
 * @since 1.0.0
 */
class Mail {

    static private $default_transport;

    private        $transport;

    private        $headers = array();

    private        $from;

    private        $to      = array();

    private        $subject;

    private        $body    = array();

    /**
     * The mail class constructor
     *
     * If a transport is not provided then the [[Hazaar\Mail\Transport\Local]] transport will be used.
     *
     * @param \Hazaar\Mail\Transport\Local $transport The transport backend to use.
     */
    function __construct(Mail\Transport $transport = NULL) {

        if(! $transport)
            $transport = (Mail::$default_transport ? Mail::$default_transport : new Mail\Transport\Local());

        $this->transport = $transport;

    }

    /**
     * Set the transport backend that should be used
     *
     * @param \Hazaar\Mail\Transport\Local $transport The transport backend to use.
     */
    static public function setDefaultTransport(Mail\Transport $transport) {

        Mail::$default_transport = $transport;

    }

    /**
     * Encodes an email address into RFC5322 standard format.
     *
     * @param string $email The email address
     *
     * @param string $name The name part
     */
    private function encodeEmailAddress($email, $name) {

        $email = trim($email);

        $name = trim($name);

        return ($name ? "$name <$email>" : $email);

    }

    /**
     * Set the 'From:' address header of the email
     *
     * @param string $email The email address
     *
     * @param string $name The name part
     */
    public function setFrom($email, $name = NULL) {

        $this->from = $this->encodeEmailAddress($email, $name);

    }

    /**
     * Set the 'Reply-To:' address header of the email
     *
     * @param string $email The email address
     *
     * @param string $name The name part
     */
    public function setReplyTo($email, $name = NULL) {

        $this->headers['Reply-To'] = $this->encodeEmailAddress($email, $name);

        $this->headers['Return-Path'] = $this->encodeEmailAddress($email, $name);

    }

    /**
     * Set the 'To:' address header of the email
     *
     * @param string $email The email address
     *
     * @param string $name The name part
     */
    public function addTo($email, $name = NULL) {

        $this->to[] = $this->encodeEmailAddress($email, $name);

    }

    /**
     * Set the 'Subject' header of the email
     *
     * @param string $subject The email address
     */
    public function setSubject($subject) {

        $this->subject = $subject;

    }

    /**
     * Sets the plain text body of the email
     *
     * @param string $body The plain text body of the email
     */
    public function setBodyText($body) {

        $template = new Template();

        $template->loadFromString($body);

        $this->body[] = $template;

    }

    /**
     * Sets a the HTML body of the email
     *
     * @param string $html The HTML body of the email
     */
    public function setBodyHTML($html) {

        if(! $html instanceof Mime\Part) {

            $html = new Mail\Html($html);

        }

        $this->body[] = $html;

    }

    /**
     * Set an email template to use as the email body
     *
     * @param \Hazaar\Mail\Template $template The template to use.
     */
    public function setBodyTemplate(Mail\Template $template) {

        $this->body[] = $template;

    }

    /**
     * Get the current body part of the email
     *
     * @return string The body of the email
     */
    private function getBody($params = array()) {

        $message = '';

        $use_mime = FALSE;

        /*
         * See if we have any MIME parts so we know if we should generate a MIME message
         */
        foreach($this->body as $part) {

            if($part instanceof Mime\Part) {

                $use_mime = TRUE;

                break;

            }

        }

        /*
         * If we do have MIME parts, return a Hazaar_Mime_Message object
         * Otherwise, just implode the body as text and return that.
         */
        if($use_mime == TRUE) {

            $message = new Mime\Message($this->body);

        } else {

            $message = '';

            foreach($this->body as $part)
                $message .= $part->parse($params);

        }

        return $message;

    }

    public function setExtraHeaders($headers) {

        foreach($headers as $key => $value)
            $this->headers[$key] = $value;

        return TRUE;

    }

    /**
     * Send the email using the current transport backend
     *
     * @return boolean True/false as to whether the transmission was successful
     */
    public function send($params = array()) {

        if(! $this->transport instanceof Mail\Transport) {

            throw new Exception('No mail transport set while trying to send mail');

        }

        /*
         * Add the from address to the extra headers
         */
        $headers = $this->headers;

        $headers['From'] = $this->from;

        $message = $this->getBody($params);

        if($message instanceof Mime\Message) {

            $message->addHeaders($headers);

            $headers = $message->getHeaders();

            $body = $message->encode();

        } else {

            $body = $message;

        }

        return $this->transport->send($this->to, $this->subject, $body, $headers);

    }

}
