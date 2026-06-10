<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\common\web\BusinessException;
use app\common\web\ResultCode;
use app\model\PurchaseRecord;
use app\model\RechargeRecord;
use app\model\SystemConfig;
use app\model\User;

final class PaymentController extends BaseController
{
    protected bool $requireAuth = true;

    const PACKAGES = [
        'single'  => ['name' => '1个智能体',       'agents' => 1, 'price' => 799],
        'double'  => ['name' => '2个智能体',       'agents' => 2, 'price' => 1399],
        'triple'  => ['name' => '3个智能体',       'agents' => 3, 'price' => 1999],
        'add_one' => ['name' => '加购1个智能体',    'agents' => 1, 'price' => 799],
    ];

    public function balance()
    {
        $user = User::find($this->getAuthUserId());
        return $this->success([
            'balance' => (float)$user->balance,
            'agent_quota' => (int)$user->agent_quota,
            'agent_used' => (int)$user->agent_used,
            'agent_available' => max(0, (int)$user->agent_quota - (int)$user->agent_used),
        ]);
    }

    public function trc20Address()
    {
        $address = SystemConfig::getValue('trc20_address', '');
        $minAmount = SystemConfig::getValue('trc20_min_amount', '50');
        return $this->success([
            'address' => $address,
            'min_amount' => (int)$minAmount,
        ]);
    }

    public function recharge()
    {
        $params = $this->request->post();
        $amount = (float)($params['amount'] ?? 0);

        if ($amount <= 0) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '请填写有效充值金额');
        }

        $minAmount = (int)SystemConfig::getValue('trc20_min_amount', '50');
        if ($amount < $minAmount) {
            throw new BusinessException(ResultCode::PARAM_ERROR, "最低充值金额为 {$minAmount} USD");
        }

        $address = SystemConfig::getValue('trc20_address', '');
        if (empty($address)) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '平台尚未配置TRC20收款地址');
        }

        // 保存充值记录（status=pending，无需tx_hash）
        $record = new RechargeRecord();
        $record->user_id = $this->getAuthUserId();
        $record->amount = $amount;
        $record->status = 'pending';
        $record->save();

        // 构建 tronusdt 回调地址
        $callbackUrl = $this->request->domain() . '/api/v1/payment/tronusdt-callback/' . $record->id;

        // 构建 tronusdt payui 支付页面 URL
        $payuiUrl = 'https://tronusdt.xyz/?' . http_build_query([
            'way'     => 'payui',
            'name'    => $address,
            'type'    => 'usdt',
            'product' => '充值 ' . $amount . ' USD',
            'value'   => $amount,
            'jump'    => $callbackUrl,
            'auto'    => 'async',
            'about'   => '$' . $amount . '.00',
        ]);

        return $this->success([
            'id'      => $record->id,
            'status'  => 'pending',
            'pay_url' => $payuiUrl,
        ]);
    }

    /**
     * tronusdt 异步回调（无需登录认证）
     * GET /api/v1/payment/tronusdt-callback/<id>
     */
    public function tronusdtCallback($id)
    {
        $record = RechargeRecord::find((int)$id);
        if (!$record) {
            return response('not found', 404);
        }

        // 防重：已确认的订单不再处理
        if ($record->status === 'confirmed') {
            return response('ok', 200);
        }

        // tronusdt 可能在 5min/15min 多次回调，只有 pending 状态的才确认
        if ($record->status !== 'pending') {
            return response('already processed', 200);
        }

        // 确认充值：加余额
        $user = User::find($record->user_id);
        if ($user) {
            $user->balance = (float)$user->balance + (float)$record->amount;
            $user->save();
        }

        $record->status = 'confirmed';
        $record->save();

        return response('ok', 200);
    }

    public function rechargeRecords()
    {
        $userId = $this->getAuthUserId();
        $list = RechargeRecord::where('user_id', $userId)
            ->order('id', 'desc')
            ->limit(50)
            ->select();
        return $this->success([
            'list' => $list->toArray(),
            'total' => count($list),
        ]);
    }

    public function purchase()
    {
        $params = $this->request->post();
        $packageType = $params['package_type'] ?? '';

        if (!isset(self::PACKAGES[$packageType])) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '无效套餐类型');
        }

        $package = self::PACKAGES[$packageType];
        $price = $package['price'];
        $agents = $package['agents'];

        $userId = $this->getAuthUserId();
        $user = User::find($userId);

        if ((float)$user->balance < $price) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '余额不足，请先充值');
        }

        $user->balance = (float)$user->balance - $price;
        $user->agent_quota = (int)$user->agent_quota + $agents;
        $user->save();

        $record = new PurchaseRecord();
        $record->user_id = $userId;
        $record->package_name = $package['name'];
        $record->agent_count = $agents;
        $record->amount = $price;
        $record->save();

        return $this->success([
            'balance' => (float)$user->balance,
            'agent_quota' => (int)$user->agent_quota,
            'agent_used' => (int)$user->agent_used,
            'agent_available' => max(0, (int)$user->agent_quota - (int)$user->agent_used),
        ]);
    }

    public function purchaseRecords()
    {
        $userId = $this->getAuthUserId();
        $list = PurchaseRecord::where('user_id', $userId)
            ->order('id', 'desc')
            ->limit(50)
            ->select();
        return $this->success([
            'list' => $list->toArray(),
            'total' => count($list),
        ]);
    }
}
