<?php

declare(strict_types=1);

namespace app\common\exception;

use app\common\web\ResultCode;
use RuntimeException;

final class BusinessException extends RuntimeException
{
    private string $resultCode;

    public function __construct(string $code, string $message = '')
    {
        $this->resultCode = $code;
        parent::__construct($message ?: ResultCode::getMsg($code), 0);
    }

    public function getResultCode(): string
    {
        return $this->resultCode;
    }
}
