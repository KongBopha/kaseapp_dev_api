<?php

namespace App\Services;

use Google\Client;
use Illuminate\Support\Facades\Http;

class FirebaseService
{
    private $client;
    private $projectId;

    public function __construct()
    {
        // Initialize Google Client
        $this->client = new Client();
        $this->client->setAuthConfig(storage_path('app/public/credentials/credentials.json'));
        $this->client->addScope('https://www.googleapis.com/auth/firebase.messaging');

        // Get project_id from credentials JSON
        $credentials = json_decode(file_get_contents(storage_path('app/public/credentials/credentials.json')), true);
        $this->projectId = $credentials['project_id'];
    }

    // Get FCM access token
    private function getAccessToken()
    {
        if ($this->client->isAccessTokenExpired()) {
            $this->client->fetchAccessTokenWithAssertion();
        }
        return $this->client->getAccessToken()['access_token'];
    }

    // Send FCM notification
    public function sendNotification(string $fcmToken, string $title, string $body, array $data = [])
    {
        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
        $accessToken = $this->getAccessToken();

        $payload = [
            "message" => [
                "token" => $fcmToken,
                "notification" => [
                    "title" => $title,
                    "body" => $body,
                ],
             
            ]
        ];

        $response = Http::withToken($accessToken)
            ->post($url, $payload);

        return $response->json();
    }
}
