<?php

namespace App\Http\Controllers;

use App\Models\AccessToken;
use App\Models\Contribution;
use App\Models\EventSetting;
use App\Models\MediaFile;
use App\Models\Participant;
use App\Models\Question;
use App\Services\ContributionWriter;
use App\Support\Access;
use App\Support\Present;
use App\Support\Text;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ParticipantController extends Controller
{
    public function __construct(private ContributionWriter $writer)
    {
    }

    private function me(Request $request): Participant
    {
        $p = Access::participant($request);
        abort_unless($p, 401, 'Primero cuéntanos quién eres.');

        return $p;
    }

    private function ensureOpen(): void
    {
        if (! EventSetting::current()->acceptingSubmissions()) {
            throw ValidationException::withMessages([
                'closed' => 'La recepción de recuerdos ya cerró. ¡Gracias por tu cariño!',
            ]);
        }
    }

    private function identityRules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'relationship' => ['required', Rule::in(array_keys(Participant::RELATIONSHIPS))],
            'relationship_other' => ['nullable', 'required_if:relationship,other', 'string', 'max:60'],
            'display_name' => ['nullable', 'string', 'max:120'],
        ];
    }

    private function identityData(array $v): array
    {
        return [
            'name' => Text::line($v['name'], 120),
            'relationship' => $v['relationship'],
            'relationship_other' => $v['relationship'] === 'other' ? Text::line($v['relationship_other'] ?? null, 60) : null,
            'display_name' => Text::line($v['display_name'] ?? null, 120),
        ];
    }

    /** Paso 1: identificación. Crea al participante y su enlace personal para volver. */
    public function identify(Request $request): JsonResponse
    {
        $this->ensureOpen();
        $existing = Access::participant($request);
        $invite = Access::inviteToken($request);
        abort_unless($existing || $invite, 403, 'Necesitas un enlace de invitación para participar.');
        $v = $request->validate($this->identityRules());

        if ($existing) {
            $existing->update($this->identityData($v));

            return response()->json(['participant' => Present::participant($existing, true), 'personal_link' => $this->personalLink($existing)]);
        }

        [$participant, $plain] = DB::transaction(function () use ($v, $invite) {
            [$token, $plain] = AccessToken::issue(AccessToken::PARTICIPANT, ['label' => $v['name']]);
            $p = Participant::create($this->identityData($v) + [
                'guest_id' => $invite->type === AccessToken::GUEST_INVITE ? $invite->guest_id : null,
                'access_token_id' => $token->id,
            ]);

            return [$p, $plain];
        });

        Access::loginParticipant($request, $participant, $plain);

        return response()->json([
            'participant' => Present::participant($participant, true),
            'personal_link' => url('/mi-recuerdo/'.$plain),
        ], 201);
    }

    private function personalLink(Participant $p): ?string
    {
        $plain = $p->accessToken?->plain();

        return $plain ? url('/mi-recuerdo/'.$plain) : null;
    }

    public function questions(): JsonResponse
    {
        return response()->json(Question::where('active', true)->orderBy('position')->get(['id', 'text']));
    }

    public function contributions(Request $request): JsonResponse
    {
        $p = $this->me($request);
        $items = $p->contributions()->with(['media', 'answers'])->oldest()->get();

        return response()->json([
            'participant' => Present::participant($p, true),
            'personal_link' => $this->personalLink($p),
            'contributions' => $items->map(fn ($c) => Present::contribution($c)),
        ]);
    }

    /** Paso 4: enviar una o varias aportaciones a la vez. */
    public function store(Request $request): JsonResponse
    {
        $this->ensureOpen();
        $p = $this->me($request);
        $request->validate(['items' => ['required', 'array', 'min:1', 'max:12'], 'items.*' => ['array']]);

        $saved = DB::transaction(function () use ($request, $p) {
            $out = [];
            foreach ($request->input('items') as $i => $item) {
                $out[] = $this->writer->save($p, $item, null, "items.$i.");
            }

            return $out;
        });

        return response()->json([
            'contributions' => collect($saved)->map(fn ($c) => Present::contribution($c)),
            'personal_link' => $this->personalLink($p),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->ensureOpen();
        $p = $this->me($request);
        $c = $p->contributions()->whereKey($id)->firstOrFail();
        $c = $this->writer->save($p, $request->all(), $c);

        return response()->json(Present::contribution($c));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->ensureOpen();
        $p = $this->me($request);
        $c = $p->contributions()->whereKey($id)->firstOrFail();
        DB::transaction(function () use ($c) {
            $c->media()->get()->each->delete();
            $c->delete();
        });

        return response()->json(['ok' => true]);
    }

    public function destroyMedia(Request $request, string $id): JsonResponse
    {
        $p = $this->me($request);
        $m = MediaFile::where('participant_id', $p->id)->whereNull('contribution_id')->whereKey($id)->firstOrFail();
        $m->delete();

        return response()->json(['ok' => true]);
    }
}
