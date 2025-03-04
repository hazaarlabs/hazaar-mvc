<?php

namespace Hazaar\Mail;

use Hazaar\Application;
use Hazaar\File;

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
 */
class Adapter
{
    protected static string $default_transport = 'local';
    protected Transport $transport;
    protected TransportMessage $message;
    protected string|Template $subject;
    protected mixed $body;

    /**
     * @var array<mixed>
     */
    protected array $last_to = [];

    /**
     * @var array<mixed>
     */
    protected array $config;

    /**
     * The mail class constructor.
     *
     * If a transport is not provided then the [[Hazaar\Mail\Transport\Local]] transport will be used.
     *
     * @param array<mixed> $config the configuration settings for the mail adapter
     */
    public function __construct(?array $config = null)
    {
        $this->message = new TransportMessage();
        if (is_array($config)) {
            $this->config = $config;
        } else {
            if (!($app = Application::getInstance()) instanceof Application) {
                throw new \Exception('No application instance found!');
            }
            if (!isset($app->config['mail'])) {
                throw new \Exception('No mail configuration found!');
            }
            $this->config = array_merge([
                'enable' => true,
                'testmode' => false,
                'transport' => self::$default_transport,
            ], Application::getInstance()->config['mail']);
        }
        $this->transport = $this->getTransportObject($this->config['transport'], $this->config);
        if (isset($this->config['from'])) {
            $this->message->from = self::encodeEmailAddress($this->config['from']);
        }
    }

    /**
     * Get the transport object for the specified transport backend.
     *
     * @param string       $transport The transport backend to use.  Options are local, or smtp.
     * @param array<mixed> $config    The configuration settings for the transport backend
     */
    public function getTransportObject(string $transport = 'local', array $config = []): Transport
    {
        $transportClass = '\Hazaar\Mail\Transport\\'.ucfirst($transport);
        if (!class_exists($transportClass)) {
            throw new \Exception("The configured mail transport class '{$transport}' does not exist!");
        }

        return new $transportClass($config);
    }

    /**
     * Set the transport backend that should be used.
     *
     * @param string $transport The transport backend to use.  Options are local, or smtp.
     */
    public static function setDefaultTransport(string $transport): void
    {
        self::$default_transport = $transport;
    }

    /**
     * Set the 'From:' address header of the email.
     *
     * @param string $email The email address
     * @param string $name  The name part
     */
    public function setFrom(string $email, ?string $name = null): void
    {
        $this->message->from = self::encodeEmailAddress($email, $name);
    }

    /**
     * Set the 'Reply-To:' address header of the email.
     *
     * @param string $email The email address
     * @param string $name  The name part
     */
    public function setReplyTo(string $email, ?string $name = null): void
    {
        $this->message->replyTo[] = self::encodeEmailAddress($email, $name);
    }

    /**
     * Sets the return path for the email.
     *
     * @param string      $email the email address to set as the return path
     * @param null|string $name  the name associated with the email address (optional)
     */
    public function setReturnPath(string $email, ?string $name = null): void
    {
        $this->message->headers['Return-Path'] = self::encodeEmailAddress($email, $name);
    }

    /**
     * Clear all recipients ready for re-using the adapter.
     *
     * This is for working with templates that need to be sent/rendered multiple times to send to many recipients.
     */
    public function clear(bool $clear_attachments = false): void
    {
        $this->message->to = [];
        $this->message->cc = [];
        $this->message->bcc = [];
        if (true === $clear_attachments) {
            $this->message->attachments = [];
        }
    }

    /**
     * Set the 'To:' address header of the email.
     *
     * @param array<mixed>|string $email The email address
     * @param string              $name  The name part
     */
    public function addTo(array|string $email, ?string $name = null): void
    {
        if (is_array($email) && !array_key_exists('email', $email)) {
            foreach ($email as $addr) {
                $this->addTo($addr);
            }

            return;
        }
        $this->message->to[] = self::encodeEmailAddress($email, $name);
    }

    /**
     * Get the 'To:' address header of the email.
     *
     * @return array<mixed>
     */
    public function getTo(): array
    {
        return $this->message->to;
    }

    /**
     * Get the last 'To:' address header of the email.
     *
     * @return array<mixed>
     */
    public function getLastTo(): array
    {
        return $this->last_to;
    }

    /**
     * Set the 'CC:' address header of the email.
     *
     * @param array<string>|string $email
     */
    public function addCC(array|string $email, ?string $name = null): void
    {
        if (is_array($email) && !array_key_exists('email', $email)) {
            foreach ($email as $addr) {
                $this->addCC($addr);
            }

            return;
        }
        $this->message->cc[] = self::encodeEmailAddress($email, $name);
    }

    /**
     * Get the 'CC:' address header of the email.
     *
     * @return array<mixed>
     */
    public function getCC(): array
    {
        return $this->message->cc;
    }

    /**
     * Set the 'BCC:' address header of the email.
     *
     * @param array<string>|string $email
     */
    public function addBCC(array|string $email, ?string $name = null): void
    {
        if (is_array($email) && !array_key_exists('email', $email)) {
            foreach ($email as $addr) {
                $this->addBCC($addr);
            }

            return;
        }
        $this->message->bcc[] = self::encodeEmailAddress($email, $name);
    }

    /**
     * Get the 'BCC:' address header of the email.
     *
     * @return array<mixed>
     */
    public function getBCC(): array
    {
        return $this->message->bcc;
    }

    /**
     * Set the 'Subject' header of the email.
     */
    public function setSubject(string|Template $subject): void
    {
        if (!$subject instanceof Template) {
            $template = new Template();
            $template->loadFromString($subject);
            $subject = $template;
        }
        $this->subject = $subject;
    }

    /**
     * Sets the plain text body of the email.
     *
     * @param string $body The plain text body of the email
     */
    public function setBodyText($body): void
    {
        $template = new Template();
        $template->loadFromString($body);
        $this->body = $template;
    }

    /**
     * Sets a the HTML body of the email.
     */
    public function setBodyHTML(mixed $html): void
    {
        if (!($html instanceof Mime\Part || $html instanceof Mime\Message)) {
            $html = new Mime\Html($html);
        }
        $this->body = $html;
    }

    public function addAttachment(Attachment|File $file, ?string $name = null): void
    {
        if (!$file instanceof Attachment) {
            $file = new Attachment($file, $name);
        }
        $this->message->attachments[] = $file;
    }

    /**
     * Set an email template to use as the email body.
     *
     * @param Template $template the template to use
     */
    public function setBodyTemplate(Template $template): void
    {
        $this->body = $template;
    }

    /**
     * Load load a template to use as the TEXT email body.
     *
     * @param string $filename The filename to load the template from
     */
    public function loadTemplate(string $filename): void
    {
        $template = new Template();
        $template->loadFromFile(new File($filename));
        $this->body = $template;
    }

    /**
     * Load a template to use as the HTML email body.
     *
     * @param string $filename The filename to load the template from
     */
    public function loadHTMLTemplate(string $filename): void
    {
        $template = new Template();
        $template->loadFromFile(new File($filename));
        $this->body = new Mime\Html($template);
    }

    /**
     * Get the current body part of the email.
     *
     * @param array<mixed> $params
     */
    public function getBody(array $params = []): mixed
    {
        if ($this->body instanceof Mime\Message) {
            return $this->body;
        }
        if ($this->body instanceof Mime\Part) {
            if ($this->body instanceof Mime\Html) {
                $this->body->setParams($params);
            }

            return new Mime\Message([$this->body] + $this->message->attachments);
        }
        $message = '';
        foreach ($this->body as $part) {
            $message .= $part->render($params);
        }
        foreach ($this->message->attachments as $attachment) {
            $message .= $attachment->encode();
        }

        return $message;
    }

    /**
     * Set extra headers to be included in the email.
     *
     * @param array<string> $headers
     */
    public function setExtraHeaders(array $headers): void
    {
        foreach ($headers as $key => $value) {
            $this->message->headers[$key] = $value;
        }
    }

    /**
     * Send the email using the current transport backend.
     *
     * @param array<mixed> $params
     *
     * @return mixed false if the email was not sent, or the result of the transport send method
     */
    public function send(array $params = []): mixed
    {
        if (true !== $this->config['enable']) {
            throw new \Exception('Mail subsystem is disabled!');
        }
        if (true === $this->config['testmode']) {
            return true;
        }
        if ($subjectPrefix = $this->config['subjectPrefix']) {
            $this->subject->prepend($subjectPrefix);
        }
        $this->message->subject = $this->subject->render($params);
        $this->message->content = $this->getBody($params);
        $map_func = function ($item) {
            return is_array($item) ? Adapter::encodeEmailAddress($item[0], $item[1]) : $item;
        };
        if ($override_to = $this->config['override']['to']) {
            $to = [];
            if (isset($this->config['noOverrideMatch'])) {
                foreach ((array) $override_to as $rcpt) {
                    if (preg_match('/'.$this->config['noOverrideMatch'].'/', $rcpt[0])) {
                        $to[] = $rcpt;
                    }
                }
            }
            if (0 === count($to)) {
                $to = $override_to;
            }
            $this->message->to = array_map($map_func, (array) $to);
        }
        if ($cc = $this->config['override']['cc']) {
            $this->message->cc = array_map($map_func, (array) $cc);
        }
        if ($bcc = $this->config['override']['bcc']) {
            $this->message->bcc = array_map($map_func, (array) $bcc);
        }
        $this->last_to = $this->message->to;
        $result = $this->transport->send($this->message);
        if ($result) {
            $this->clear();
        }

        return $result;
    }

    /**
     * Enables ALL Delivery Status Notification types.
     */
    public function enableDSN(): void
    {
        $this->message->dsn = ['success', 'failure', 'delay'];
    }

    /**
     * Disable ALL Delivery Status Notifications.
     */
    public function disableDSN(): void
    {
        $this->message->dsn = ['never'];
    }

    /**
     * Enables SUCCESS Delivery Status Notification types.
     */
    public function enableDSNSuccess(): void
    {
        $this->resetDSN();
        if (!in_array('success', $this->message->dsn)) {
            $this->message->dsn[] = 'success';
        }
    }

    /**
     * Enables SUCCESS Delivery Status Notification types.
     */
    public function enableDSNFailure(): void
    {
        $this->resetDSN();
        if (!in_array('failure', $this->message->dsn)) {
            $this->message->dsn[] = 'failure';
        }
    }

    /**
     * Enables SUCCESS Delivery Status Notification types.
     */
    public function enableDSNDelay(): void
    {
        $this->resetDSN();
        if (!in_array('delay', $this->message->dsn)) {
            $this->message->dsn[] = 'delay';
        }
    }

    /**
     * Encodes an email address into RFC5322 standard format.
     *
     * @param array<string>|string $email The email address
     * @param string               $name  The name part
     *
     * @return array<string> An array containing the email address and name
     */
    protected static function encodeEmailAddress(array|string $email, ?string $name = null): array
    {
        if (is_array($email)) {
            $name = ake($email, 'name');
            $email = ake($email, 'email');
        }
        $email = trim($email ?? '');
        $name = trim($name ?? '');

        return ['email' => $email, 'name' => $name];
    }

    protected function resetDSN(): void
    {
        if (($key = array_search('never', $this->message->dsn)) !== false) {
            unset($this->message->dsn[$key]);
        }
    }
}
