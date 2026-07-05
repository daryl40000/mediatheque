<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\SteamGameResolver;
use Moncine\SteamTitleMatch;
use PHPUnit\Framework\TestCase;

final class SteamTitleMatchTest extends TestCase
{
    public function testFoldedKeysIncludesBaseTitleBeforeSuffix(): void
    {
        $keys = SteamTitleMatch::foldedKeys('The Witcher 3: Wild Hunt');
        $base = SteamTitleMatch::foldKey('The Witcher 3');

        $this->assertContains($base, $keys);
        $this->assertGreaterThan(1, count($keys));
    }

    public function testExtractAppIdFromStoreUrl(): void
    {
        $this->assertSame(
            292030,
            SteamGameResolver::extractAppIdFromStoreUrl('https://store.steampowered.com/app/292030/The_Witcher_3/')
        );
        $this->assertSame(0, SteamGameResolver::extractAppIdFromStoreUrl(''));
    }

    public function testSlugFromStoreUrl(): void
    {
        $this->assertSame(
            'the witcher 3 wild hunt',
            SteamTitleMatch::foldKey(SteamTitleMatch::titleFromStoreUrlSlug(
                'https://store.steampowered.com/app/292030/The_Witcher_3_Wild_Hunt/'
            ))
        );
    }

    public function testSteamStoreSlugUsesUnderscores(): void
    {
        $this->assertSame('The_Witcher_3', SteamTitleMatch::steamStoreSlug('The Witcher 3'));
    }
}
