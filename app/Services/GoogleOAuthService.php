<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use RuntimeException;

class GoogleOAuthService
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const USERINFO_URL = 'https://www.googleapis.com/oauth2/v3/userinfo';

    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct()
    {
        $this->clientId = (string)Config::get('GOOGLE_CLIENT_ID', '');
        $this->clientSecret = (string)Config::get('GOOGLE_CLIENT_SECRET', '');
        $this->redirectUri = (string)Config::get('GOOGLE_REDIRECT_URI', '');

        // Если redirectUri относительный, дополняем APP_URL
        if (!str_starts_with($this->redirectUri, 'http')) {
            $baseUrl = rtrim((string)Config::get('APP_URL', 'https://altybayev.kz'), '/');
            $this->redirectUri = $baseUrl . '/' . ltrim($this->redirectUri, '/');
        }
    }

    /**
     * Формирует ссылку для входа через Google
     */
    public function getAuthUrl(string $state): string
    {
        $params = [
            'client_id'             => $this->clientId,
            'redirect_uri'          => $this->redirectUri,
            'response_type'         => 'code',
            'scope'                 => 'openid email profile',
            'access_type'           => 'online',
            'state'                 => $state,
            'prompt'                => 'select_account'
        ];

        return self::AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Обменивает код авторизации на токен доступа и возвращает профиль пользователя Google
     */
    public function getUserFromCode(string $code): array
    {
        // 1. Запрос access_token через cURL
        $postData = [
            'code'          => $code,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri'  => $this->redirectUri,
            'grant_type'    => 'authorization_code'
        ];

        $ch = curl_init(self::TOKEN_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            throw new RuntimeException("Қате: Google сервеlevel қосылу сәтсіз: {$curlError}");
        }

        $tokenData = json_decode((string)$response, true);
        if ($httpCode !== 200 || empty($tokenData['access_token'])) {
            $errMsg = $tokenData['error_description'] ?? $tokenData['error'] ?? 'Белгісіз қате / Неизвестная ошибка';
            throw new RuntimeException("Google токен алу қатесі: {$errMsg}");
        }

        $accessToken = $tokenData['access_token'];

        // 2. Запрос информации о профиле пользователя
        $ch = curl_init(self::USERINFO_URL);
        curl_setopt_array($ch, [
            CURLOPT_HTTPGET        => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$accessToken}",
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $userInfoResponse = curl_exec($ch);
        $infoHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $userInfo = json_decode((string)$userInfoResponse, true);
        if ($infoHttpCode !== 200 || empty($userInfo['email'])) {
            throw new RuntimeException("Google профиль мәліметтерін алу мүмкін болмады");
        }

        return [
            'google_id'  => $userInfo['sub'] ?? '',
            'email'      => strtolower(trim($userInfo['email'])),
            'name'       => $userInfo['name'] ?? explode('@', $userInfo['email'])[0],
            'avatar_url' => $userInfo['picture'] ?? null,
        ];
    }
}
