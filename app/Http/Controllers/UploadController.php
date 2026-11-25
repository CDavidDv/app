<?php

namespace App\Http\Controllers;

use App\Models\EventSetting;
use App\Models\UploadSession;
use App\Services\MediaService;
use App\Support\Access;
use App\Support\Present;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Subida por partes (chunks) para que los videos grandes funcionen en redes móviles
 * y respeten los límites de PHP del hosting.
 */
class UploadController extends Controller
{
    public function __construct(private MediaService $media)
    {
    }

    /** @return array{0: array, 1: ?string} owner attributes y participant id */
    private function owner(Request $request): array
    {
        if ($p = Access::participant($request)) {
            if (! EventSetting::current()->acceptingSubmissions()) {
                throw ValidationException::withMessages(['closed' => 'La recepción de recuerdos ya cerró.']);
            }

            return [['participant_id' => $p->id], $p->id];
        }
        abort(401, 'Primero cuéntanos quién eres.');
    }

    private function session(Request $request, string $id): UploadSession
    {
        [$owner] = $this->owner($request);

        return UploadSession::where($owner)->whereKey($id)->firstOrFail();
    }

    public function start(Request $request): JsonResponse
    {
        [$owner] = $this->owner($request);
        $v = $request->validate([
            'kind' => ['required', 'in:image,video'],
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
        ]);

        // Evita acumular subidas abandonadas.
        $pending = UploadSession::where($owner)->where('status', 'pending')->where('created_at', '>', now()->subDay())->count();
        if ($pending > 30) {
            throw ValidationException::withMessages(['file' => 'Tienes demasiadas subidas pendientes. Recarga la página e inténtalo de nuevo.']);
        }

        $s = $this->media->startUpload($v['kind'], $v['name'], (int) $v['size'], $owner);

        return response()->json([
            'id' => $s->id,
            'chunk_size' => $this->media->chunkBytes(),
            'total_chunks' => $s->total_chunks,
        ], 201);
    }

    public function chunk(Request $request, string $id): JsonResponse
    {
        $s = $this->session($request, $id);
        $request->validate([
            'index' => ['required', 'integer', 'min:0'],
            'chunk' => ['required', 'file', 'max:'.(config('pedacito.chunk_size_mb') * 1024 + 64)],
        ]);
        $this->media->storeChunk($s, (int) $request->input('index'), $request->file('chunk'));

        return response()->json(['received' => $s->received_chunks, 'total' => $s->total_chunks]);
    }

    public function complete(Request $request, string $id): JsonResponse
    {
        [, $participantId] = $this->owner($request);
        $s = $this->session($request, $id);
        $m = $this->media->completeUpload($s, $participantId);

        return response()->json(Present::media($m), 201);
    }
}
