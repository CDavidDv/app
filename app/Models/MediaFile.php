<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MediaFile extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $hidden = ['disk', 'path', 'thumb_path'];

    protected static function booted(): void
    {
        // Al eliminar el registro se eliminan también los archivos físicos.
        static::deleting(function (MediaFile $m) {
            $disk = Storage::disk($m->disk);
            foreach (array_filter([$m->path, $m->thumb_path]) as $p) {
                try {
                    $disk->delete($p);
                } catch (\Throwable) {
                }
            }
        });
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function contribution(): BelongsTo
    {
        return $this->belongsTo(Contribution::class);
    }

    public function downloadName(): string
    {
        $ext = pathinfo($this->path, PATHINFO_EXTENSION);
        $base = pathinfo($this->original_name ?: 'archivo', PATHINFO_FILENAME);
        $base = preg_replace('/[^\pL\pN _-]+/u', '', $base) ?: 'archivo';

        return mb_substr($base, 0, 80).'.'.$ext;
    }
}
