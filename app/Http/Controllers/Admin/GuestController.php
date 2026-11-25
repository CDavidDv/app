<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessToken;
use App\Models\Guest;
use App\Models\Participant;
use App\Support\Text;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Lista manual de invitados: quién ya participó y quién falta, con enlace personal opcional. */
class GuestController extends Controller
{
    private function present(Guest $g): array
    {
        $plain = $g->inviteToken?->plain();

        return [
            'id' => $g->id,
            'name' => $g->name,
            'relationship' => $g->relationship,
            'contact' => $g->contact,
            'notes' => $g->notes,
            'participated' => $g->participants_count > 0,
            'participants_count' => (int) $g->participants_count,
            'personal_link' => $plain ? url('/invitacion/'.$plain) : null,
        ];
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'relationship' => ['nullable', Rule::in(array_keys(Participant::RELATIONSHIPS))],
            'contact' => ['nullable', 'string', 'max:190'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function clean(array $v): array
    {
        return [
            'name' => Text::line($v['name'], 120) ?? 'Invitado',
            'relationship' => $v['relationship'] ?? null,
            'contact' => Text::line($v['contact'] ?? null, 190),
            'notes' => Text::line($v['notes'] ?? null, 500),
        ];
    }

    public function index(): JsonResponse
    {
        return response()->json(Guest::withCount('participants')->with('inviteToken')->orderBy('name')->get()->map(fn ($g) => $this->present($g)));
    }

    public function store(Request $request): JsonResponse
    {
        // Permite pegar varios nombres a la vez (uno por línea).
        if ($request->filled('bulk')) {
            $request->validate(['bulk' => ['string', 'max:10000']]);
            $names = collect(preg_split('/\r?\n/', $request->string('bulk')))->map(fn ($n) => Text::line($n, 120))->filter()->unique()->take(300);
            foreach ($names as $n) {
                Guest::firstOrCreate(['name' => $n]);
            }

            return $this->index();
        }
        $g = Guest::create($this->clean($request->validate($this->rules())));

        return response()->json($this->present($g->loadCount('participants')), 201);
    }

    public function update(Request $request, Guest $guest): JsonResponse
    {
        $guest->update($this->clean($request->validate($this->rules())));

        return response()->json($this->present($guest->loadCount('participants')->load('inviteToken')));
    }

    public function destroy(Guest $guest): JsonResponse
    {
        $guest->delete();

        return response()->json(['ok' => true]);
    }

    /** Genera (o regenera) un enlace personal que precarga su nombre al participar. */
    public function link(Guest $guest): JsonResponse
    {
        AccessToken::where('guest_id', $guest->id)->where('type', AccessToken::GUEST_INVITE)->update(['active' => false]);
        [, $plain] = AccessToken::issue(AccessToken::GUEST_INVITE, ['guest_id' => $guest->id, 'label' => $guest->name]);

        return response()->json(['personal_link' => url('/invitacion/'.$plain)]);
    }
}
