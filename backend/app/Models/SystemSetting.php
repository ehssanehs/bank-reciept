<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasUuids;

    protected $fillable = ['key', 'value', 'group', 'is_encrypted'];

    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
        ];
    }
}
