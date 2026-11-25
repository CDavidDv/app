<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventSetting extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['birthday_password'];

    protected function casts(): array
    {
        return [
            'birthday_date' => 'date',
            'submissions_close_at' => 'datetime',
            'memories_enabled' => 'boolean',
            'submissions_open' => 'boolean',
            'music_enabled' => 'boolean',
            'age' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::query()->first() ?? static::create(static::defaults());
    }

    public static function defaults(): array
    {
        return [
            // Datos personales: vienen del .env (nunca se guardan en el repositorio).
            'celebrant_name' => config('pedacito.event.celebrant_name'),
            'celebrant_short_name' => config('pedacito.event.celebrant_short_name'),
            'age' => config('pedacito.event.age'),
            'birthday_date' => config('pedacito.event.birthday_date'),
            'submissions_close_at' => config('pedacito.event.submissions_close_at'),
            'cover_title' => 'Un pedacito de todos',
            'cover_message' => 'Hay personas que hacen nuestra vida más bonita. Hoy quisimos reunir un poquito de todo el cariño que te tienen.',
            'invite_title' => 'Queremos regalarle algo que pueda conservar para siempre',
            'invite_message' => 'Estamos preparando una sorpresa muy especial. Queremos reunir mensajes, cartas, fotografías y videos de las personas que forman parte de su vida, para que pueda descubrir cuánto la quieren y guardar estos recuerdos para siempre.',
            'memories_enabled' => false,
            'submissions_open' => true,
        ];
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'cover_media_id');
    }

    public function music(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'music_media_id');
    }

    public function acceptingSubmissions(): bool
    {
        if (! $this->submissions_open) {
            return false;
        }

        return $this->submissions_close_at === null || now()->lt($this->submissions_close_at);
    }

    public function hasBirthdayPassword(): bool
    {
        return ! empty($this->birthday_password);
    }
}
