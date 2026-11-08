<?php

namespace Ryra\Core\Translation;

class MessageSelector implements \Ryra\Core\Contracts\Translation\MessageSelector
{
    /**
     * {@inheritdoc}
     *
     * Supports two formats:
     * 1. Simple pipe-delimited: "apple|apples"
     * 2. Explicit ranges: "{0} No items|{1} One item|[2,*] :count items"
     */
    public function choose(string $message, int|float $count, string $locale): string
    {
        $segments = explode('|', $message);

        // First, check for explicit ranges like {0}, {1}, [2,5], [6,*]
        foreach ($segments as $segment) {
            $segment = trim($segment);

            // Exact match: {0} No items, {1} One item
            if (preg_match('/^\{(\d+)\}\s*(.*)$/', $segment, $matches)) {
                if ((int) $matches[1] === (int) $count) {
                    return trim($matches[2]);
                }
                continue;
            }

            // Range match: [2,5] Some items, [6,*] Many items
            if (preg_match('/^\[(\d+),(\d+|\*)\]\s*(.*)$/', $segment, $matches)) {
                $from = (int) $matches[1];
                $to = $matches[2] === '*' ? PHP_INT_MAX : (int) $matches[2];

                if ($count >= $from && $count <= $to) {
                    return trim($matches[3]);
                }
                continue;
            }
        }

        // Fallback to simple pluralization (segments without explicit ranges)
        $simpleSegments = array_filter($segments, function ($segment) {
            $segment = trim($segment);
            return !preg_match('/^\{|\^\[/', $segment);
        });

        // If all segments had explicit ranges but none matched, extract just the text
        if (empty($simpleSegments)) {
            $simpleSegments = array_map(function ($segment) {
                $segment = trim($segment);
                // Remove range prefix if present
                $segment = preg_replace('/^\{\d+\}\s*/', '', $segment);
                $segment = preg_replace('/^\[\d+,[\d\*]+\]\s*/', '', $segment);
                return $segment;
            }, $segments);
        }

        $simpleSegments = array_values($simpleSegments);

        // Apply CLDR plural rules based on locale
        $pluralForm = $this->getPluralForm($count, $locale);

        return trim($simpleSegments[$pluralForm] ?? end($simpleSegments));
    }

    /**
     * Get the plural form index based on CLDR rules.
     *
     * @param int|float $count
     * @param string $locale
     * @return int
     */
    protected function getPluralForm(int|float $count, string $locale): int
    {
        // Normalize count: use absolute integer value
        $count = abs((int) $count);

        // Extract base language code (vi_VN -> vi, en-US -> en)
        $lang = strtolower(preg_replace('/[_-].*/', '', $locale));

        return match ($lang) {
            // Languages with no plural form (singular always)
            // Vietnamese, Chinese, Japanese, Korean, Thai, Indonesian, Malay, Lao, Khmer, Burmese, Tibetan, Dzongkha
            'vi', 'zh', 'ja', 'ko', 'th', 'id', 'ms', 'lo', 'km', 'my', 'bo', 'dz' => 0,

            // Two forms: 1 / other
            // English, German, Spanish, Italian, Portuguese, Dutch, Swedish, Danish, Norwegian, Finnish, Greek, Hebrew, Hungarian, Turkish, etc.
            'en', 'de', 'es', 'it', 'pt', 'nl', 'sv', 'da', 'no', 'nb', 'nn', 'fi', 'el', 'he', 'hu', 'tr', 'bg', 'et', 'fo', 'gl', 'lb',
            // African languages
            'af', 'ha', 'ig', 'yo', 'zu', 'xh', 'sw', 'so', 'sn', 'st', 'tn', 'ts', 've', 'ss', 'nd', 'lg', 'rw', 'ny', 'mg', 'ee',
            // South Asian languages
            'bn', 'pa', 'ta', 'te', 'ml', 'mr', 'ne', 'or', 'si', 'ur', 'as', 'ks', 'sd',
            // Central Asian languages
            'kk', 'ky', 'uz', 'tk', 'ug',
            // Caucasian languages
            'ka', 'hy', 'az', 'os',
            // Other European languages
            'sq', 'eu', 'ca', 'eo', 'fy', 'ku', 'ckb', 'ast', 'fur', 'gsw', 'rm', 'wae',
            // Pacific/Other languages
            'haw', 'mn', 'ia', 'io', 'vo', 'yi',
            // Additional CLDR languages
            'bem', 'brx', 'chr', 'jgo', 'jmc', 'kaj', 'kcg', 'kkj', 'kl', 'ksb', 'mas', 'mgo',
            'nah', 'nnh', 'nyn', 'om', 'pap', 'ps', 'rof', 'rwk', 'saq', 'sdh', 'seh', 'ssy',
            'syr', 'teo', 'tig', 'vun', 'xog' =>
                ($count == 1) ? 0 : 1,

            // French-style: 0-1 / other (also Hindi, Bangla when using integers)
            'fr', 'ff', 'kab', 'hi', 'gu', 'kn', 'fa' => ($count == 0 || $count == 1) ? 0 : 1,

            // Icelandic: special rule based on mod 10/100
            'is' => $this->getIcelandicPluralForm($count),

            // Macedonian: special rule
            'mk' => $this->getMacedonianPluralForm($count),

            // Filipino/Tagalog: 1,2,3 and ends in 4,6,9 / other
            'fil', 'tl' => $this->getFilipinoPluralForm($count),

            // Russian/Ukrainian/Belarusian/Croatian/Serbian/Bosnian style: 1 / 2-4 / other
            'ru', 'uk', 'be', 'hr', 'sr', 'bs' => $this->getSlavicPluralForm($count),

            // Polish style (slightly different)
            'pl' => $this->getPolishPluralForm($count),

            // Czech/Slovak style
            'cs', 'sk' => $this->getCzechPluralForm($count),

            // Arabic: 0 / 1 / 2 / 3-10 / 11-99 / 100+
            'ar' => $this->getArabicPluralForm($count),

            // Romanian/Moldavian: 1 / 2-19 / 20+
            'ro', 'mo' => $this->getRomanianPluralForm($count),

            // Lithuanian
            'lt' => $this->getLithuanianPluralForm($count),

            // Latvian
            'lv' => $this->getLatvianPluralForm($count),

            // Slovenian: 1 / 2 / 3-4 / other (4 forms)
            'sl' => $this->getSlovenianPluralForm($count),

            // Irish: 1 / 2 / 3-6 / 7-10 / other (5 forms)
            'ga' => $this->getIrishPluralForm($count),

            // Welsh: 0 / 1 / 2 / 3 / 6 / other (6 forms)
            'cy' => $this->getWelshPluralForm($count),

            // Scottish Gaelic: 1,11 / 2,12 / 3-10,13-19 / other (4 forms)
            'gd' => $this->getScottishGaelicPluralForm($count),

            // Maltese: 1 / 0,11-19 / 2-10 / other (4 forms)
            'mt' => $this->getMaltesePluralForm($count),

            // Breton: special rule
            'br' => $this->getBretonPluralForm($count),

            // Default: simple two-form (1 / other)
            default => ($count == 1) ? 0 : 1,
        };
    }

    /**
     * Get Slavic plural form (Russian, Ukrainian, Belarusian, Croatian, Serbian, Bosnian).
     * Forms: singular (1) / few (2-4) / many (other)
     */
    protected function getSlavicPluralForm(int|float $count): int
    {
        $count = abs((int) $count);
        $mod10 = $count % 10;
        $mod100 = $count % 100;

        if ($mod10 === 1 && $mod100 !== 11) {
            return 0; // singular: 1, 21, 31, 41...
        }

        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
            return 1; // few: 2-4, 22-24, 32-34...
        }

        return 2; // many: 0, 5-20, 25-30...
    }

    /**
     * Get Polish plural form.
     * Forms: singular (1) / few (2-4, except 12-14) / many (other)
     */
    protected function getPolishPluralForm(int|float $count): int
    {
        $count = abs((int) $count);

        if ($count === 1) {
            return 0;
        }

        $mod10 = $count % 10;
        $mod100 = $count % 100;

        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
            return 1;
        }

        return 2;
    }

    /**
     * Get Czech/Slovak plural form.
     * Forms: singular (1) / few (2-4) / many (other)
     */
    protected function getCzechPluralForm(int|float $count): int
    {
        $count = abs((int) $count);

        if ($count === 1) {
            return 0;
        }

        if ($count >= 2 && $count <= 4) {
            return 1;
        }

        return 2;
    }

    /**
     * Get Arabic plural form.
     * Forms: zero / one / two / few (3-10) / many (11-99) / other
     */
    protected function getArabicPluralForm(int|float $count): int
    {
        $count = abs((int) $count);

        if ($count === 0) return 0;
        if ($count === 1) return 1;
        if ($count === 2) return 2;
        if ($count % 100 >= 3 && $count % 100 <= 10) return 3;
        if ($count % 100 >= 11) return 4;

        return 5;
    }

    /**
     * Get Romanian plural form.
     * Forms: singular (1) / few (0, 2-19, x01-x19) / many (other)
     */
    protected function getRomanianPluralForm(int|float $count): int
    {
        $count = abs((int) $count);

        if ($count === 1) {
            return 0;
        }

        if ($count === 0 || ($count % 100 > 0 && $count % 100 < 20)) {
            return 1;
        }

        return 2;
    }

    /**
     * Get Lithuanian plural form.
     * Forms: singular / few / many
     */
    protected function getLithuanianPluralForm(int|float $count): int
    {
        $count = abs((int) $count);
        $mod10 = $count % 10;
        $mod100 = $count % 100;

        if ($mod10 === 1 && $mod100 !== 11) {
            return 0;
        }

        if ($mod10 >= 2 && $mod10 <= 9 && ($mod100 < 11 || $mod100 > 19)) {
            return 1;
        }

        return 2;
    }

    /**
     * Get Latvian plural form.
     * Forms: zero / singular / many
     */
    protected function getLatvianPluralForm(int|float $count): int
    {
        $count = abs((int) $count);

        if ($count === 0) {
            return 0;
        }

        $mod10 = $count % 10;
        $mod100 = $count % 100;

        if ($mod10 === 1 && $mod100 !== 11) {
            return 1;
        }

        return 2;
    }

    /**
     * Get Icelandic plural form.
     * Forms: one (1, 21, 31...) / other
     */
    protected function getIcelandicPluralForm(int|float $count): int
    {
        $count = abs((int) $count);
        $mod10 = $count % 10;
        $mod100 = $count % 100;

        if ($mod10 === 1 && $mod100 !== 11) {
            return 0;
        }

        return 1;
    }

    /**
     * Get Macedonian plural form.
     * Forms: one (ends in 1, not 11) / other
     */
    protected function getMacedonianPluralForm(int|float $count): int
    {
        $count = abs((int) $count);
        $mod10 = $count % 10;
        $mod100 = $count % 100;

        if ($mod10 === 1 && $mod100 !== 11) {
            return 0;
        }

        return 1;
    }

    /**
     * Get Filipino/Tagalog plural form.
     * Forms: one (1,2,3 or ends in 4,6,9 but not 10-19) / other
     */
    protected function getFilipinoPluralForm(int|float $count): int
    {
        $count = abs((int) $count);
        $mod10 = $count % 10;
        $mod100 = $count % 100;

        if ($count === 1 || $count === 2 || $count === 3 ||
            (($mod10 === 4 || $mod10 === 6 || $mod10 === 9) && ($mod100 < 10 || $mod100 > 19))) {
            return 0;
        }

        return 1;
    }

    /**
     * Get Slovenian plural form.
     * Forms: one (1, 101, 201...) / two (2, 102, 202...) / few (3-4, 103-104...) / other
     */
    protected function getSlovenianPluralForm(int|float $count): int
    {
        $count = abs((int) $count);
        $mod100 = $count % 100;

        if ($mod100 === 1) {
            return 0; // one
        }

        if ($mod100 === 2) {
            return 1; // two
        }

        if ($mod100 === 3 || $mod100 === 4) {
            return 2; // few
        }

        return 3; // other
    }

    /**
     * Get Irish plural form.
     * Forms: one (1) / two (2) / few (3-6) / many (7-10) / other
     */
    protected function getIrishPluralForm(int|float $count): int
    {
        $count = abs((int) $count);

        if ($count === 1) {
            return 0; // one
        }

        if ($count === 2) {
            return 1; // two
        }

        if ($count >= 3 && $count <= 6) {
            return 2; // few
        }

        if ($count >= 7 && $count <= 10) {
            return 3; // many
        }

        return 4; // other
    }

    /**
     * Get Welsh plural form.
     * Forms: zero (0) / one (1) / two (2) / few (3) / many (6) / other
     */
    protected function getWelshPluralForm(int|float $count): int
    {
        $count = abs((int) $count);

        return match ($count) {
            0 => 0, // zero
            1 => 1, // one
            2 => 2, // two
            3 => 3, // few
            6 => 4, // many
            default => 5, // other
        };
    }

    /**
     * Get Scottish Gaelic plural form.
     * Forms: one (1, 11) / two (2, 12) / few (3-10, 13-19) / other
     */
    protected function getScottishGaelicPluralForm(int|float $count): int
    {
        $count = abs((int) $count);

        if ($count === 1 || $count === 11) {
            return 0; // one
        }

        if ($count === 2 || $count === 12) {
            return 1; // two
        }

        if (($count >= 3 && $count <= 10) || ($count >= 13 && $count <= 19)) {
            return 2; // few
        }

        return 3; // other
    }

    /**
     * Get Maltese plural form.
     * Forms: one (1) / few (0 or 2-10) / many (11-19) / other
     */
    protected function getMaltesePluralForm(int|float $count): int
    {
        $count = abs((int) $count);
        $mod100 = $count % 100;

        if ($count === 1) {
            return 0; // one
        }

        if ($count === 0 || ($mod100 >= 2 && $mod100 <= 10)) {
            return 1; // few
        }

        if ($mod100 >= 11 && $mod100 <= 19) {
            return 2; // many
        }

        return 3; // other
    }

    /**
     * Get Breton plural form.
     * Forms: one (1) / two (2) / few (3,4,9) / many (1000000+) / other
     */
    protected function getBretonPluralForm(int|float $count): int
    {
        $count = abs((int) $count);
        $mod10 = $count % 10;
        $mod100 = $count % 100;

        if ($mod10 === 1 && !in_array($mod100, [11, 71, 91])) {
            return 0; // one
        }

        if ($mod10 === 2 && !in_array($mod100, [12, 72, 92])) {
            return 1; // two
        }

        if (in_array($mod10, [3, 4, 9]) && !($mod100 >= 10 && $mod100 <= 19) &&
            !($mod100 >= 70 && $mod100 <= 79) && !($mod100 >= 90 && $mod100 <= 99)) {
            return 2; // few
        }

        if ($count !== 0 && $count % 1000000 === 0) {
            return 3; // many
        }

        return 4; // other
    }
}
