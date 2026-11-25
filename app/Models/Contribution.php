<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contribution extends Model
{
    use HasUlids;

    public const TYPES = ['letter', 'video', 'photo', 'message', 'secret', 'questions', 'song'];

    protected $fillable = ['type', 'title', 'artist', 'link', 'body', 'signature', 'prompt', 'is_favorite', 'is_hidden'];

    protected function casts(): array
    {
        return ['is_favorite' => 'boolean', 'is_hidden' => 'boolean'];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(MediaFile::class)->orderBy('position');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class)->orderBy('position');
    }

    public function scopeVisible(Builder $q): Builder
    {
        return $q->where('is_hidden', false)
            ->whereHas('participant', fn ($p) => $p->where('is_hidden', false));
    }
}
