<?php

declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use app\model\StreamerConfig;
use app\model\User;
use app\validate\StreamerValidate;

final class StreamerController extends BaseController
{
    protected bool $requireAuth = true;

    private array $allowedAvatarExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public function configs()
    {
        $userId = $this->getAuthUserId();
        $configs = StreamerConfig::where('user_id', $userId)
            ->order('update_time', 'desc')
            ->select();

        return $this->success($configs->toArray());
    }

    public function detail($id)
    {
        $userId = $this->getAuthUserId();
        $config = StreamerConfig::where('id', $id)
            ->where('user_id', $userId)
            ->find();

        if (!$config) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '配置不存在');
        }

        return $this->success($config->toArray());
    }

    public function create()
    {
        $params = $this->request->post();
        $this->validate($params, StreamerValidate::class . '.create');

        $userId = $this->getAuthUserId();
        $user = User::find($userId);
        if ((int)$user->agent_used >= (int)$user->agent_quota) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '智能体配额已用完，请在充值中心购买套餐');
        }

        $configData = $params['config_data'];
        $avatar = (string) ($params['avatar'] ?? ($configData['avatar'] ?? ''));
        $configData['avatar'] = $avatar;

        $config = new StreamerConfig();
        $config->user_id = $userId;
        $config->name = $params['name'];
        $config->avatar = $avatar;
        $config->config_data = $configData;
        $config->system_prompt = $params['system_prompt'];
        $config->status = 1;
        $config->save();

        $user->agent_used = (int)$user->agent_used + 1;
        $user->save();

        return $this->success(['id' => $config->id]);
    }

    public function update()
    {
        $params = $this->request->put();
        $this->validate($params, StreamerValidate::class . '.update');
        $configData = $params['config_data'];
        $avatar = (string) ($params['avatar'] ?? ($configData['avatar'] ?? ''));
        $configData['avatar'] = $avatar;

        $userId = $this->getAuthUserId();
        $config = StreamerConfig::where('id', $params['id'])
            ->where('user_id', $userId)
            ->find();

        if (!$config) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '配置不存在');
        }

        if ($config->training_status === 'training') {
            throw new BusinessException(ResultCode::PARAM_ERROR, '该智能体正在训练中，无法编辑');
        }

        $config->name = $params['name'];
        $config->avatar = $avatar;
        $config->config_data = $configData;
        $config->system_prompt = $params['system_prompt'];
        $config->save();

        return $this->success();
    }

    public function uploadAvatar()
    {
        $file = $this->request->file('file');
        if (!$file) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '请上传头像图片');
        }

        $extension = strtolower($file->getOriginalExtension() ?: $file->extension());
        if (!in_array($extension, $this->allowedAvatarExtensions, true)) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '仅支持 jpg、jpeg、png、gif、webp 格式');
        }

        if ($file->getSize() > 5 * 1024 * 1024) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '图片大小不能超过 5MB');
        }

        $relativeDir = 'uploads/avatars/' . date('Ymd');
        $saveDir = root_path() . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'avatars' . DIRECTORY_SEPARATOR . date('Ymd');
        if (!is_dir($saveDir)) {
            mkdir($saveDir, 0777, true);
        }

        $filename = date('His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        $file->move($saveDir, $filename);

        $relativePath = $relativeDir . '/' . $filename;
        $url = rtrim($this->request->domain(), '/') . '/' . $relativePath;

        return $this->success([
            'url' => $url,
            'path' => $relativePath,
        ]);
    }

    public function delete($id)
    {
        $userId = $this->getAuthUserId();
        $config = StreamerConfig::where('id', $id)
            ->where('user_id', $userId)
            ->find();

        if (!$config) {
            throw new BusinessException(ResultCode::PARAM_ERROR, '配置不存在');
        }

        $config->delete();

        $user = User::find($userId);
        $user->agent_used = max(0, (int)$user->agent_used - 1);
        $user->save();

        return $this->success();
    }

    // 预留：GET /api/v1/streamer/configs/:id/relationships
    // 根据智能体ID查询其 config_data.relationships，返回关联智能体的 name/avatar/agentType 等关键信息
}
