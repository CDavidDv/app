<?php

use App\Models\MediaFile;
use App\Models\UploadSession;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;

// Elimina subidas abandonadas: archivos nunca asociados a una aportación y partes de subidas incompletas.
Artisan::command('pedacito:cleanup', function () {
    $limit = now()->subHours(config('pedacito.orphan_hours'));

    $orphans = MediaFile::whereNull('contribution_id')->where('purpose', 'contribution')->where('created_at', '<', $limit)->get();
    $orphans->each->delete();

    $sessions = UploadSession::where('updated_at', '<', now()->subDay())->get();
    foreach ($sessions as $s) {
        File::deleteDirectory($s->chunkDir());
        $s->delete();
    }

    $this->info("Archivos huérfanos eliminados: {$orphans->count()}. Subidas incompletas limpiadas: {$sessions->count()}.");
})->purpose('Limpia archivos y subidas abandonadas');

Schedule::command('pedacito:cleanup')->daily();
