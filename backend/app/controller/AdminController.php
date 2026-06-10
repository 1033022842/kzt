<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\model\RechargeRecord;
use app\model\StreamerConfig;
use app\model\SystemConfig;
use app\model\User;

final class AdminController extends BaseController
{
    protected bool $requireAuth = true;

    private function checkAdmin()
    {
        $userId = $this->getAuthUserId();
        $user = User::find($userId);
        if (!$user || $user->role !== 'admin') {
            throw new BusinessException(ResultCode::ACCESS_TOKEN_INVALID, '无权限访问');
        }
    }

    public function users()
    {
        $this->checkAdmin();
        $list = User::field('id,username,nickname,email,role,status,token_version,agent_quota,agent_used,balance,create_time')
            ->order('id', 'asc')->select()->toArray();
        return $this->success(['list' => $list, 'total' => count($list)]);
    }

    public function toggleUserStatus($id)
    {
        $this->checkAdmin();
        $user = User::find($id);
        if (!$user) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '用户不存在');
        }
        $user->status = $user->status === 1 ? 0 : 1;
        $user->save();
        return $this->success(['status' => $user->status]);
    }

    public function kickUser($id)
    {
        $this->checkAdmin();
        $user = User::find($id);
        if (!$user) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '用户不存在');
        }
        if ($user->role === 'admin') {
            throw new BusinessException(ResultCode::PARAM_ERROR, '不能踢出管理员');
        }
        $user->token_version = $user->token_version + 1;
        $user->save();
        return $this->success(['token_version' => $user->token_version]);
    }

    public function assignConfig($id)
    {
        $this->checkAdmin();
        $targetUserId = $this->request->post('user_id');
        if (!$targetUserId) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '请指定目标用户');
        }
        $targetUser = User::find($targetUserId);
        if (!$targetUser) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '目标用户不存在');
        }
        $config = StreamerConfig::find($id);
        if (!$config) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '配置不存在');
        }
        $config->user_id = $targetUserId;
        $config->save();
        return $this->success();
    }

    public function setUserQuota($id)
    {
        $this->checkAdmin();
        $user = User::find($id);
        if (!$user) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '用户不存在');
        }
        $quota = (int)$this->request->put('agent_quota', 0);
        if ($quota < 1) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '配额至少为1');
        }
        $user->agent_quota = $quota;
        $user->save();
        return $this->success(['agent_quota' => $user->agent_quota]);
    }

    public function usersConfigs()
    {
        $this->checkAdmin();

        $params = $this->request->get();
        $pageSize = (int) ($params['pageSize'] ?? 20);
        $pageNum = (int) ($params['pageNum'] ?? 1);

        $query = StreamerConfig::with(['user'])
            ->order('update_time', 'desc');

        $paginator = $query->paginate([
            'list_rows' => $pageSize,
            'page' => $pageNum,
        ]);

        $list = [];
        foreach ($paginator->items() as $item) {
            $data = $item->toArray();
            if ($item->user) {
                $data['username'] = $item->user->username;
                $data['nickname'] = $item->user->nickname;
                $data['user_info'] = [
                    'username' => $item->user->username,
                    'nickname' => $item->user->nickname,
                ];
            }
            unset($data['user']);
            $list[] = $data;
        }

        return $this->successPaginate($list, $paginator->total());
    }

    public function toggleTrainingStatus()
    {
        $this->checkAdmin();

        $params = $this->request->put();
        $configId = $params['id'] ?? null;
        $newStatus = $params['training_status'] ?? null;

        if (!$configId || !in_array($newStatus, ['normal', 'training'], true)) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '参数错误');
        }

        $config = StreamerConfig::find($configId);
        if (!$config) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '配置不存在');
        }

        $config->training_status = $newStatus;
        $config->save();

        return $this->success(['training_status' => $config->training_status]);
    }

    public function recharges()
    {
        $this->checkAdmin();
        $records = RechargeRecord::with(['user'])
            ->order('id', 'desc')
            ->limit(200)
            ->select();

        $list = [];
        foreach ($records as $r) {
            $data = $r->toArray();
            $data['username'] = $r->user->username ?? '-';
            $data['nickname'] = $r->user->nickname ?? '';
            unset($data['user']);
            $list[] = $data;
        }
        return $this->success(['list' => $list, 'total' => count($list)]);
    }

    public function confirmRecharge($id)
    {
        $this->checkAdmin();
        $record = RechargeRecord::find($id);
        if (!$record || $record->status !== 'pending') {
            throw new BusinessException(ResultCode::PARAM_ERROR, '该充值记录不存在或已处理');
        }
        $user = User::find($record->user_id);
        if (!$user) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '用户不存在');
        }
        $record->status = 'confirmed';
        $record->save();
        $user->balance = (float)$user->balance + (float)$record->amount;
        $user->save();
        return $this->success(['status' => 'confirmed']);
    }

    public function rejectRecharge($id)
    {
        $this->checkAdmin();
        $record = RechargeRecord::find($id);
        if (!$record || $record->status !== 'pending') {
            throw new BusinessException(ResultCode::PARAM_ERROR, '该充值记录不存在或已处理');
        }
        $remark = trim($this->request->put('remark', ''));
        $record->status = 'rejected';
        $record->remark = $remark ?: '审核驳回';
        $record->save();
        return $this->success(['status' => 'rejected']);
    }

    public function trc20Config()
    {
        $this->checkAdmin();
        $address = trim($this->request->put('address', ''));
        $minAmount = (int)$this->request->put('min_amount', 50);
        SystemConfig::setValue('trc20_address', $address);
        SystemConfig::setValue('trc20_min_amount', (string)$minAmount);
        return $this->success(['address' => $address, 'min_amount' => $minAmount]);
    }
}
