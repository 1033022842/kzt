<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

final class RechargeRecord extends Model
{
    protected $name = 'recharge_record';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected $type = [
        'id' => 'integer',
        'user_id' => 'integer',
        'amount' => 'float',
        'create_time' => 'datetime',
        'update_time' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
