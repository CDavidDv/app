<?php

namespace App\Services;

use App\Models\Contribution;
use App\Models\MediaFile;
use App\Models\Participant;
use App\Models\Question;
use App\Support\SongLink;
use App\Support\Text;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Valida y guarda aportaciones según su tipo. Una aportación puede tener texto, archivos o ambos.
 */
class ContributionWriter
{
    public function save(Participant $participant, array $item, ?Contribution $existing = null, string $errorPrefix = ''): Contribution
    {
        $type = $existing?->type ?? ($item['type'] ?? null);
        if (! in_array($type, Contribution::TYPES, true)) {
            $this->fail($errorPrefix.'type', 'Tipo de aportación no válido.');
        }

        $limits = config('pedacito.limits');
        $data = [
            'type' => $type,
            'title' => Text::line($item['title'] ?? null, 200),
            'artist' => null,
            'link' => null,
            'signature' => Text::line($item['signature'] ?? null, 120),
            'prompt' => Text::line($item['prompt'] ?? null, 255),
            'body' => null,
        ];
        $mediaIds = array_values(array_unique(array_filter((array) ($item['media_ids'] ?? []), 'is_string')));
        $answers = [];

        switch ($type) {
            case 'letter':
                $data['body'] = Text::clean($item['body'] ?? null, $limits['letter_chars']);
                if (! $data['body']) {
                    $this->fail($errorPrefix.'body', 'Escribe tu carta antes de enviarla.');
                }
                $mediaIds = [];
                break;
            case 'message':
                $data['body'] = Text::clean($item['body'] ?? null, $limits['message_chars']);
                if (! $data['body']) {
                    $this->fail($errorPrefix.'body', 'Escribe tus palabras antes de enviarlas.');
                }
                $data['title'] = null;
                $mediaIds = [];
                break;
            case 'secret':
                $data['body'] = Text::clean($item['body'] ?? null, $limits['text_chars']);
                if (! $data['body']) {
                    $this->fail($errorPrefix.'body', 'Cuéntale eso que nunca le has dicho.');
                }
                $mediaIds = [];
                break;
            case 'video':
                $data['body'] = Text::clean($item['body'] ?? null, $limits['text_chars']);
                if (count($mediaIds) < 1) {
                    $this->fail($errorPrefix.'media_ids', 'Sube al menos un video.');
                }
                if (count($mediaIds) > $limits['videos_per_contribution']) {
                    $this->fail($errorPrefix.'media_ids', 'Puedes subir hasta '.$limits['videos_per_contribution'].' videos.');
                }
                break;
            case 'photo':
                $data['body'] = Text::clean($item['body'] ?? null, $limits['text_chars']);
                if (count($mediaIds) < 1) {
                    $this->fail($errorPrefix.'media_ids', 'Sube al menos una fotografía.');
                }
                if (count($mediaIds) > $limits['photos_per_memory']) {
                    $this->fail($errorPrefix.'media_ids', 'Puedes subir hasta '.$limits['photos_per_memory'].' fotografías por recuerdo.');
                }
                break;
            case 'song':
                $mediaIds = [];
                $data['artist'] = Text::line($item['artist'] ?? null, 200);
                $data['body'] = Text::clean($item['body'] ?? null, $limits['text_chars']);
                $data['signature'] = null;
                $data['prompt'] = null;
                if (! $data['title']) {
                    $this->fail($errorPrefix.'title', 'Escribe el nombre de la canción.');
                }
                $rawLink = trim((string) ($item['link'] ?? ''));
                if ($rawLink === '') {
                    $this->fail($errorPrefix.'link', 'Pega el enlace de la canción (YouTube, Spotify u otra plataforma).');
                }
                $data['link'] = SongLink::normalize($rawLink);
                if (! $data['link']) {
                    $this->fail($errorPrefix.'link', 'Ese enlace no parece válido. Cópialo desde el botón Compartir de la app.');
                }
                break;
            case 'questions':
                $mediaIds = [];
                $data['title'] = null;
                $questions = Question::where('active', true)->get()->keyBy('id');
                foreach ((array) ($item['answers'] ?? []) as $a) {
                    $q = $questions->get((int) ($a['question_id'] ?? 0));
                    $text = Text::clean($a['answer'] ?? null, $limits['text_chars']);
                    if ($q && $text) {
                        $answers[$q->id] = ['question_id' => $q->id, 'question_text' => $q->text, 'answer' => $text, 'position' => $q->position];
                    }
                }
                if (! $answers) {
                    $this->fail($errorPrefix.'answers', 'Responde al menos una pregunta.');
                }
                break;
        }

        $kind = $type === 'video' ? 'video' : 'image';
        $media = collect();
        if ($mediaIds) {
            $media = MediaFile::query()
                ->whereIn('id', $mediaIds)
                ->where('participant_id', $participant->id)
                ->where('purpose', 'contribution')
                ->where('kind', $kind)
                ->where(fn ($q) => $q->whereNull('contribution_id')->when($existing, fn ($q) => $q->orWhere('contribution_id', $existing->id)))
                ->get()->keyBy('id');
            if ($media->count() !== count($mediaIds)) {
                $this->fail($errorPrefix.'media_ids', 'Alguno de los archivos ya no está disponible. Vuelve a subirlo.');
            }
        }

        return DB::transaction(function () use ($participant, $existing, $data, $mediaIds, $media, $answers) {
            $c = $existing ?? new Contribution;
            $c->fill($data);
            if (! $existing) {
                $c->participant()->associate($participant);
            }
            $c->save();

            // Archivos que se quitaron durante la edición: se eliminan.
            if ($existing) {
                MediaFile::where('contribution_id', $c->id)->whereNotIn('id', $mediaIds ?: ['-'])->get()->each->delete();
            }
            foreach ($mediaIds as $pos => $id) {
                $media[$id]->forceFill(['contribution_id' => $c->id, 'position' => $pos])->save();
            }

            $c->answers()->delete();
            foreach (array_values($answers) as $a) {
                $c->answers()->create($a);
            }

            return $c->load(['media', 'answers']);
        });
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
