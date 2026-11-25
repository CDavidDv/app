<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Participant;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/** Descarga de respaldo: todos los textos y archivos en un ZIP organizado por persona. */
class ExportController extends Controller
{
    public function zip(?string $participantId = null): BinaryFileResponse
    {
        @set_time_limit(600);
        abort_unless(class_exists(ZipArchive::class), 500, 'La extensión zip de PHP no está disponible.');

        $query = Participant::with(['contributions.media', 'contributions.answers'])->oldest();
        if ($participantId) {
            $query->whereKey($participantId);
        }
        $people = $query->get();
        abort_if($people->isEmpty(), 404);

        $tmp = tempnam(sys_get_temp_dir(), 'pdt');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::OVERWRITE);

        $labels = ['letter' => 'Carta', 'video' => 'Video', 'photo' => 'Recuerdo', 'message' => 'Palabras', 'secret' => 'Algo que nunca te he dicho', 'questions' => 'Preguntas', 'song' => 'Canción'];
        foreach ($people as $i => $p) {
            $folder = sprintf('%02d - %s', $i + 1, Str::slug($p->publicName()) ?: 'persona');
            $txt = $p->publicName().' ('.$p->relationshipLabel().")\n\n";
            foreach ($p->contributions as $c) {
                $txt .= '== '.$labels[$c->type].($c->title ? ': '.$c->title : '')." ==\n";
                if ($c->artist) {
                    $txt .= $c->artist."\n";
                }
                if ($c->link) {
                    $txt .= $c->link."\n";
                }
                if ($c->prompt) {
                    $txt .= $c->prompt."\n";
                }
                if ($c->body) {
                    $txt .= $c->body."\n";
                }
                foreach ($c->answers as $a) {
                    $txt .= "\n".$a->question_text."\n".$a->answer."\n";
                }
                if ($c->signature) {
                    $txt .= "\n— ".$c->signature."\n";
                }
                $txt .= "\n";
                foreach ($c->media as $n => $m) {
                    $disk = Storage::disk($m->disk);
                    $name = $folder.'/'.$labels[$c->type].' '.($n + 1).' - '.$m->downloadName();
                    if (config("filesystems.disks.{$m->disk}.driver") === 'local') {
                        if (is_file($disk->path($m->path))) {
                            $zip->addFile($disk->path($m->path), $name);
                        }
                    } else {
                        $zip->addFromString($name, (string) $disk->get($m->path));
                    }
                }
            }
            $zip->addFromString($folder.'/mensajes.txt', $txt);
        }
        $zip->close();

        return response()->download($tmp, 'un-pedacito-de-todos-'.now()->format('Y-m-d').'.zip')->deleteFileAfterSend();
    }
}
