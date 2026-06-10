<?php

declare(strict_types=1);

namespace app\middleware;

use app\common\security\JwtService;
use app\common\web\Result;
use app\common\web\ResultCode;
use app\model\User;
use Closure;
use think\Request;
use think\Response;

final class Auth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('Authorization', '');
        $token = str_replace('Bearer ', '', $token);

        if (empty($token)) {
            return json(Result::failedWith(ResultCode::ACCESS_TOKEN_INVALID));
        }

        $payload = JwtService::parseToken($token);
        if (!$payload) {
            return json(Result::failedWith(ResultCode::ACCESS_TOKEN_INVALID));
        }

        $userId = $payload['sub'] ?? 0;
        $user = User::find($userId);
        if (!$user || $user->status !== 1) {
            return json(Result::failedWith(ResultCode::ACCESS_TOKEN_INVALID));
        }
        $payloadVersion = $payload['token_version'] ?? 0;
        $userVersion = (int)($user->token_version ?? 0);
        if ($payloadVersion && $userVersion && $payloadVersion !== $userVersion) {
            return json(Result::failedWith(ResultCode::ACCESS_TOKEN_INVALID, '账号已在别处登录，请重新登录'));
        }

        $request->userId = $user->id;
        $request->username = $user->username;
        $request->userRole = $user->role;

        return $next($request);
    }
}
