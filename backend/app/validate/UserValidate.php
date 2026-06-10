<?php

declare(strict_types=1);

namespace app\validate;

use think\Validate;

final class UserValidate extends Validate
{
    protected $rule = [
        'username' => 'require|max:50',
        'password' => 'require|min:6|max:20',
        'nickname' => 'require|max:50',
        'email' => 'email',
    ];

    protected $message = [
        'username.require' => '用户名不能为空',
        'username.max' => '用户名最多50个字符',
        'password.require' => '密码不能为空',
        'password.min' => '密码至少6个字符',
        'password.max' => '密码最多20个字符',
        'nickname.require' => '昵称不能为空',
        'nickname.max' => '昵称最多50个字符',
        'email.email' => '邮箱格式错误',
    ];

    protected $scene = [
        'register' => ['username', 'password', 'nickname', 'email'],
        'login' => ['username', 'password'],
    ];
}
