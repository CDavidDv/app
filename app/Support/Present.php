<?php

namespace App\Support;

use App\Models\Contribution;
use App\Models\EventSetting;
use App\Models\MediaFile;
use App\Models\Participant;

/** Serialización de modelos para el frontend (nunca expone rutas internas de archivos). */
class Present
{
    public static function media(?MediaFile $m): ?array
    {
        if (! $m) {
            return null;
        }

        return [
            'id' => $m->id,
            'kind' => $m->kind,
            'mime' => $m->mime,
            'size' => $m->size,
            'width' => $m->width,
            'height' => $m->height,
            'name' => $m->original_name,
            'url' => url('/api/media/'.$m->id),
            'thumb' => $m->thumb_path ? url('/api/media/'.$m->id.'/thumb') : url('/api/media/'.$m->id),
        ];
    }

    public static function contribution(Contribution $c, bool $admin = false): array
    {
        $data = [
            'id' => $c->id,
            'type' => $c->type,
            'title' => $c->title,
            'artist' => $c->artist,
            'link' => $c->link,
            'platform' => SongLink::platform($c->link),
            'body' => $c->body,
            'signature' => $c->signature,
            'prompt' => $c->prompt,
            'media' => $c->media->map(fn ($m) => self::media($m))->values(),
            'answers' => $c->answers->map(fn ($a) => [
                'question_id' => $a->question_id,
                'question' => $a->question_text,
                'answer' => $a->answer,
            ])->values(),
            'created_at' => $c->created_at?->toIso8601String(),
            'updated_at' => $c->updated_at?->toIso8601String(),
            'is_favorite' => $c->is_favorite,
        ];
        if ($admin) {
            $data['is_hidden'] = $c->is_hidden;
            $data['participant_id'] = $c->participant_id;
        }

        return $data;
    }

    public static function participant(Participant $p, bool $admin = false): array
    {
        $data = [
            'id' => $p->id,
            'name' => $p->publicName(),
            'relationship' => $p->relationship,
            'relationship_label' => $p->relationshipLabel(),
        ];
        if ($admin) {
            $data += [
                'real_name' => $p->name,
                'display_name' => $p->display_name,
                'relationship_other' => $p->relationship_other,
                'is_hidden' => $p->is_hidden,
                'guest_id' => $p->guest_id,
                'created_at' => $p->created_at?->toIso8601String(),
            ];
        }

        return $data;
    }

    public static function event(EventSetting $s, bool $admin = false): array
    {
        $data = [
            'celebrant_name' => $s->celebrant_name,
            'celebrant_short_name' => $s->celebrant_short_name,
            'age' => $s->age,
            'birthday_date' => $s->birthday_date?->toDateString(),
            'cover_title' => $s->cover_title,
            'cover_message' => $s->cover_message,
            'invite_title' => $s->invite_title,
            'invite_message' => $s->invite_message,
            'cover' => self::media($s->cover),
            'music' => $s->music_enabled ? self::media($s->music) : null,
            'submissions_close_at' => $s->submissions_close_at?->toIso8601String(),
            'accepting' => $s->acceptingSubmissions(),
        ];
        if ($admin) {
            $data += [
                'memories_enabled' => $s->memories_enabled,
                'submissions_open' => $s->submissions_open,
                'has_birthday_password' => $s->hasBirthdayPassword(),
                'music_enabled' => $s->music_enabled,
                'music_file' => self::media($s->music),
            ];
        }

        return $data;
    }
}
