<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable = ['text', 'position', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
