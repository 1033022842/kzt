<?php

declare(strict_types=1);

namespace app;

use app\common\exception\BusinessException;
use app\common\web\Result;
use app\common\web\ResultCode;
use think\App;
use think\response\Json;

abstract class BaseController
{
    protected App $app;

    protected $request;

    protected bool $requireAuth = false;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $app->request;
        $this->initialize();
    }

    protected function initialize(): void
    {
        if ($this->requireAuth && $this->getAuthUserId() <= 0) {
            throw new BusinessException(ResultCode::ACCESS_TOKEN_INVALID);
        }
    }

    protected function success(mixed $data = null, string $msg = ''): Json
    {
        return json(Result::success($data, $msg ?: ResultCode::getMsg(ResultCode::SUCCESS)));
    }

    protected function successPaginate(array $list, int $total): Json
    {
        return json(Result::page($list, $total));
    }

    protected function fail(string $code, string $msg = ''): Json
    {
        return json(Result::failedWith($code, $msg));
    }

    protected function validate(array $data, string $validate): void
    {
        if (str_contains($validate, '.')) {
            [$class, $scene] = explode('.', $validate);
            $v = (new $class())->scene($scene);
        } else {
            $v = new $validate();
        }

        if (!$v->check($data)) {
            throw new BusinessException(ResultCode::PARAM_ERROR, $v->getError());
        }
    }

    protected function getAuthUserId(): int
    {
        return (int) ($this->request->userId ?? 0);
    }
}
