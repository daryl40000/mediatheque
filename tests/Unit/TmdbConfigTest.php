<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\TmdbConfig;
use PHPUnit\Framework\TestCase;

final class TmdbConfigTest extends TestCase
{
    public function testClearStoredApiKeyRemovesFile(): void
    {
        $path = MONCINE_DATA . '/tmdb_api_key.txt';
        if (!is_dir(MONCINE_DATA)) {
            mkdir(MONCINE_DATA, 0750, true);
        }
        file_put_contents($path, "test-key-abc\n");

        $this->assertTrue(TmdbConfig::clearStoredApiKey());
        $this->assertFileDoesNotExist($path);
        $this->assertFalse(TmdbConfig::hasApiKey());
    }

    public function testGetKeySourceFile(): void
    {
        $path = MONCINE_DATA . '/tmdb_api_key.txt';
        if (!is_dir(MONCINE_DATA)) {
            mkdir(MONCINE_DATA, 0750, true);
        }
        file_put_contents($path, "file-source-key\n");

        $this->assertSame(TmdbConfig::SOURCE_FILE, TmdbConfig::getKeySource());

        @unlink($path);
    }
}
