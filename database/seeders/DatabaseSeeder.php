<?php

namespace Database\Seeders;

use App\Models\AccessToken;
use App\Models\EventSetting;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public const QUESTIONS = [
        '¿Cuál es tu recuerdo favorito con ella?',
        '¿Qué es lo que más admiras de ella?',
        '¿Qué cambió en tu vida desde que la conociste?',
        '¿Qué le deseas para esta nueva etapa?',
        '¿Qué quisieras que nunca olvidara?',
        '¿Qué palabra utilizarías para describirla y por qué?',
        '¿Qué momento juntos repetirías una y otra vez?',
        '¿Qué crees que ella no sabe sobre lo importante que es para ti?',
    ];

    public function run(): void
    {
        // Administrador inicial (credenciales desde .env).
        $email = config('pedacito.admin.email');
        if (! User::where('email', $email)->exists()) {
            $password = config('pedacito.admin.password') ?: Str::password(16, symbols: false);
            User::create([
                'name' => config('pedacito.admin.name'),
                'email' => $email,
                'password' => Hash::make($password),
            ]);
            if (! config('pedacito.admin.password')) {
                $this->command?->warn("Contraseña generada para {$email}: {$password}");
            }
        }

        EventSetting::current();

        foreach (self::QUESTIONS as $i => $text) {
            Question::updateOrCreate(['text' => $text], ['position' => $i + 1, 'active' => true]);
        }

        foreach ([AccessToken::INVITE => 'Invitación general', AccessToken::BIRTHDAY => 'Enlace de la cumpleañera'] as $type => $label) {
            if (! AccessToken::where('type', $type)->where('active', true)->exists()) {
                AccessToken::issue($type, ['label' => $label]);
            }
        }
    }
}
