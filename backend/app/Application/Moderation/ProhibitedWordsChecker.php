<?php

namespace App\Application\Moderation;

class ProhibitedWordsChecker
{
    /**
     * @return list<string>
     */
    public function forbiddenMatches(string $text): array
    {
        $normalized = mb_strtolower(trim($text));

        if ($normalized === '') {
            return [];
        }

        $matches = [];

        foreach ($this->words() as $word) {
            $pattern = '/\b'.preg_quote(mb_strtolower($word), '/').'\b/u';

            if (preg_match($pattern, $normalized) === 1) {
                $matches[] = $word;
            }
        }

        return array_values(array_unique($matches));
    }

    public function containsProhibitedWords(string $text): bool
    {
        return $this->forbiddenMatches($text) !== [];
    }

    /**
     * @return list<string>
     */
    public function words(): array
    {
        /** @var list<string> $words */
        $words = config('prohibited_words.words', []);

        return $words;
    }
}
