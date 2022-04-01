<?php

namespace Hazaar\Mail;

/**
 * Common class for sending emails via different transport mechanisms.
 *
 * This class provides a common interface for send emails via transport backends.  These backends can be anything from
 * local sendmail execution via the PHP mail() command, or directly using SMTP.
 *
 * @since 1.0.0
 */
class Adapter {

    private        $transport;

    private        $headers = [];

    private        $recipient_headers = [];

    private        $from;

    private        $to      = [];

    private        $cc      = [];

    private        $bcc     = [];

    private        $subject;

    private        $body    = [];

    private        $dsn     = [];

    /**
     * The mail class constructor
     *
     * If a transport is not provided then the [[Hazaar\Mail\Transport\Local]] transport will be used.
     *
     * @param string $transport The name of the transport backend to use.
     */
    function __construct($transport = 'local') {

        $config = new \Hazaar\Map([
            'transport' => 'local'
        ], \Hazaar\Application::getInstance()->config->get('mail'));

        $this->transport = $this->getTransportObject($config->transport, $config);

        if($config->has('from'))
            $this->from = $this->encodeEmailAddress(ake($config->from, 'email'), ake($config->from, 'name'));

    }

    public function getTransportObject($transport = 'local', $config = []){

        $transportClass = '\\Hazaar\\Mail\\Transport\\' . ucfirst($transport);

        if(!class_exists($transportClass))
            throw new \Exception("The configured mail transport class '$transport' does not exist!");

        $transportObject = new $transportClass($config);

        return $transportObject;

    }

    /**
     * Set the transport backend that should be used
     *
     * @param \Hazaar\Mail\Transport\Local $transport The transport backend to use.
     */
    static public function setDefaultTransport(Transport $transport) {

        Adapter::$default_transport = $transport;

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
     * Clear all recipients ready for re-using the adapter
     * 
     * This is for working with templates that need to be sent/rendered multiple times to send to many recipients.
     */
    public function clear(){

        $this->to = [];

        $this->cc = [];

        $this->bcc = [];

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
     * Set the 'CC:' address header of the email
     *
     * @param string $email The email address
     *
     * @param string $name The name part
     */
    public function addCC($email, $name = NULL) {

        $this->cc[] = $this->encodeEmailAddress($email, $name);

    }

    /**
     * Set the 'BCC:' address header of the email
     *
     * @param string $email The email address
     *
     * @param string $name The name part
     */
    public function addBCC($email, $name = NULL) {

        $this->bcc[] = $this->encodeEmailAddress($email, $name);

    }

    /**
     * Set the 'Subject' header of the email
     *
     * @param string $subject The email address
     */
    public function setSubject($subject) {

        if(!$subject instanceof Template){

            $template = new Template();

            $template->loadFromString($subject);

            $subject = $template;

        }

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

        $this->body['body'] = $template;

    }

    /**
     * Sets a the HTML body of the email
     *
     * @param mixed $html The HTML body of the email
     */
    public function setBodyHTML($html) {

        if(! $html instanceof Mime\Part)
            $html = new Html($html);

        $this->body['body'] = $html;

    }

    public function addAttachment($file, $name = null){

        if(!$file instanceof Attachment)
            $file = new Attachment($file, $name);

        $this->body[] = $file;

    }

    /**
     * Set an email template to use as the email body
     *
     * @param \Hazaar\Mail\Template $template The template to use.
     */
    public function setBodyTemplate(Template $template) {

        $this->body[] = $template;

    }

    /**
     * Load load a template to use as the TEXT email body
     * 
     * @param string $filename The filename to load the template from
     */
    public function loadTemplate($filename){

        $template = new Template();

        $template->loadFromFile($filename);

        $this->body['body'] = $template;

    }

    /**
     * Load a template to use as the HTML email body
     * 
     * @param string $filename The filename to load the template from
     */
    public function loadHTMLTemplate($filename){

        $template = new Template();

        $template->loadFromFile($filename);

        $this->body['body'] = new Html($template);

    }

    /**
     * Get the current body part of the email
     *
     * @return string The body of the email
     */
    public function getBody($params = []) {

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

            $message = new Mime\Message($this->body, $params);

        } else {

            $message = '';

            foreach($this->body as $part)
                $message .= $part->render($params);

        }

        return $message;

    }

    public function setExtraHeaders($headers) {

        foreach($headers as $key => $value)
            $this->headers[$key] = $value;

        return TRUE;

    }

    public function setRecipientHeaders($headers) {

        foreach($headers as $key => $value)
            $this->recipient_headers[$key] = $value;

        return TRUE;

    }

    /**
     * Send the email using the current transport backend
     *
     * @return boolean True/false as to whether the transmission was successful
     */
    public function send($params = []) {

        if(! $this->transport instanceof Transport)
            throw new \Exception('No mail transport set while trying to send mail');

        /*
         * Add the from address to the extra headers
         */
        $headers = array_merge($this->headers, $this->recipient_headers);

        $headers['From'] = $this->from;

        $message = $this->getBody($params);

        if($message instanceof Mime\Message) {

            $message->addHeaders($headers);

            $headers = $message->getHeaders();

            $body = $message->encode($params);

        } else {

            $body = $message;

        }

        if(count($this->cc) > 0)
            $headers['CC'] = implode(', ', $this->cc);

        if(count($this->bcc) > 0)
            $headers['BCC'] = implode(', ', $this->bcc);
        
        $result = $this->transport->send($this->to, $this->subject->render($params), $body, $headers, $this->dsn);

        if($result)
            $this->clear();

        return $result;
        
    }

    /**
     * Enables ALL Delivery Status Notification types
     */
    public function enableDSN(){

        $this->dsn = ['success', 'failure', 'delay'];

    }

    /**
     * Disable ALL Delivery Status Notifications
     */
    public function disableDSN(){
        
        $this->dsn = ['never'];

    }

    private function resetDSN(){

        if (($key = array_search('never', $this->dsn)) !== false)
            unset($$this->dsn[$key]);

    }

    /**
     * Enables SUCCESS Delivery Status Notification types
     */
    public function enableDSNSuccess(){

        $this->resetDSN();

        if(!in_array('success', $this->dsn))
            $this->dsn[] = 'success';

    }

    /**
     * Enables SUCCESS Delivery Status Notification types
     */
    public function enableDSNFailure(){

        $this->resetDSN();

        if(!in_array('failure', $this->dsn))
            $this->dsn[] = 'failure';

    }

    /**
     * Enables SUCCESS Delivery Status Notification types
     */
    public function enableDSNDelay(){

        $this->resetDSN();

        if(!in_array('delay', $this->dsn))
            $this->dsn[] = 'delay';

    }
    
    public function reset(){

        $this->to = [];

        $this->cc = [];

        $this->bcc = [];

        $this->recipient_headers = [];

    }

}
