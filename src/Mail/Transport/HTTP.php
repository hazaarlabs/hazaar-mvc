<?php

namespace Hazaar\Mail\Transport;

use Hazaar\Mail\Transport;
use Hazaar\Mail\TransportMessage;

class HTTP extends Transport
{
    /**
     * @var array<string>
     */
    protected array $headers = [];

    /**
     * @var array<string>
     */
    protected array $schema = [
        // 'type_id' => '$typeId', //One day this may support multiple message types such as email or SMS.
        'send_at' => '$sendAt',
        'to' => '$to',
        'cc' => '$cc',
        'bcc' => '$bcc',
        'from' => '$from',
        'reply_to' => '$replyTo',
        'reply_to_list' => '$replyTo_list',
        'subject' => '$subject',
        'headers' => '$headers',
        'attachments' => '$attachments',
        'categories' => '$categories',
        'batch_id' => '$batchId',
        'content' => '$content',
    ];

    public function send(TransportMessage $message): mixed
    {
        $url = $this->options['url'] ?? '';
        $data = [];
        foreach ($this->schema as $key => $value) {
            if ('$' === substr($value, 0, 1)) {
                $value = substr($value, 1);
            } else {
                $value = $key;
            }
            if (isset($message->{$value})) {
                $data[$key] = $message->{$value};
            }
        }
        $headers = [
            'Content-Type: application/json',
        ];
        foreach ($this->headers as $name => $value) {
            $headers[] = $name.': '.$value;
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true); // Set to true to make a POST request
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); // Set the JSON data as the request body
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        if (false === $response) {
            throw new \Exception('Failed to send email: '.curl_error($ch));
        }
        curl_close($ch);

        return json_decode($response, true);
    }

    public function addHeader(string $name, string $value): void
    {
        $this->headers[$name] = $value;
    }
}
