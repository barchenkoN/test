<?php

namespace App\Support;

final class Money
{
    private function __construct()
    {
    }

    public static function format(int $minor): string
    {
        $whole = intdiv($minor, 100);
        $fraction = $minor % 100;

        return sprintf('%d.%02d', $whole, $fraction);
    }
}
