<?php

namespace App\Libraries;

/**
 * Menyimpan klien API yang terautentikasi selama satu request.
 */
class ApiAuth
{
    private static ?array $client = null;

    public static function setClient(?array $client): void
    {
        self::$client = $client;
    }

    public static function client(): ?array
    {
        return self::$client;
    }
}
