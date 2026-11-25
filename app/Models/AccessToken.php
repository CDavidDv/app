<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AccessToken extends Model
{
    public const INVITE = 'invite';
    public const GUEST_INVITE = 'guest_invite';
    public const BIRTHDAY = 'birthday';
    public const PARTICIPANT = 'participant';

    protected $guarded = ['id'];

    protected $hidden = ['token_hash', 'token_encrypted'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'token_encrypted' => 'encrypted',
        ];
    }

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * Crea un token aleatorio (~256 bits). Sólo se guarda su hash para buscarlo
     * y una copia cifrada con APP_KEY para que el admin pueda volver a copiar el enlace.
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(string $type, array $attributes = []): array
    {
        $plain = Str::random(43);
        $token = static::create(array_merge($attributes, [
            'type' => $type,
            'token_hash' => static::hash($plain),
            'token_encrypted' => $plain,
        ]));

        return [$token, $plain];
    }

    public static function findValid(?string $plain, string|array $types): ?self
    {
        if ($plain === null || strlen($plain) < 20 || strlen($plain) > 100) {
            return null;
        }

        $token = static::query()
            ->where('token_hash', static::hash($plain))
            ->whereIn('type', (array) $types)
            ->where('active', true)
            ->first();

        if (! $token || ($token->expires_at && $token->expires_at->isPast())) {
            return null;
        }

        return $token;
    }

    public function touchUse(): void
    {
        $this->forceFill(['last_used_at' => now(), 'uses' => $this->uses + 1])->saveQuietly();
    }

    public function plain(): ?string
    {
        return $this->token_encrypted;
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }
}
