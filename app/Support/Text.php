<?php

namespace App\Support;

class Text
{
    /**
     * Limpia texto libre: sin HTML, sin caracteres de control, saltos de línea normalizados.
     * El frontend siempre lo muestra como texto (nunca como HTML), así que esto es defensa en profundidad.
     */
    public static function clean(?string $value, int $max = 8000): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = strip_tags($value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
        $value = preg_replace("/\n{4,}/", "\n\n\n", $value) ?? '';
        $value = trim($value);
        $value = mb_substr($value, 0, $max);

        return $value === '' ? null : $value;
    }

    public static function line(?string $value, int $max = 200): ?string
    {
        $value = self::clean($value, $max * 2);
        if ($value === null) {
            return null;
        }
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
