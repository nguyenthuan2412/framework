<?php

namespace Ryra\Core\Contracts\Translation;

interface MessageSelector
{
    /**
     * Select the proper translation string based on the count.
     *
     * Supports formats:
     * - Simple pipe-delimited: "apple|apples"
     * - Explicit ranges: "{0} No items|{1} One item|[2,*] :count items"
     *
     * @param string $message The message with plural forms (pipe-delimited)
     * @param int|float $count The count for pluralization
     * @param string $locale The locale for pluralization rules
     * @return string The selected message
     */
    public function choose(string $message, int|float $count, string $locale): string;
}
