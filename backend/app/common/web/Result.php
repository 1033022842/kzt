<?php

declare(strict_types=1);

namespace app\common\web;

final class Result
{
    public static function success(mixed $data = null, string $msg = ''): array
    {
        return [
            'code' => ResultCode::SUCCESS,
            'msg' => $msg ?: ResultCode::getMsg(ResultCode::SUCCESS),
            'data' => $data,
        ];
    }

    public static function page(array $list, int $total): array
    {
        return [
            'code' => ResultCode::SUCCESS,
            'msg' => ResultCode::getMsg(ResultCode::SUCCESS),
            'data' => [
                'list' => $list,
                'total' => $total,
            ],
        ];
    }

    public static function failedWith(string $code, string $msg = ''): array
    {
        return [
            'code' => $code,
            'msg' => $msg ?: ResultCode::getMsg($code),
            'data' => null,
        ];
    }
}
