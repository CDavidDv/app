<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Answer extends Model
{
    protected $fillable = ['question_id', 'question_text', 'answer', 'position'];

    public function contribution(): BelongsTo
    {
        return $this->belongsTo(Contribution::class);
    }
}
