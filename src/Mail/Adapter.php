<?php

namespace Hazaar\Mail;

/**
 * Common class for sending emails via different transport mechanisms.
 *
 * This class provides a common interface for send emails via transport backends.  These backends can be anything from
 * local sendmail execution via the PHP mail() command, or directly using SMTP.
 * 
 * ## Configuration Settings
 * 
 * * _enable_ - BOOLEAN - This is TRUE by default.  Setting to FALSE will cause any calls to `Adapter::send()` to throw an `\Exception`.
 * * _transport_ - STRING - This is the default transport to use when one is not specified.  Currently supports 'local' and 'smtp'.
 * * _testmode_ - BOOLEAN - This is FALSE by default.  Setting it to TRUE will activate test mode.  This is exactly the same as disabling
 *   emails with `enable = FALSE` except that it will simulate a successful transmission.
 * * _override_ - This allows recipient emails to be overriden with the email address listed.
 *   * _to_ - ARRAY - Override all 'to' recipient addresses with the list provided.
 *   * _cc_ - ARRAY - Override all 'cc' recipient addresses with the list provided.
 *   * _bcc_ - ARRAY - Override all 'bcc' recipient addresses with the list provided.
 * * _noOverrideMatch_ - REGEX - Do not override any 'to' recipient email address that match this regex.
 *
 * Override email address can be either plain strings, or an Array where index 0 is the email address, and index 1 is the recipient name.
 * 
 * Example #1: `[ "support@hazaar.io" ]`
 * 
 * Example #2: `[ [ "support@hazaar.io", "Hazaar Labs Support" ] ]`
 * 
 * @since 1.0.0
 */
class Adapter {

    static private $default_transport = 'local';

    public        $transport;

    private        $headers             = [];

    private        $recipient_headers   = [];

    private        $from;

    private        $recipients          = [
        'to' => [],
        'cc' => [],
        'bcc' => []
    ];

    private        $subject;

    private        $body                = [];

    private        $attachments         = [];

    private        $dsn                 = [];

    private        $last_to             = [];

    private        $config;

    /**
     * The mail class constructor
     *
     * If a transport is not provided then the [[Hazaar\Mail\Transport\Local]] transport will be used.
     *
     * @param string|array<mixed> $transport The name of the transport backend to use.
     */
    function __construct($transport = null) {

        if(is_array($transport)){
            $config = $transport;
            $transport = null;
        }else{
            $config = \Hazaar\Application::getInstance()->config->get('mail');
        }

        $this->config = new \Hazaar\Map([
            'enable' => true,
            'testmode' => false,
            'transport' => self::$default_transport
        ], $config);

        if($transport !== null){
            $this->config->transport = $transport;
        }

        $this->transport = $this->getTransportObject($this->config->transport, $this->config);

        if($this->config->has('from')){

            $this->from = (is_object($this->config->from))
                ? [ake($this->config->from, 'email'), ake($this->config->from, 'name')]
                : $this->from = $this->config->from;

        }

    }

    public function getTransportObject($transport = 'local', $config = []){

        $transportClass = '\\Hazaar\\Mail\\Transport\\' . ucfirst($transport);

        if(!class_exists($transportClass)){

            if(class_exists($transport) && is_subclass_of($transport, Transport::class)){
                $transportClass = $transport;
            }else{
                throw new \Exception("The configured mail transport class '$transport' does not exist!");
            }

        }

        $transportObject = new $transportClass($config);

        return $transportObject;

    }

    /**
     * Set the transport backend that should be used
     *
     * @param string $transport The transport backend to use.  Options are local, or smtp.
     */
    static public function setDefaultTransport($transport) {

        self::$default_transport = $transport;

    }

    /**
     * Set the 'From:' address header of the email
     *
     * @param string $email The email address
     *
     * @param string $name The name part
     */
    public function setFrom($email, $name = NULL) {

        $this->from = [$email, $name];

    }

    /**
     * Set the 'Reply-To:' address header of the email
     *
     * @param string $email The email address
     *
     * @param string $name The name part
     */
    public function setReplyTo($email, $name = NULL) {

        $this->headers['Reply-To'] = Transport::encodeEmailAddress($email, $name);

        $this->headers['Return-Path'] = Transport::encodeEmailAddress($email, $name);

    }

    /**
     * Clear all recipients ready for re-using the adapter
     * 
     * This is for working with templates that need to be sent/rendered multiple times to send to many recipients.
     */
    public function clear($clear_attachments = false){

        $this->recipients = [
            'to' => [],
            'cc' => [],
            'bcc' => []
        ];

        $this->recipient_headers = [];

        if($clear_attachments === true)
            $this->attachments = [];

    }

    /**
     * Set the 'To:' address header of the email
     *
     * @param string $email The email address
     *
     * @param string $name The name part
     */
    public function addTo($email, $name = NULL) {

        $this->recipients['to'][] = [trim($email), trim($name??'')];

    }

    public function getTo(){

        return $this->recipients['to'];

    }

    public function getLastTo(){

        return $this->last_to;
        
    }

    /**
     * Set the 'CC:' address header of the email
     *
     * @param string $email The email address
     *
     * @param string $name The name part
     */
    public function addCC($email, $name = NULL) {

        $this->recipients['cc'][] = [trim($email), trim($name??'')];

    }

    public function getCC(){

        return $this->recipients['cc'];

    }

    /**
     * Set the 'BCC:' address header of the email
     *
     * @param string $email The email address
     *
     * @param string $name The name part
     */
    public function addBCC($email, $name = NULL) {

        $this->recipients['bcc'][] = [trim($email), trim($name??'')];

    }

    public function getBCC(){

        return $this->recipients['bcc'];

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

        $this->attachments[] = $file;

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

            $message = new Mime\Message($this->body);

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

        if($this->config->enable !== true)
            throw new \Exception('Mail subsystem is disabled!');

        if(! $this->transport instanceof Transport)
            throw new \Exception('No mail transport set while trying to send mail');

        if(!$this->from)
            throw new \Exception('No From address specified');

        if($this->config->get('testmode') === true)
            return true;

        $recipients = ['to' => []];

        /*
         * Add the from address to the extra headers
         */
        $headers = array_merge($this->headers, $this->recipient_headers);

        $message = $this->getBody($params);

        if($message instanceof Mime\Message) {

            $message->addHeaders($headers);

            $message->setParams($params);

            $headers = $message->getHeaders();

        }

        if($cc = $this->config->getArray('override.cc'))
            $this->recipients['cc'] = (array)$cc;

        if($bcc = $this->config->getArray('override.bcc'))
            $this->recipients['bcc'] = (array)$bcc;

        if(($o_to = $this->config->getArray('override.to'))){

            $to = [];

            if($this->config->has('noOverrideMatch')){

                foreach($this->recipients['to'] as $rcpt)
                    if(preg_match('/' . $this->config->noOverrideMatch . '/', $rcpt[0]))
                        $to[] = $rcpt;

            }

            if(count($to) === 0)
                $to = $o_to;

            $this->recipients['to'] = $to; 
            
        }
            
        if($subjectPrefix = $this->config->get('subjectPrefix'))
            $this->subject->prepend($subjectPrefix);

        $result = $this->transport->send(
            $this->recipients, 
            $this->from, 
            $this->subject->render($params),
            $message, 
            $headers, 
            $this->attachments
        );

        $this->last_to = $recipients['to'];

        if($result)
            $this->clear();

        return $result;
        
    }

}
