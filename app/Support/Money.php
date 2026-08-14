<?php

namespace App\Support;

final class Money
{
    private function __construct()
    {
    }

    public static function format(int $minor): string
    {
        return number_format($minor / 100, 2, '.', '');
    }
}
