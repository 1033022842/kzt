<?php

declare(strict_types=1);

return [
    'secret' => env('JWT_SECRET', 'zhibo_jwt_secret_key_2024'),
    'access_ttl' => (int) env('JWT_ACCESS_TTL', 7200),
    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 604800),
    'issuer' => env('JWT_ISSUER', 'zhibo'),
];
