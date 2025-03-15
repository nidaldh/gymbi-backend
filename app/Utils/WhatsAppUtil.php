<?php

namespace App\Utils;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class WhatsAppUtil
{
    protected $token;
    protected $apiUrl;
    protected $client;

    public function __construct()
    {
        $this->token = env('ULTRAMSG_TOKEN');
        $this->apiUrl = env('ULTRAMSG_API_URL');
        $this->client = new Client();
    }

    public function sendMessage($to, $body)
    {
        if (empty($to) || empty($body)) {
            throw new \Exception("Invalid Parameters");
        }
        if (env('APP_DEBUG', true)) {
            $to = '+970597035694';
        }
        $params = [
            'token' => $this->token,
            'to' => $to,
            'body' => $body,
        ];

        try {
            $response = $this->client->post($this->apiUrl, [
                'form_params' => $params,
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
            ]);

            return $response->getBody()->getContents();
        } catch (RequestException $e) {
            throw new \Exception("HTTP Request Error: " . $e->getMessage());
        }
    }
}
