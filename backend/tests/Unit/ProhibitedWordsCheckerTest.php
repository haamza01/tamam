<?php

namespace Tests\Unit;

use App\Application\Moderation\ProhibitedWordsChecker;
use Tests\TestCase;

class ProhibitedWordsCheckerTest extends TestCase
{
    public function test_detects_configured_prohibited_words(): void
    {
        $checker = app(ProhibitedWordsChecker::class);

        $this->assertTrue($checker->containsProhibitedWords('This listing is a scam offer.'));
        $this->assertSame(['scam'], $checker->forbiddenMatches('This listing is a scam offer.'));
    }

    public function test_allows_clean_text(): void
    {
        $checker = app(ProhibitedWordsChecker::class);

        $this->assertFalse($checker->containsProhibitedWords('Gently used sofa in West Bay.'));
    }
}
