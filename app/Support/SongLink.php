<?php

namespace App\Support;

/**
 * Enlaces de canciones (YouTube, Spotify, Apple Music, etc.).
 * Solo se aceptan URLs http(s) bien formadas; nunca javascript:, data:, etc.
 */
class SongLink
{
    /** Dominio => nombre de la plataforma. Se compara contra el dominio y sus subdominios. */
    private const PLATFORMS = [
        'music.youtube.com' => 'YouTube Music',
        'youtube.com' => 'YouTube',
        'youtu.be' => 'YouTube',
        'spotify.com' => 'Spotify',
        'spotify.link' => 'Spotify',
        'spoti.fi' => 'Spotify',
        'music.apple.com' => 'Apple Music',
        'deezer.com' => 'Deezer',
        'deezer.page.link' => 'Deezer',
        'soundcloud.com' => 'SoundCloud',
        'tidal.com' => 'Tidal',
        'music.amazon.com' => 'Amazon Music',
        'music.amazon.com.mx' => 'Amazon Music',
        'music.amazon.es' => 'Amazon Music',
    ];

    /** Normaliza el enlace o devuelve null si no es válido. */
    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        // A veces se copia junto con texto ("Escucha esto en Spotify: https://…").
        if (preg_match('~https?://\S+~i', $value, $m)) {
            $value = $m[0];
        } elseif (! preg_match('~^[a-z][a-z0-9+.-]*:~i', $value)) {
            $value = 'https://'.$value;
        }
        if (mb_strlen($value) > 500 || preg_match('/[\s<>"\'`]/', $value)) {
            return null;
        }
        $parts = parse_url($value);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true) || ! str_contains($host, '.') || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return $value;
    }

    public static function platform(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
        $host = preg_replace('/^(www|m|open|on|listen|play)\./', '', $host) ?? $host;
        foreach (self::PLATFORMS as $domain => $name) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return $name;
            }
        }

        return $host ?: null;
    }
}
