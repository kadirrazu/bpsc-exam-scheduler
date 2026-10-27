<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = ['code', 'name', 'sort_order', 'for_exams', 'for_users'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'for_exams' => 'boolean', 'for_users' => 'boolean', 'version' => 'integer'];
    }
}
