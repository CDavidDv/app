<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contribution;
use App\Models\Guest;
use App\Models\MediaFile;
use App\Models\Participant;
use App\Services\MediaService;
use App\Support\Present;
use App\Support\Text;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ContentController extends Controller
{
    public function overview(): JsonResponse
    {
        $byType = Contribution::query()->select('type', DB::raw('count(*) as total'))->groupBy('type')->pluck('total', 'type');

        return response()->json([
            'participants' => Participant::count(),
            'contributions' => Contribution::count(),
            'hidden' => Contribution::where('is_hidden', true)->count(),
            'favorites' => Contribution::where('is_favorite', true)->count(),
            'by_type' => $byType,
            'media' => [
                'photos' => MediaFile::where('purpose', 'contribution')->where('kind', 'image')->whereNotNull('contribution_id')->count(),
                'videos' => MediaFile::where('purpose', 'contribution')->where('kind', 'video')->whereNotNull('contribution_id')->count(),
                'bytes' => (int) MediaFile::sum('size'),
            ],
            'guests' => [
                'total' => Guest::count(),
                'participated' => Guest::whereHas('participants')->count(),
            ],
            'latest' => Contribution::with('participant')->latest()->limit(6)->get()->map(fn ($c) => [
                'id' => $c->id,
                'type' => $c->type,
                'participant' => $c->participant?->publicName(),
                'created_at' => $c->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function contributions(Request $request): JsonResponse
    {
        $q = Contribution::query()->with(['media', 'answers', 'participant'])->latest();
        if ($t = $request->query('type')) {
            $q->where('type', $t);
        }
        if ($p = $request->query('participant')) {
            $q->where('participant_id', $p);
        }
        if ($request->boolean('favorites')) {
            $q->where('is_favorite', true);
        }
        if ($request->query('hidden') === '1') {
            $q->where('is_hidden', true);
        }

        return response()->json($q->get()->map(fn ($c) => Present::contribution($c, true) + [
            'participant' => $c->participant ? Present::participant($c->participant, true) : null,
        ]));
    }

    public function updateContribution(Request $request, string $id): JsonResponse
    {
        $c = Contribution::findOrFail($id);
        $v = $request->validate([
            'is_favorite' => ['sometimes', 'boolean'],
            'is_hidden' => ['sometimes', 'boolean'],
            'title' => ['sometimes', 'nullable', 'string', 'max:200'],
            'body' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'signature' => ['sometimes', 'nullable', 'string', 'max:120'],
            'artist' => ['sometimes', 'nullable', 'string', 'max:200'],
            'link' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);
        if (array_key_exists('link', $v)) {
            if ($c->type !== 'song') {
                unset($v['link']);
            } else {
                $v['link'] = \App\Support\SongLink::normalize($v['link']);
                if (! $v['link']) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['link' => 'Ese enlace no parece válido.']);
                }
            }
        }
        foreach (['title' => 200, 'signature' => 120, 'artist' => 200] as $f => $max) {
            if (array_key_exists($f, $v)) {
                $v[$f] = Text::line($v[$f], $max);
            }
        }
        if (array_key_exists('body', $v)) {
            $v['body'] = Text::clean($v['body'], 20000);
        }
        $c->update($v);

        return response()->json(Present::contribution($c->load(['media', 'answers']), true));
    }

    public function destroyContribution(string $id): JsonResponse
    {
        $c = Contribution::findOrFail($id);
        DB::transaction(function () use ($c) {
            $c->media()->get()->each->delete();
            $c->delete();
        });

        return response()->json(['ok' => true]);
    }

    public function participants(): JsonResponse
    {
        $list = Participant::withCount('contributions')->with('accessToken')->latest()->get();

        return response()->json($list->map(fn ($p) => Present::participant($p, true) + [
            'contributions_count' => $p->contributions_count,
            'personal_link' => $p->accessToken?->plain() ? url('/mi-recuerdo/'.$p->accessToken->plain()) : null,
        ]));
    }

    public function updateParticipant(Request $request, string $id): JsonResponse
    {
        $p = Participant::findOrFail($id);
        $v = $request->validate([
            'name' => ['sometimes', 'string', 'min:1', 'max:120'],
            'display_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'relationship' => ['sometimes', Rule::in(array_keys(Participant::RELATIONSHIPS))],
            'relationship_other' => ['sometimes', 'nullable', 'string', 'max:60'],
            'is_hidden' => ['sometimes', 'boolean'],
            'guest_id' => ['sometimes', 'nullable', 'exists:guests,id'],
        ]);
        foreach (['name' => 120, 'display_name' => 120, 'relationship_other' => 60] as $f => $max) {
            if (array_key_exists($f, $v)) {
                $v[$f] = Text::line($v[$f], $max);
            }
        }
        if (array_key_exists('name', $v) && ! $v['name']) {
            unset($v['name']);
        }
        $p->update($v);

        return response()->json(Present::participant($p, true));
    }

    public function destroyParticipant(string $id): JsonResponse
    {
        $p = Participant::findOrFail($id);
        DB::transaction(function () use ($p) {
            $p->media()->get()->each->delete();
            $p->accessToken?->delete();
            $p->delete();
        });

        return response()->json(['ok' => true]);
    }

    public function download(MediaService $media, string $id): Response
    {
        return $media->respond(MediaFile::findOrFail($id), false, true);
    }
}
