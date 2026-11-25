<?php

namespace Tests\Feature;

use App\Models\AccessToken;
use App\Models\Contribution;
use App\Models\EventSetting;
use App\Models\Guest;
use App\Support\Access;
use Illuminate\Http\UploadedFile;

class BirthdayAndAdminTest extends PedacitoTestCase
{
    private function seedContent(): array
    {
        $this->joinAs('Ana', 'friend');
        $media = $this->uploadViaChunks($this->jpeg(500, 400), 'foto.jpg', 'image');
        $this->postJson('/api/my/contributions', ['items' => [
            ['type' => 'letter', 'body' => 'Una carta'],
            ['type' => 'photo', 'title' => 'Juntas', 'media_ids' => [$media['id']]],
        ]])->assertCreated();
        $this->flushSession();

        return $media;
    }

    public function test_birthday_page_waits_until_enabled_then_shows_grouped_memories(): void
    {
        $media = $this->seedContent();

        $this->get('/para-ti/'.$this->birthdayToken)->assertRedirect('/para-ti');
        $this->getJson('/api/birthday/memories')->assertOk()->assertJson(['ready' => false]);
        $this->get('/api/media/'.$media['id'])->assertNotFound();

        EventSetting::current()->update(['memories_enabled' => true]);
        $res = $this->getJson('/api/birthday/memories')->assertOk()
            ->assertJson(['ready' => true, 'stats' => ['people' => 1, 'memories' => 2]])
            ->assertJsonPath('people.0.name', 'Ana')
            ->assertJsonPath('people.0.count', 2);
        $this->assertNotNull($res->json('people.0.photo'));
        $this->get('/api/media/'.$media['id'])->assertOk();

        // Ocultar una aportación la quita de su vista y bloquea el archivo.
        Contribution::where('type', 'photo')->update(['is_hidden' => true]);
        $this->getJson('/api/birthday/memories')->assertJson(['stats' => ['memories' => 1]]);
        $this->get('/api/media/'.$media['id'])->assertNotFound();
    }

    public function test_birthday_password(): void
    {
        $this->actingAs($this->admin())->putJson('/api/admin/settings', ['birthday_password' => 'luna'])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->get('/para-ti/'.$this->birthdayToken);
        $this->getJson('/api/state')->assertJsonPath('birthday.needs_password', true);
        $this->getJson('/api/birthday/memories')->assertForbidden();
        $this->postJson('/api/birthday/unlock', ['password' => 'sol'])->assertUnprocessable();
        $this->postJson('/api/birthday/unlock', ['password' => 'luna'])->assertOk();
        $this->getJson('/api/birthday/memories')->assertOk();
    }

    public function test_admin_login_and_protection(): void
    {
        $this->getJson('/api/admin/overview')->assertUnauthorized();
        $this->postJson('/api/admin/login', ['email' => config('pedacito.admin.email'), 'password' => 'mala'])->assertUnprocessable();
        $this->postJson('/api/admin/login', ['email' => config('pedacito.admin.email'), 'password' => 'secreto-de-prueba'])->assertOk();
        $this->getJson('/api/admin/overview')->assertOk()->assertJsonStructure(['participants', 'contributions', 'media', 'guests']);
    }

    public function test_admin_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/admin/login', ['email' => 'x@y.com', 'password' => 'mala'])->assertUnprocessable();
        }
        $this->postJson('/api/admin/login', ['email' => 'x@y.com', 'password' => 'mala'])
            ->assertUnprocessable();
        $this->assertStringContainsString('Demasiados intentos', $this->postJson('/api/admin/login', ['email' => 'x@y.com', 'password' => 'mala'])->json('errors.email.0'));
    }

    public function test_admin_manages_settings_content_and_links(): void
    {
        $this->seedContent();
        $this->actingAs($this->admin());

        // Configuración del evento
        $this->putJson('/api/admin/settings', [
            'celebrant_short_name' => 'Yos',
            'cover_message' => 'Te queremos',
            'memories_enabled' => true,
        ])->assertOk()->assertJson(['celebrant_short_name' => 'Yos', 'memories_enabled' => true]);

        // Foto de portada
        $tmp = tempnam(sys_get_temp_dir(), 'c').'.jpg';
        file_put_contents($tmp, $this->jpeg(600, 800));
        $this->post('/api/admin/settings/cover', ['file' => new UploadedFile($tmp, 'portada.jpg', 'image/jpeg', null, true)], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('cover.kind', 'image');

        // Favoritas / ocultas / edición
        $c = Contribution::where('type', 'letter')->firstOrFail();
        $this->patchJson('/api/admin/contributions/'.$c->id, ['is_favorite' => true, 'body' => 'Una carta corregida'])->assertOk();
        $this->assertTrue($c->fresh()->is_favorite);
        $this->assertSame('Una carta corregida', $c->fresh()->body);

        // Editar persona
        $p = $c->participant;
        $this->patchJson('/api/admin/participants/'.$p->id, ['name' => 'Ana María', 'relationship' => 'family'])->assertOk();
        $this->assertSame('family', $p->fresh()->relationship);

        // Descarga
        $photo = Contribution::where('type', 'photo')->first()->media->first();
        $this->get('/api/admin/media/'.$photo->id.'/download')->assertOk()->assertDownload();
        $this->get('/api/admin/export')->assertOk()->assertDownload();

        // Invitados
        $this->postJson('/api/admin/guests', ['bulk' => "Mamá\nLuis\nMamá"])->assertOk()->assertJsonCount(2);
        $g = Guest::where('name', 'Luis')->first();
        $link = $this->postJson("/api/admin/guests/{$g->id}/link")->assertOk()->json('personal_link');
        $this->assertStringContainsString('/invitacion/', $link);

        // Regenerar invalida el enlace anterior
        $old = $this->getJson('/api/admin/links')->json('invite.url');
        $new = $this->postJson('/api/admin/links/invite/regenerate')->assertOk()->json('invite.url');
        $this->assertNotSame($old, $new);
        $this->get(parse_url($old, PHP_URL_PATH))->assertRedirect('/enlace-no-valido');
        $this->get(parse_url($new, PHP_URL_PATH))->assertRedirect('/participar');

        // Eliminar
        $this->deleteJson('/api/admin/contributions/'.$c->id)->assertOk();
        $this->assertNull(Contribution::find($c->id));
    }

    public function test_tokens_are_stored_hashed(): void
    {
        $t = AccessToken::where('token_hash', AccessToken::hash($this->inviteToken))->firstOrFail();
        $this->assertNotSame($this->inviteToken, $t->getRawOriginal('token_hash'));
        $this->assertNotSame($this->inviteToken, $t->getRawOriginal('token_encrypted'));
    }
}
