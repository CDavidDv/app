<?php

namespace App\Support;

use App\Models\AccessToken;
use App\Models\EventSetting;
use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Centraliza cómo se reconoce a cada tipo de visitante:
 *  - Invitado: llegó por un enlace de invitación (general o personal).
 *  - Participante: ya se identificó; puede volver a editar sus aportaciones con su enlace personal.
 *  - Cumpleañera: llegó con su enlace secreto (y contraseña, si está configurada).
 *  - Administrador: autenticación Laravel con email y contraseña.
 */
class Access
{
    public const INVITE = 'pedacito.invite_token_id';
    public const PARTICIPANT = 'pedacito.participant_id';
    public const BIRTHDAY = 'pedacito.birthday_token_id';
    public const BIRTHDAY_PW = 'pedacito.birthday_pw_ok';
    public const PARTICIPANT_COOKIE = 'pedacito_p';

    public static function inviteToken(Request $request): ?AccessToken
    {
        $id = $request->session()->get(self::INVITE);
        if (! $id) {
            return null;
        }
        $token = AccessToken::query()->whereKey($id)->where('active', true)
            ->whereIn('type', [AccessToken::INVITE, AccessToken::GUEST_INVITE])->first();
        if (! $token) {
            $request->session()->forget(self::INVITE);
        }

        return $token;
    }

    public static function participant(Request $request): ?Participant
    {
        $id = $request->session()->get(self::PARTICIPANT);
        if ($id) {
            $p = Participant::find($id);
            if ($p) {
                return $p;
            }
            $request->session()->forget(self::PARTICIPANT);
        }

        // Recordar al participante en este dispositivo mediante cookie cifrada de larga duración.
        $plain = $request->cookie(self::PARTICIPANT_COOKIE);
        if (is_string($plain) && ($token = AccessToken::findValid($plain, AccessToken::PARTICIPANT))) {
            $p = Participant::where('access_token_id', $token->id)->first();
            if ($p) {
                $request->session()->put(self::PARTICIPANT, $p->id);

                return $p;
            }
        }

        return null;
    }

    public static function loginParticipant(Request $request, Participant $p, string $plainToken): void
    {
        $request->session()->put(self::PARTICIPANT, $p->id);
        Cookie::queue(Cookie::make(self::PARTICIPANT_COOKIE, $plainToken, 60 * 24 * 120, null, null, null, true, false, 'lax'));
    }

    public static function canParticipate(Request $request): bool
    {
        return self::inviteToken($request) !== null || self::participant($request) !== null;
    }

    public static function birthdayLinked(Request $request): bool
    {
        $id = $request->session()->get(self::BIRTHDAY);
        if (! $id) {
            return false;
        }
        $ok = AccessToken::query()->whereKey($id)->where('type', AccessToken::BIRTHDAY)->where('active', true)->exists();
        if (! $ok) {
            $request->session()->forget([self::BIRTHDAY, self::BIRTHDAY_PW]);
        }

        return $ok;
    }

    public static function birthdayUnlocked(Request $request): bool
    {
        if (! self::birthdayLinked($request)) {
            return false;
        }
        $settings = EventSetting::current();

        return ! $settings->hasBirthdayPassword() || $request->session()->get(self::BIRTHDAY_PW) === true;
    }

    public static function isAdmin(Request $request): bool
    {
        return $request->user() !== null;
    }
}
