<?php

namespace Tests\Feature;

use App\Models\AccessToken;
use App\Models\Contribution;
use App\Models\EventSetting;
use App\Models\MediaFile;
use App\Models\Participant;
use App\Models\Question;
use App\Support\Access;
use Illuminate\Support\Facades\Storage;

class ParticipationTest extends PedacitoTestCase
{
    public function test_without_link_nobody_can_participate(): void
    {
        $this->getJson('/api/state')->assertOk()->assertJson(['can_participate' => false, 'event' => null]);
        $this->postJson('/api/participant', ['name' => 'X', 'relationship' => 'friend'])->assertForbidden();
        $this->get('/invitacion/token-falso-que-no-existe-1234567890')->assertRedirect('/enlace-no-valido');
    }

    public function test_invite_link_opens_participation_and_registers_participant(): void
    {
        $this->get('/invitacion/'.$this->inviteToken)->assertRedirect('/participar');
        $this->getJson('/api/state')->assertJson(['can_participate' => true, 'participant' => null])
            ->assertJsonPath('event.celebrant_short_name', 'Festejada');

        $res = $this->postJson('/api/participant', [
            'name' => 'Carlos',
            'relationship' => 'other',
            'relationship_other' => 'Vecino',
            'display_name' => 'Tu vecino favorito',
        ])->assertCreated();

        $this->assertStringContainsString('/mi-recuerdo/', $res->json('personal_link'));
        $p = Participant::firstOrFail();
        $this->assertSame('Vecino', $p->relationshipLabel());
        $this->assertSame('Tu vecino favorito', $p->publicName());
    }

    public function test_relationship_other_requires_text(): void
    {
        $this->get('/invitacion/'.$this->inviteToken);
        $this->postJson('/api/participant', ['name' => 'Carlos', 'relationship' => 'other'])
            ->assertUnprocessable()->assertJsonValidationErrors('relationship_other');
    }

    public function test_send_letter_message_secret_and_questions_together(): void
    {
        $this->joinAs();
        $q = Question::orderBy('position')->first();

        $this->postJson('/api/my/contributions', ['items' => [
            ['type' => 'letter', 'title' => 'Para ti', 'body' => "Querida amiga,\n\nGracias por todo.", 'signature' => 'Ana'],
            ['type' => 'message', 'body' => '¡Feliz cumpleaños! <script>alert(1)</script>'],
            ['type' => 'secret', 'prompt' => 'Quiero que sepas que…', 'body' => 'Eres mi persona favorita.'],
            ['type' => 'questions', 'answers' => [['question_id' => $q->id, 'answer' => 'Aquel viaje.'], ['question_id' => 999, 'answer' => 'ignorada']]],
        ]])->assertCreated()->assertJsonCount(4, 'contributions');

        $this->assertSame(4, Contribution::count());
        $msg = Contribution::where('type', 'message')->first();
        $this->assertStringNotContainsString('<script>', $msg->body);
        $questions = Contribution::where('type', 'questions')->first();
        $this->assertCount(1, $questions->answers);
        $this->assertSame($q->text, $questions->answers->first()->question_text);
    }

    public function test_empty_letter_is_rejected_with_friendly_message(): void
    {
        $this->joinAs();
        $this->postJson('/api/my/contributions', ['items' => [['type' => 'letter', 'body' => '   ']]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.body');
        $this->assertSame(0, Contribution::count());
    }

    public function test_song_with_link_is_saved_and_platform_detected(): void
    {
        $this->joinAs();
        $res = $this->postJson('/api/my/contributions', ['items' => [[
            'type' => 'song',
            'title' => 'Coldplay - Yellow',
            'artist' => 'Coldplay',
            'link' => 'Escúchala aquí: https://open.spotify.com/track/3AJwUDP919kvQ9QcozQPxg?si=abc',
            'body' => 'Siempre que suena pienso en ti.',
        ]]])->assertCreated();

        $res->assertJsonPath('contributions.0.link', 'https://open.spotify.com/track/3AJwUDP919kvQ9QcozQPxg?si=abc')
            ->assertJsonPath('contributions.0.platform', 'Spotify')
            ->assertJsonPath('contributions.0.artist', 'Coldplay');

        $this->postJson('/api/my/contributions', ['items' => [['type' => 'song', 'title' => 'Otra', 'link' => 'youtu.be/dQw4w9WgXcQ']]])
            ->assertCreated()->assertJsonPath('contributions.0.platform', 'YouTube')
            ->assertJsonPath('contributions.0.link', 'https://youtu.be/dQw4w9WgXcQ');
    }

    public function test_song_rejects_unsafe_or_missing_links(): void
    {
        $this->joinAs();
        foreach (['javascript:alert(1)', 'data:text/html,hola', 'https://a b.com', '', 'ftp://x.com/a.mp3'] as $bad) {
            $this->postJson('/api/my/contributions', ['items' => [['type' => 'song', 'title' => 'X', 'link' => $bad]]])
                ->assertUnprocessable()->assertJsonValidationErrors('items.0.link');
        }
        $this->postJson('/api/my/contributions', ['items' => [['type' => 'song', 'link' => 'https://youtu.be/x']]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.title');
        $this->assertSame(0, Contribution::count());
    }

    public function test_photo_upload_in_chunks_with_thumbnail(): void
    {
        $this->joinAs();
        $jpeg = $this->jpeg();
        $this->assertGreaterThan(1024 * 1024, strlen($jpeg), 'la imagen debe necesitar varias partes');

        $media = $this->uploadViaChunks($jpeg, 'IMG_0001.JPG', 'image');
        $this->assertSame('image/jpeg', $media['mime']);

        $m = MediaFile::findOrFail($media['id']);
        Storage::disk('media')->assertExists($m->path);
        $this->assertNotNull($m->thumb_path);
        Storage::disk('media')->assertExists($m->thumb_path);
        $this->assertSame(strlen($jpeg), $m->size);

        $this->postJson('/api/my/contributions', ['items' => [
            ['type' => 'photo', 'title' => 'La playa', 'body' => 'Ese día…', 'media_ids' => [$media['id']]],
        ]])->assertCreated();
        $this->assertNotNull($m->fresh()->contribution_id);

        // El propio participante puede ver su foto.
        $this->get('/api/media/'.$m->id)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get('/api/media/'.$m->id.'/thumb')->assertOk();
    }

    public function test_video_upload_and_inline_playback_with_range(): void
    {
        $this->joinAs();
        $media = $this->uploadViaChunks($this->mp4(), 'video.mp4', 'video');
        $this->assertSame('video/mp4', $media['mime']);

        $this->postJson('/api/my/contributions', ['items' => [
            ['type' => 'video', 'body' => 'Para ti', 'media_ids' => [$media['id']]],
        ]])->assertCreated();

        $this->get('/api/media/'.$media['id'], ['Range' => 'bytes=0-99'])->assertStatus(206);
    }

    public function test_disguised_or_disallowed_files_are_rejected(): void
    {
        $this->joinAs();
        // Extensión no permitida
        $this->postJson('/api/uploads', ['kind' => 'image', 'name' => 'shell.php', 'size' => 100])
            ->assertUnprocessable();
        // Contenido PHP disfrazado de JPG
        $start = $this->postJson('/api/uploads', ['kind' => 'image', 'name' => 'foto.jpg', 'size' => 30])->json();
        $tmp = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($tmp, '<?php system($_GET["c"]); ?>  ');
        $this->post("/api/uploads/{$start['id']}/chunk", ['index' => 0, 'chunk' => new \Illuminate\Http\UploadedFile($tmp, 'p', null, null, true)], ['Accept' => 'application/json'])->assertOk();
        $this->postJson("/api/uploads/{$start['id']}/complete")->assertUnprocessable();
        $this->assertSame(0, MediaFile::count());
        // Tamaño excesivo
        $this->postJson('/api/uploads', ['kind' => 'video', 'name' => 'v.mp4', 'size' => 5 * 1024 * 1024 * 1024])->assertUnprocessable();
    }

    public function test_participants_cannot_see_each_other(): void
    {
        $this->joinAs('Ana');
        $media = $this->uploadViaChunks($this->jpeg(400, 300), 'a.jpg', 'image');
        $this->postJson('/api/my/contributions', ['items' => [['type' => 'photo', 'media_ids' => [$media['id']]]]])->assertCreated();
        $anaContribution = Contribution::firstOrFail();

        // Otro navegador: nueva sesión.
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->joinAs('Luis');

        $this->getJson('/api/my/contributions')->assertOk()->assertJsonCount(0, 'contributions');
        $this->get('/api/media/'.$media['id'])->assertNotFound();
        $this->putJson('/api/my/contributions/'.$anaContribution->id, ['body' => 'hack'])->assertNotFound();
        $this->deleteJson('/api/my/contributions/'.$anaContribution->id)->assertNotFound();
        // No puede adjuntar archivos de otra persona
        $this->postJson('/api/my/contributions', ['items' => [['type' => 'photo', 'media_ids' => [$media['id']]]]])->assertUnprocessable();
        $this->getJson('/api/birthday/memories')->assertForbidden();
    }

    public function test_personal_link_lets_participant_come_back_and_edit(): void
    {
        $data = $this->joinAs();
        $this->postJson('/api/my/contributions', ['items' => [['type' => 'message', 'body' => 'Hola']]])->assertCreated();
        $c = Contribution::firstOrFail();

        $this->flushSession();
        $this->get($data['personal_link'])->assertRedirect('/participar/mis-recuerdos');
        $this->getJson('/api/my/contributions')->assertOk()->assertJsonCount(1, 'contributions');

        $this->putJson('/api/my/contributions/'.$c->id, ['body' => 'Hola, editado'])->assertOk();
        $this->assertSame('Hola, editado', $c->fresh()->body);

        $this->deleteJson('/api/my/contributions/'.$c->id)->assertOk();
        $this->assertSame(0, Contribution::count());
    }

    public function test_guest_personal_invite_prefills_and_links_guest(): void
    {
        $guest = \App\Models\Guest::create(['name' => 'Tía Rosa', 'relationship' => 'family']);
        [, $plain] = AccessToken::issue(AccessToken::GUEST_INVITE, ['guest_id' => $guest->id]);

        $this->get('/invitacion/'.$plain)->assertRedirect('/participar');
        $this->getJson('/api/state')->assertJsonPath('prefill.name', 'Tía Rosa');
        $this->postJson('/api/participant', ['name' => 'Rosa', 'relationship' => 'family'])->assertCreated();
        $this->assertSame($guest->id, Participant::first()->guest_id);
    }

    public function test_submissions_close_after_deadline(): void
    {
        $this->joinAs();
        EventSetting::current()->update(['submissions_close_at' => now()->subMinute()]);
        $this->postJson('/api/my/contributions', ['items' => [['type' => 'message', 'body' => 'tarde']]])
            ->assertUnprocessable()->assertJsonValidationErrors('closed');
        $this->postJson('/api/uploads', ['kind' => 'image', 'name' => 'a.jpg', 'size' => 10])->assertUnprocessable();
    }

    public function test_pages_are_not_indexable_and_have_security_headers(): void
    {
        $this->get('/')->assertOk()
            ->assertHeader('X-Robots-Tag')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Content-Security-Policy');
        $this->assertStringContainsString('Disallow: /', file_get_contents(public_path('robots.txt')));
    }
}
