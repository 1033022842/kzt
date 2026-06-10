<?php

declare(strict_types=1);

namespace app\common\web;

final class ResultCode
{
    public const SUCCESS = '00000';
    public const USER_ERROR = 'A0001';
    public const ACCESS_TOKEN_INVALID = 'A0002';
    public const PARAM_ERROR = 'A0003';
    public const SYSTEM_ERROR = 'B0001';

    public static function getMsg(string $code): string
    {
        return match ($code) {
            self::SUCCESS => '成功',
            self::USER_ERROR => '用户错误',
            self::ACCESS_TOKEN_INVALID => 'Token无效',
            self::PARAM_ERROR => '参数错误',
            self::SYSTEM_ERROR => '系统错误',
            default => '',
        };
    }
}
