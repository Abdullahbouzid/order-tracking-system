<?php

namespace App\Helpers;

class Utf8Cleaner
{
    public static function clean($string)
    {
        if (is_null($string)) return null;
        if (is_array($string)) {
            return array_map([self::class, 'clean'], $string);
        }
        // إزالة الأحرف غير الصالحة لـ UTF-8
        $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        $string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $string);
        return $string;
    }

    public static function cleanCollection($collection)
    {
        foreach ($collection as $item) {
            foreach ($item->getAttributes() as $key => $value) {
                if (is_string($value)) {
                    $item->$key = self::clean($value);
                }
            }
        }
        return $collection;
    }
}