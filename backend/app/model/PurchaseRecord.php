<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

final class PurchaseRecord extends Model
{
    protected $name = 'purchase_record';
    protected $pk = 'id';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_time';

    protected $type = [
        'id' => 'integer',
        'user_id' => 'integer',
        'agent_count' => 'integer',
        'amount' => 'float',
        'create_time' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
