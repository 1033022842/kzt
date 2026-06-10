<?php

declare(strict_types=1);

namespace app\common\security;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use think\facade\Config;

final class JwtService
{
    public static function generateAccessToken(array $payload): string
    {
        $payload['exp'] = time() + (int) Config::get('jwt.access_ttl', 7200);
        $payload['iat'] = time();
        $payload['iss'] = Config::get('jwt.issuer', 'zhibo');

        return JWT::encode($payload, Config::get('jwt.secret'), 'HS256');
    }

    public static function parseToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key(Config::get('jwt.secret'), 'HS256'));
            return (array) $decoded;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function generateRefreshToken(int $userId): string
    {
        return JWT::encode([
            'sub' => $userId,
            'type' => 'refresh',
            'exp' => time() + (int) Config::get('jwt.refresh_ttl', 604800),
        ], Config::get('jwt.secret'), 'HS256');
    }
}
