<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedReport extends Model
{
    protected $fillable = ['user_id', 'organization_id', 'name', 'configuration'];

    protected function casts(): array
    {
        return ['configuration' => 'array'];
    }
}
