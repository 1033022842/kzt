<?php

declare(strict_types=1);

namespace app\validate;

use think\Validate;

final class StreamerValidate extends Validate
{
    protected $rule = [
        'name' => 'require|max:100',
        'avatar' => 'max:500',
        'config_data' => 'require|array',
        'system_prompt' => 'require',
    ];

    protected $message = [
        'name.require' => '配置名称不能为空',
        'name.max' => '配置名称最多100个字符',
        'avatar.max' => '头像地址长度不能超过500个字符',
        'config_data.require' => '配置数据不能为空',
        'config_data.array' => '配置数据必须是数组',
        'system_prompt.require' => '提示词不能为空',
    ];

    protected $scene = [
        'create' => ['name', 'avatar', 'config_data', 'system_prompt'],
        'update' => ['name', 'avatar', 'config_data', 'system_prompt'],
    ];
}
