<?php

namespace Tests\Feature;

use App\Models\AccessToken;
use App\Models\EventSetting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

abstract class PedacitoTestCase extends TestCase
{
    use RefreshDatabase;

    protected string $inviteToken;
    protected string $birthdayToken;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
        config(['pedacito.disk' => 'media', 'pedacito.chunk_size_mb' => 1, 'pedacito.admin.password' => 'secreto-de-prueba',
            'pedacito.event.celebrant_name' => 'Festejada de Prueba', 'pedacito.event.celebrant_short_name' => 'Festejada']);
        $this->seed(DatabaseSeeder::class);

        // Fechas abiertas para las pruebas.
        EventSetting::current()->update(['submissions_close_at' => now()->addDays(10)]);

        [, $this->inviteToken] = AccessToken::issue(AccessToken::INVITE);
        [, $this->birthdayToken] = AccessToken::issue(AccessToken::BIRTHDAY);
    }

    protected function admin(): User
    {
        return User::firstOrFail();
    }

    /** Entra con el enlace de invitación y se identifica. */
    protected function joinAs(string $name = 'Ana López', string $relationship = 'friend'): array
    {
        $this->get('/invitacion/'.$this->inviteToken)->assertRedirect('/participar');
        $res = $this->postJson('/api/participant', ['name' => $name, 'relationship' => $relationship])->assertCreated();

        return $res->json();
    }

    /** Sube un archivo por partes usando la API real. */
    protected function uploadViaChunks(string $contents, string $name, string $kind): array
    {
        $start = $this->postJson('/api/uploads', ['kind' => $kind, 'name' => $name, 'size' => strlen($contents)])->assertCreated()->json();
        $size = $start['chunk_size'];
        for ($i = 0; $i < $start['total_chunks']; $i++) {
            $part = substr($contents, $i * $size, $size);
            $tmp = tempnam(sys_get_temp_dir(), 'chk');
            file_put_contents($tmp, $part);
            $this->post("/api/uploads/{$start['id']}/chunk", [
                'index' => $i,
                'chunk' => new UploadedFile($tmp, "part-$i", 'application/octet-stream', null, true),
            ], ['Accept' => 'application/json'])->assertOk();
        }

        return $this->postJson("/api/uploads/{$start['id']}/complete")->assertCreated()->json();
    }

    /** JPEG real con ruido (pesa más de 1 MB para forzar varias partes). */
    protected function jpeg(int $w = 1400, int $h = 1000): string
    {
        $img = imagecreatetruecolor($w, $h);
        for ($i = 0; $i < 60000; $i++) {
            imagesetpixel($img, random_int(0, $w - 1), random_int(0, $h - 1), random_int(0, 0xFFFFFF));
        }
        for ($y = 0; $y < $h; $y += 2) {
            imageline($img, 0, $y, $w, $y, random_int(0, 0xFFFFFF));
        }
        ob_start();
        imagejpeg($img, null, 95);

        return ob_get_clean();
    }

    /** Contenedor MP4 mínimo que finfo reconoce como video/mp4. */
    protected function mp4(int $bytes = 1_500_000): string
    {
        $ftyp = pack('N', 24).'ftypmp42'.pack('N', 0).'mp42isom';
        $mdat = pack('N', $bytes).'mdat'.random_bytes($bytes - 8);

        return $ftyp.$mdat;
    }
}
