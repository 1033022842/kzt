<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\common\exception\BusinessException;
use app\common\security\JwtService;
use app\common\web\ResultCode;
use app\model\User;
use app\validate\UserValidate;

final class AuthController extends BaseController
{
    public function register()
    {
        $params = $this->request->post();
        $this->validate($params, UserValidate::class . '.register');

        if (!empty($params['email']) && User::where('email', $params['email'])->find()) {
            throw new BusinessException(ResultCode::USER_ERROR, '邮箱已存在');
        }

        if (User::where('username', $params['username'])->find()) {
            throw new BusinessException(ResultCode::USER_ERROR, '用户名已存在');
        }

        $user = new User();
        $user->username = $params['username'];
        $user->password = password_hash($params['password'], PASSWORD_BCRYPT);
        $user->nickname = $params['nickname'] ?? '';
        $user->email = $params['email'] ?? '';
        $user->role = 'user';
        $user->status = 1;
        $user->save();

        return $this->success(['id' => $user->id], '注册成功');
    }

    public function login()
    {
        $params = $this->request->post();
        $this->validate($params, UserValidate::class . '.login');

        $user = User::where('username', $params['username'])->find();
        if (!$user || !password_verify($params['password'], $user->password)) {
            throw new BusinessException(ResultCode::USER_ERROR, '用户名或密码错误');
        }

        if ($user->status !== 1) {
            throw new BusinessException(ResultCode::USER_ERROR, '用户已被禁用');
        }

        $payload = [
            'sub' => $user->id,
            'username' => $user->username,
            'role' => $user->role,
            'token_version' => (int)($user->token_version ?? 1),
        ];

        $accessToken = JwtService::generateAccessToken($payload);
        $refreshToken = JwtService::generateRefreshToken($user->id);

        return $this->success([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'nickname' => $user->nickname,
                'role' => $user->role,
            ],
        ]);
    }
}
