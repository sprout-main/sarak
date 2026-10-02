<?php

namespace App\Support;

final class Text
{
    public static function fold(string $value): string
    {
        $value = self::foldDiacritics($value);

        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    public static function normalize(string $value): string
    {
        $value = self::fold($value);
        $value = (string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value));

        return trim($value);
    }

    public static function namesSimilar(string $left, string $right, float $minPercent = 70.0, int $maxDistance = 2): bool
    {
        $normalizedLeft = self::normalize($left);
        $normalizedRight = self::normalize($right);

        if ($normalizedLeft === '' || $normalizedRight === '') {
            return false;
        }

        if ($normalizedLeft === $normalizedRight) {
            return true;
        }

        $shorter = strlen($normalizedLeft) <= strlen($normalizedRight) ? $normalizedLeft : $normalizedRight;
        $longer = strlen($normalizedLeft) > strlen($normalizedRight) ? $normalizedLeft : $normalizedRight;

        if (strlen($shorter) >= 4 && str_contains($longer, $shorter)) {
            return true;
        }

        similar_text($normalizedLeft, $normalizedRight, $percent);

        if ($percent >= $minPercent) {
            return true;
        }

        if (strlen($shorter) >= 5 && strlen($longer) <= 64) {
            if (levenshtein($shorter, $longer) <= $maxDistance) {
                return true;
            }
        }

        return false;
    }

    private static function foldDiacritics(string $value): string
    {
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_D);
            if ($normalized !== false) {
                $stripped = preg_replace('/\p{Mn}/u', '', $normalized);
                if ($stripped !== null) {
                    return $stripped;
                }
            }
        }

        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($converted !== false) {
                return $converted;
            }
        }

        return $value;
    }
}