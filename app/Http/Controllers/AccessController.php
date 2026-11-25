<?php

namespace App\Http\Controllers;

use App\Models\AccessToken;
use App\Models\EventSetting;
use App\Models\Participant;
use App\Support\Access;
use App\Support\Present;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Entrada por enlaces secretos. El token se cambia por una sesión y se redirige
 * a una URL limpia, para que el token no quede visible en la barra del navegador.
 */
class AccessController extends Controller
{
    public function invite(Request $request, string $token): RedirectResponse
    {
        $t = AccessToken::findValid($token, [AccessToken::INVITE, AccessToken::GUEST_INVITE]);
        if (! $t) {
            return redirect('/enlace-no-valido');
        }
        $t->touchUse();
        $request->session()->regenerate();
        $request->session()->put(Access::INVITE, $t->id);

        return redirect('/participar');
    }

    public function mine(Request $request, string $token): RedirectResponse
    {
        $t = AccessToken::findValid($token, AccessToken::PARTICIPANT);
        $p = $t ? Participant::where('access_token_id', $t->id)->first() : null;
        if (! $p) {
            return redirect('/enlace-no-valido');
        }
        $t->touchUse();
        $request->session()->regenerate();
        Access::loginParticipant($request, $p, $token);

        return redirect('/participar/mis-recuerdos');
    }

    public function birthday(Request $request, string $token): RedirectResponse
    {
        $t = AccessToken::findValid($token, AccessToken::BIRTHDAY);
        if (! $t) {
            return redirect('/enlace-no-valido');
        }
        $t->touchUse();
        $request->session()->regenerate();
        $request->session()->put(Access::BIRTHDAY, $t->id);

        return redirect('/para-ti');
    }

    public function birthdayPassword(Request $request): JsonResponse
    {
        abort_unless(Access::birthdayLinked($request), 403);
        $key = 'birthday-pw:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 6)) {
            throw ValidationException::withMessages(['password' => 'Demasiados intentos. Espera un minuto.']);
        }
        $request->validate(['password' => ['required', 'string', 'max:200']]);
        $settings = EventSetting::current();
        if ($settings->hasBirthdayPassword() && ! Hash::check($request->string('password'), $settings->birthday_password)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['password' => 'Esa no es la palabra secreta. Inténtalo otra vez.']);
        }
        RateLimiter::clear($key);
        $request->session()->put(Access::BIRTHDAY_PW, true);

        return response()->json(['ok' => true]);
    }

    /** Estado de la visita actual: qué puede ver este navegador. */
    public function state(Request $request): JsonResponse
    {
        $settings = EventSetting::current()->load(['cover', 'music']);
        $invite = Access::inviteToken($request);
        $participant = Access::participant($request);
        $canParticipate = $invite !== null || $participant !== null;
        $birthdayLinked = Access::birthdayLinked($request);
        $birthdayUnlocked = Access::birthdayUnlocked($request);
        $admin = Access::isAdmin($request);

        $event = null;
        if ($canParticipate || $birthdayUnlocked || $admin) {
            $event = Present::event($settings);
            if (! $birthdayUnlocked && ! $admin) {
                // Los invitados no necesitan la foto ni la música de la página de la cumpleañera.
                $event['music'] = null;
            }
        } elseif ($birthdayLinked) {
            $event = ['celebrant_short_name' => $settings->celebrant_short_name];
        }

        $prefill = null;
        if ($invite?->type === AccessToken::GUEST_INVITE && $invite->guest) {
            $prefill = ['name' => $invite->guest->name, 'relationship' => $invite->guest->relationship];
        }

        return response()->json([
            'can_participate' => $canParticipate,
            'participant' => $participant ? Present::participant($participant, true) + [
                'contributions_count' => $participant->contributions()->count(),
            ] : null,
            'prefill' => $prefill,
            'birthday' => [
                'linked' => $birthdayLinked,
                'unlocked' => $birthdayUnlocked,
                'needs_password' => $birthdayLinked && ! $birthdayUnlocked,
            ],
            'admin' => $admin ? ['name' => $request->user()->name, 'email' => $request->user()->email] : null,
            'event' => $event,
            'relationships' => Participant::RELATIONSHIPS,
            'limits' => [
                'image_mb' => config('pedacito.limits.image_mb'),
                'video_mb' => config('pedacito.limits.video_mb'),
                'chunk_bytes' => config('pedacito.chunk_size_mb') * 1024 * 1024,
                'photos_per_memory' => config('pedacito.limits.photos_per_memory'),
                'videos_per_contribution' => config('pedacito.limits.videos_per_contribution'),
                'message_chars' => config('pedacito.limits.message_chars'),
                'letter_chars' => config('pedacito.limits.letter_chars'),
                'text_chars' => config('pedacito.limits.text_chars'),
            ],
        ]);
    }
}
