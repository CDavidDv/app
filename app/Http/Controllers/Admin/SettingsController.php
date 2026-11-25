<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessToken;
use App\Models\EventSetting;
use App\Models\MediaFile;
use App\Services\MediaService;
use App\Support\Present;
use App\Support\Text;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(Present::event(EventSetting::current()->load(['cover', 'music']), true));
    }

    public function update(Request $request): JsonResponse
    {
        $s = EventSetting::current();
        $v = $request->validate([
            'celebrant_name' => ['sometimes', 'required', 'string', 'max:190'],
            'celebrant_short_name' => ['sometimes', 'required', 'string', 'max:60'],
            'age' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:120'],
            'birthday_date' => ['sometimes', 'nullable', 'date'],
            'cover_title' => ['sometimes', 'nullable', 'string', 'max:190'],
            'cover_message' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'invite_title' => ['sometimes', 'nullable', 'string', 'max:300'],
            'invite_message' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'memories_enabled' => ['sometimes', 'boolean'],
            'submissions_open' => ['sometimes', 'boolean'],
            'submissions_close_at' => ['sometimes', 'nullable', 'date'],
            'music_enabled' => ['sometimes', 'boolean'],
            'birthday_password' => ['sometimes', 'nullable', 'string', 'min:4', 'max:100'],
            'remove_birthday_password' => ['sometimes', 'boolean'],
        ]);

        foreach (['celebrant_name' => 190, 'celebrant_short_name' => 60, 'cover_title' => 190, 'invite_title' => 300] as $f => $max) {
            if (array_key_exists($f, $v)) {
                $v[$f] = Text::line($v[$f], $max);
            }
        }
        foreach (['cover_message', 'invite_message'] as $f) {
            if (array_key_exists($f, $v)) {
                $v[$f] = Text::clean($v[$f], 3000);
            }
        }
        if (array_key_exists('submissions_close_at', $v) && $v['submissions_close_at']) {
            $v['submissions_close_at'] = Carbon::parse($v['submissions_close_at'])->setTimezone(config('app.timezone'));
        }
        if (! empty($v['remove_birthday_password'])) {
            $v['birthday_password'] = null;
        } elseif (! empty($v['birthday_password'])) {
            $v['birthday_password'] = Hash::make($v['birthday_password']);
        } else {
            unset($v['birthday_password']);
        }
        unset($v['remove_birthday_password']);

        $s->update($v);

        return $this->show();
    }

    public function uploadCover(Request $request, MediaService $media): JsonResponse
    {
        $max = config('pedacito.limits.image_mb') * 1024;
        $request->validate(['file' => ['required', 'file', 'max:'.$max, 'mimes:jpg,jpeg,png,webp']]);
        $f = $request->file('file');
        $m = $media->storeLocalFile($f->getRealPath(), 'image', $f->getClientOriginalExtension(), $f->getClientOriginalName(), null, 'cover');

        $s = EventSetting::current();
        $old = $s->cover_media_id;
        $s->update(['cover_media_id' => $m->id]);
        if ($old) {
            MediaFile::whereKey($old)->where('purpose', 'cover')->get()->each->delete();
        }

        return $this->show();
    }

    public function uploadMusic(Request $request, MediaService $media): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:30720', 'mimes:mp3,m4a,ogg,mp4']]);
        $f = $request->file('file');
        $m = $media->storeLocalFile($f->getRealPath(), 'audio', $f->getClientOriginalExtension(), $f->getClientOriginalName(), null, 'music');

        $s = EventSetting::current();
        $old = $s->music_media_id;
        $s->update(['music_media_id' => $m->id]);
        if ($old) {
            MediaFile::whereKey($old)->where('purpose', 'music')->get()->each->delete();
        }

        return $this->show();
    }

    public function removeMedia(string $what): JsonResponse
    {
        $s = EventSetting::current();
        $field = $what === 'music' ? 'music_media_id' : 'cover_media_id';
        if ($id = $s->{$field}) {
            $s->update([$field => null]);
            MediaFile::whereKey($id)->get()->each->delete();
        }

        return $this->show();
    }

    public function links(): JsonResponse
    {
        $out = [];
        foreach ([AccessToken::INVITE => '/invitacion/', AccessToken::BIRTHDAY => '/para-ti/'] as $type => $prefix) {
            $t = AccessToken::where('type', $type)->where('active', true)->latest('id')->first();
            if (! $t) {
                [$t] = AccessToken::issue($type, ['label' => $type === AccessToken::INVITE ? 'Invitación general' : 'Enlace de la cumpleañera']);
            }
            $out[$type] = [
                'url' => url($prefix.$t->plain()),
                'uses' => $t->uses,
                'last_used_at' => $t->last_used_at?->toIso8601String(),
                'created_at' => $t->created_at?->toIso8601String(),
            ];
        }

        return response()->json($out);
    }

    public function regenerate(string $type): JsonResponse
    {
        abort_unless(in_array($type, [AccessToken::INVITE, AccessToken::BIRTHDAY], true), 404);
        AccessToken::where('type', $type)->update(['active' => false]);

        return $this->links();
    }
}
