<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

final class User extends Model
{
    protected $name = 'sys_user';

    protected $pk = 'id';

    protected $hidden = ['password'];

    protected $type = [
        'id' => 'integer',
        'status' => 'integer',
        'token_version' => 'integer',
        'balance' => 'float',
        'agent_quota' => 'integer',
        'agent_used' => 'integer',
        'create_time' => 'datetime',
        'update_time' => 'datetime',
    ];
}
