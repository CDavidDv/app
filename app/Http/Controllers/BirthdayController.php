<?php

namespace App\Http\Controllers;

use App\Models\AccessToken;
use App\Models\Contribution;
use App\Models\EventSetting;
use App\Models\Participant;
use App\Support\Access;
use App\Support\Present;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BirthdayController extends Controller
{
    /** Todo lo que la cumpleañera puede ver, agrupado por persona. */
    public function memories(Request $request): JsonResponse
    {
        $admin = Access::isAdmin($request);
        abort_unless($admin || Access::birthdayUnlocked($request), 403);

        $settings = EventSetting::current()->load(['cover', 'music']);
        $event = Present::event($settings);

        if (! $settings->memories_enabled && ! $admin) {
            return response()->json(['ready' => false, 'event' => $event]);
        }

        $participants = Participant::query()
            ->where('is_hidden', false)
            ->whereHas('contributions', fn ($q) => $q->where('is_hidden', false))
            ->with(['contributions' => fn ($q) => $q->where('is_hidden', false)->with(['media', 'answers'])->oldest()])
            ->oldest()
            ->get();

        $people = $participants->map(function (Participant $p) {
            $contributions = $p->contributions;
            $photo = $contributions->where('type', 'photo')->flatMap->media->first();

            return Present::participant($p) + [
                'photo' => Present::media($photo),
                'count' => $contributions->count(),
                'contributions' => $contributions->map(fn (Contribution $c) => Present::contribution($c))->values(),
            ];
        })->values();

        $total = $people->sum('count');
        $token = AccessToken::where('type', AccessToken::BIRTHDAY)->where('active', true)->latest('id')->first();

        return response()->json([
            'ready' => true,
            'preview' => $admin && ! $settings->memories_enabled,
            'share_url' => $token?->plain() ? url('/para-ti/'.$token->plain()) : null,
            'event' => $event,
            'stats' => [
                'people' => $people->count(),
                'memories' => $total,
            ],
            'people' => $people,
        ]);
    }
}
