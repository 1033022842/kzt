<?php

declare(strict_types=1);

namespace app;

use app\common\exception\BusinessException;
use app\common\web\Result;
use Throwable;
use think\exception\Handle;
use think\exception\HttpException;
use think\Response;
use think\Request;

final class ExceptionHandle extends Handle
{
    protected $ignoreReport = [
        BusinessException::class,
        HttpException::class,
    ];

    public function render(Request $request, Throwable $e): Response
    {
        if ($e instanceof BusinessException) {
            return json(Result::failedWith($e->getResultCode(), $e->getMessage()))->code(200);
        }

        return parent::render($request, $e);
    }
}
