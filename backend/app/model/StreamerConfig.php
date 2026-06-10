<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

final class StreamerConfig extends Model
{
    protected $name = 'streamer_config';

    protected $pk = 'id';

    protected $autoWriteTimestamp = true;

    protected $createTime = 'create_time';

    protected $updateTime = 'update_time';

    protected $type = [
        'id' => 'integer',
        'user_id' => 'integer',
        'avatar' => 'string',
        'config_data' => 'json',
        'status' => 'integer',
        'training_status' => 'string',
        'create_time' => 'datetime',
        'update_time' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
