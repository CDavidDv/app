<?php

namespace App\Http\Controllers;

use App\Models\EventSetting;
use App\Models\MediaFile;
use App\Services\MediaService;
use App\Support\Access;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Los archivos nunca son públicos: cada petición se autoriza según quién la hace.
 */
class MediaController extends Controller
{
    public function __construct(private MediaService $media)
    {
    }

    public function show(Request $request, string $id, ?string $variant = null): Response
    {
        $m = MediaFile::with('contribution.participant')->findOrFail($id);
        abort_unless($this->canView($request, $m), 404);

        return $this->media->respond($m, $variant === 'thumb');
    }

    private function canView(Request $request, MediaFile $m): bool
    {
        if (Access::isAdmin($request)) {
            return true;
        }

        $settings = EventSetting::current();

        // Portada: visible para la cumpleañera y para quien tenga invitación.
        if ($m->purpose === 'cover' && $settings->cover_media_id === $m->id) {
            return Access::birthdayUnlocked($request) || Access::canParticipate($request);
        }
        if ($m->purpose === 'music' && $settings->music_media_id === $m->id) {
            return Access::birthdayUnlocked($request);
        }

        // Cada participante sólo ve sus propios archivos.
        $p = Access::participant($request);
        if ($p && $m->participant_id === $p->id) {
            return true;
        }

        // La cumpleañera ve lo que esté publicado y visible.
        if (Access::birthdayUnlocked($request) && $settings->memories_enabled) {
            $c = $m->contribution;

            return $c && ! $c->is_hidden && $c->participant && ! $c->participant->is_hidden;
        }

        return false;
    }
}
