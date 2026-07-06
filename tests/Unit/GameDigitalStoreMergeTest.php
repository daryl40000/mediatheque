<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\GameDigitalStore;
use PHPUnit\Framework\TestCase;

final class GameDigitalStoreMergeTest extends TestCase
{
    public function testMergeStoreAddsSteamWithoutDuplicate(): void
    {
        $existing = GameDigitalStore::serializeList([
            ['store' => GameDigitalStore::GOG, 'url' => 'https://www.gog.com/game/example'],
        ]);
        $merged = GameDigitalStore::mergeStore(
            $existing,
            GameDigitalStore::STEAM,
            'https://store.steampowered.com/app/123/'
        );

        $stores = GameDigitalStore::parseStoredList($merged);
        $keys = array_column($stores, 'store');

        $this->assertContains(GameDigitalStore::GOG, $keys);
        $this->assertContains(GameDigitalStore::STEAM, $keys);
        $this->assertCount(2, $keys);
    }

    public function testMergeStoreUpdatesEmptyUrl(): void
    {
        $existing = GameDigitalStore::serializeList([
            ['store' => GameDigitalStore::STEAM, 'url' => ''],
        ]);
        $merged = GameDigitalStore::mergeStore(
            $existing,
            GameDigitalStore::STEAM,
            'https://store.steampowered.com/app/440/'
        );

        $stores = GameDigitalStore::parseStoredList($merged);
        $this->assertSame('https://store.steampowered.com/app/440/', $stores[0]['url'] ?? '');
    }

    public function testMergeStoreReplacesExistingUrl(): void
    {
        $existing = GameDigitalStore::serializeList([
            ['store' => GameDigitalStore::STEAM, 'url' => 'https://store.steampowered.com/app/1/old/'],
        ]);
        $merged = GameDigitalStore::mergeStore(
            $existing,
            GameDigitalStore::STEAM,
            'https://store.steampowered.com/app/1/new_slug/'
        );

        $stores = GameDigitalStore::parseStoredList($merged);
        $this->assertSame('https://store.steampowered.com/app/1/new_slug/', $stores[0]['url'] ?? '');
    }
}
