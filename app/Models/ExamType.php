<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamType extends Model
{
    protected $fillable = ['code', 'name', 'sort_order', 'is_viva'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_viva' => 'boolean', 'version' => 'integer'];
    }
}
