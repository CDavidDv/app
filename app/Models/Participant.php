<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Participant extends Model
{
    use HasUlids;

    public const RELATIONSHIPS = [
        'family' => 'Familia',
        'friend' => 'Amistad',
        'partner' => 'Pareja',
        'work' => 'Compañero de trabajo',
        'school' => 'Escuela',
        'other' => 'Otra',
    ];

    protected $fillable = ['name', 'relationship', 'relationship_other', 'display_name', 'guest_id', 'access_token_id', 'is_hidden'];

    protected function casts(): array
    {
        return ['is_hidden' => 'boolean'];
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(MediaFile::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function accessToken(): BelongsTo
    {
        return $this->belongsTo(AccessToken::class);
    }

    public function relationshipLabel(): string
    {
        if ($this->relationship === 'other' && $this->relationship_other) {
            return $this->relationship_other;
        }

        return self::RELATIONSHIPS[$this->relationship] ?? 'Alguien especial';
    }

    public function publicName(): string
    {
        return $this->display_name ?: $this->name;
    }
}
