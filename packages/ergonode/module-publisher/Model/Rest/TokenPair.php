<?php

declare(strict_types=1);

namespace Ergonode\Publisher\Model\Rest;

use Magento\Framework\Exception\LocalizedException;

class TokenPair
{
    /**
     * @param array<string|int, mixed> $response
     * @return array{token: string, refresh_token: string, expires_at: int}
     */
    public function read(array $response): array
    {
        $token = is_string($response['token'] ?? null) ? trim($response['token']) : '';
        $refresh = is_string($response['refresh_token'] ?? null) ? trim($response['refresh_token']) : '';
        $parts = explode('.', $token);
        $payload = count($parts) === 3 ? base64_decode(strtr($parts[1], '-_', '+/'), true) : false;
        $claims = $payload !== false ? json_decode($payload, true) : null;
        $expiresAt = is_array($claims) ? (int)($claims['exp'] ?? 0) : 0;
        if ($token === '' || $refresh === '' || $expiresAt <= time()) {
            throw new LocalizedException(__('Ergonode did not return a valid access and refresh token pair.'));
        }
        return ['token' => $token, 'refresh_token' => $refresh, 'expires_at' => $expiresAt];
    }
}
