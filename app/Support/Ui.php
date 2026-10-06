<?php

namespace App\Support;

use Carbon\Carbon;

class Ui
{
    public static function digits(mixed $value): string
    {
        return app()->getLocale() === 'bn' ? strtr((string) $value, array_combine(str_split('0123456789'), preg_split('//u', '০১২৩৪৫৬৭৮৯', -1, PREG_SPLIT_NO_EMPTY))) : (string) $value;
    }

    public static function ascii(mixed $value): mixed
    {
        return is_string($value) ? strtr($value, array_combine(preg_split('//u', '০১২৩৪৫৬৭৮৯', -1, PREG_SPLIT_NO_EMPTY), str_split('0123456789'))) : $value;
    }

    public static function number(mixed $value): string
    {
        return $value === null ? '—' : static::digits(number_format((float) $value));
    }

    public static function date(mixed $value, string $format = 'd M Y'): string
    {
        if (! $value) {
            return '—';
        }

        return static::digits(Carbon::parse($value)->locale(app()->getLocale())->translatedFormat($format));
    }
}
