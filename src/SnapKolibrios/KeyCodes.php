<?php

namespace SnapKolibrios;

class KeyCodes
{
    public const MAP = [
        'right arrow' => 77,
        'left arrow' => 75,
        'up arrow' => 72,
        'down arrow' => 80,
        'space' => 57,
        'clicked' => 1,
        'pressed' => 2,
        'mouse-entered' => 4,
        'mouse-departed' => 5,
        'random position' => 1,
        'mouse-pointer' => 2,
        'myself' => 1,
    ];

    public static function getCode(string $key): int
    {
        if (isset(self::MAP[$key])) {
            return self::MAP[$key];
        }
        return (int)$key;
    }
}
