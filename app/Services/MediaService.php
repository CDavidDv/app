<?php

namespace App\Services;

use App\Models\MediaFile;
use App\Models\UploadSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validación, almacenamiento y entrega de fotografías y videos.
 * El disco se configura con MEDIA_DISK (local privado o S3 compatible).
 */
class MediaService
{
    public function diskName(): string
    {
        return config('pedacito.disk');
    }

    public function maxBytes(string $kind): int
    {
        $mb = $kind === 'video' ? config('pedacito.limits.video_mb') : config('pedacito.limits.image_mb');

        return $mb * 1024 * 1024;
    }

    public function chunkBytes(): int
    {
        return config('pedacito.chunk_size_mb') * 1024 * 1024;
    }

    public function assertExtension(string $kind, string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowed = config("pedacito.allowed.$kind.extensions", []);
        if (! in_array($ext, $allowed, true)) {
            throw ValidationException::withMessages([
                'file' => $kind === 'video'
                    ? 'Este formato de video no es compatible. Usa MP4, MOV o WEBM.'
                    : 'Este formato de imagen no es compatible. Usa JPG, PNG o WEBP.',
            ]);
        }

        return $ext === 'jpeg' ? 'jpg' : $ext;
    }

    public function startUpload(string $kind, string $name, int $size, array $owner, string $purpose = 'contribution'): UploadSession
    {
        if (! in_array($kind, ['image', 'video'], true)) {
            throw ValidationException::withMessages(['kind' => 'Tipo de archivo no permitido.']);
        }
        $ext = $this->assertExtension($kind, $name);
        if ($size <= 0 || $size > $this->maxBytes($kind)) {
            $mb = intdiv($this->maxBytes($kind), 1048576);
            throw ValidationException::withMessages(['file' => "El archivo es demasiado grande. El máximo es de {$mb} MB."]);
        }

        $session = UploadSession::create(array_merge($owner, [
            'kind' => $kind,
            'purpose' => $purpose,
            'original_name' => mb_substr(basename($name), 0, 200),
            'extension' => $ext,
            'size' => $size,
            'total_chunks' => (int) ceil($size / $this->chunkBytes()),
        ]));
        File::ensureDirectoryExists($session->chunkDir(), 0700);

        return $session;
    }

    public function storeChunk(UploadSession $session, int $index, UploadedFile $chunk): void
    {
        if ($session->status !== 'pending') {
            throw ValidationException::withMessages(['file' => 'Esta subida ya terminó.']);
        }
        if ($index < 0 || $index >= $session->total_chunks) {
            throw ValidationException::withMessages(['index' => 'Parte inválida.']);
        }
        $expected = $index === $session->total_chunks - 1
            ? $session->size - ($this->chunkBytes() * ($session->total_chunks - 1))
            : $this->chunkBytes();
        if ($chunk->getSize() !== $expected) {
            throw ValidationException::withMessages(['file' => 'Una parte del archivo llegó incompleta. Inténtalo de nuevo.']);
        }

        File::ensureDirectoryExists($session->chunkDir(), 0700);
        $target = $session->chunkDir().DIRECTORY_SEPARATOR.sprintf('%06d.part', $index);
        $chunk->move($session->chunkDir(), basename($target));

        $received = count(glob($session->chunkDir().DIRECTORY_SEPARATOR.'*.part') ?: []);
        $session->forceFill(['received_chunks' => $received])->save();
    }

    /**
     * Une las partes, valida el contenido real del archivo y lo guarda en el disco de medios.
     */
    public function completeUpload(UploadSession $session, ?string $participantId): MediaFile
    {
        $dir = $session->chunkDir();
        $parts = glob($dir.DIRECTORY_SEPARATOR.'*.part') ?: [];
        sort($parts);
        if (count($parts) !== $session->total_chunks) {
            throw ValidationException::withMessages(['file' => 'Faltan partes del archivo. Vuelve a intentarlo.']);
        }

        $assembled = $dir.DIRECTORY_SEPARATOR.'assembled.bin';
        $out = fopen($assembled, 'wb');
        foreach ($parts as $p) {
            $in = fopen($p, 'rb');
            stream_copy_to_stream($in, $out);
            fclose($in);
        }
        fclose($out);

        try {
            if (filesize($assembled) !== (int) $session->size) {
                throw ValidationException::withMessages(['file' => 'El archivo llegó incompleto.']);
            }
            $media = $this->storeLocalFile($assembled, $session->kind, $session->extension, $session->original_name, $participantId, $session->purpose);
            $session->forceFill(['status' => 'complete'])->save();

            return $media;
        } catch (\Throwable $e) {
            $session->forceFill(['status' => 'failed'])->save();
            throw $e;
        } finally {
            File::deleteDirectory($dir);
        }
    }

    /**
     * Valida un archivo local (MIME real con finfo, dimensiones de imagen) y lo guarda.
     */
    public function storeLocalFile(string $path, string $kind, string $ext, ?string $originalName, ?string $participantId, string $purpose = 'contribution'): MediaFile
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
        $allowedMimes = config("pedacito.allowed.$kind.mimes", []);
        if (! in_array($mime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                'file' => 'El contenido del archivo no coincide con '.match ($kind) {
                    'video' => 'un video válido.',
                    'audio' => 'un audio válido.',
                    default => 'una imagen válida.',
                },
            ]);
        }

        $width = $height = null;
        if ($kind === 'image') {
            $info = @getimagesize($path);
            if (! $info || $info[0] < 1 || $info[1] < 1) {
                throw ValidationException::withMessages(['file' => 'La imagen parece estar dañada.']);
            }
            [$width, $height] = $info;
            // Extensión coherente con el contenido real.
            $ext = match ($mime) {
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg',
            };
        } elseif ($kind === 'audio') {
            $ext = match ($mime) {
                'audio/ogg' => 'ogg',
                'audio/mpeg' => 'mp3',
                default => 'm4a',
            };
        } else {
            $ext = match ($mime) {
                'video/quicktime' => 'mov',
                'video/webm' => 'webm',
                'video/3gpp', 'video/3gpp2' => '3gp',
                default => 'mp4',
            };
        }

        $folder = $purpose === 'contribution' ? ($participantId ?? 'misc') : $purpose;
        $id = (string) Str::ulid();
        $storedPath = $folder.'/'.$id.'_'.Str::random(16).'.'.$ext;
        $disk = Storage::disk($this->diskName());

        $stream = fopen($path, 'rb');
        $disk->writeStream($storedPath, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        $thumb = null;
        if ($kind === 'image') {
            $thumb = $this->makeThumbnail($path, $mime, $folder.'/'.$id.'_thumb_'.Str::random(16).'.jpg');
        }

        return MediaFile::create([
            'id' => $id,
            'participant_id' => $participantId,
            'kind' => $kind,
            'purpose' => $purpose,
            'disk' => $this->diskName(),
            'path' => $storedPath,
            'thumb_path' => $thumb,
            'original_name' => $originalName ? mb_substr($originalName, 0, 200) : null,
            'mime' => $mime,
            'size' => filesize($path),
            'width' => $width,
            'height' => $height,
        ]);
    }

    /**
     * Miniatura JPEG (máx. 1000px) para que la galería cargue rápido en el teléfono.
     * Corrige la orientación EXIF cuando la extensión está disponible.
     */
    private function makeThumbnail(string $path, string $mime, string $target): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }
        try {
            $src = match ($mime) {
                'image/png' => @imagecreatefrompng($path),
                'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
                default => @imagecreatefromjpeg($path),
            };
            if (! $src) {
                return null;
            }
            if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
                $exif = @exif_read_data($path);
                $o = $exif['Orientation'] ?? 1;
                $src = match ((int) $o) {
                    3 => imagerotate($src, 180, 0),
                    6 => imagerotate($src, -90, 0),
                    8 => imagerotate($src, 90, 0),
                    default => $src,
                };
            }
            $w = imagesx($src);
            $h = imagesy($src);
            $max = 1000;
            $scale = min(1, $max / max($w, $h));
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            $dst = imagecreatetruecolor($nw, $nh);
            $white = imagecolorallocate($dst, 255, 253, 248);
            imagefill($dst, 0, 0, $white);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            ob_start();
            imagejpeg($dst, null, 82);
            $data = ob_get_clean();
            imagedestroy($src);
            imagedestroy($dst);
            Storage::disk($this->diskName())->put($target, $data);

            return $target;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Entrega un archivo. En disco local usa BinaryFileResponse (soporta Range para videos);
     * en S3 redirige a una URL firmada de corta duración.
     */
    public function respond(MediaFile $media, bool $thumb = false, bool $download = false): Response
    {
        $path = $thumb && $media->thumb_path ? $media->thumb_path : $media->path;
        $disk = Storage::disk($media->disk);
        $mime = $thumb && $media->thumb_path ? 'image/jpeg' : $media->mime;

        $headers = [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; media-src 'self'; sandbox",
        ];

        if (config("filesystems.disks.{$media->disk}.driver") === 'local') {
            $full = $disk->path($path);
            abort_unless(is_file($full), 404);
            $response = response()->file($full, $headers);
            if ($download) {
                $response->setContentDisposition('attachment', $media->downloadName(), 'archivo');
            } else {
                $response->setContentDisposition('inline', 'archivo.'.pathinfo($path, PATHINFO_EXTENSION));
            }

            return $response;
        }

        $opts = ['ResponseContentType' => $mime];
        if ($download) {
            $opts['ResponseContentDisposition'] = 'attachment; filename="'.$media->downloadName().'"';
        }

        return redirect()->away($disk->temporaryUrl($path, now()->addMinutes(20), $opts));
    }
}
