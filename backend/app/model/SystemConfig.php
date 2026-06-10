<?php

declare(strict_types=1);

namespace app\model;

use think\Model;

final class SystemConfig extends Model
{
    protected $name = 'system_config';
    protected $pk = 'id';
    protected $autoWriteTimestamp = false;

    protected $type = [
        'id' => 'integer',
    ];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $row = self::where('key', $key)->find();
        return $row ? $row->value : $default;
    }

    public static function setValue(string $key, mixed $value): void
    {
        self::where('key', $key)->update(['value' => $value]);
    }
}
