<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\MediaPathConfig;
use Moncine\MediaStorageService;
use Moncine\Tests\Support\MoncineTestCase;

final class MediaStorageIntegrationTest extends MoncineTestCase
{
    private string $mediaRoot = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->mediaRoot = MONCINE_DATA . '/media_test_' . bin2hex(random_bytes(3));
        MediaPathConfig::saveRootPath($this->mediaRoot);
    }

    protected function tearDown(): void
    {
        MediaPathConfig::forgetCachedRoot();
        if (is_dir($this->mediaRoot)) {
            $this->removeTree($this->mediaRoot);
        }
        parent::tearDown();
    }

    public function testStoreBinaryCreatesFileAndRow(): void
    {
        $service = new MediaStorageService();
        $result = $service->storeBinary('tmp', 'integration.txt', 'payload', 'text/plain');
        $this->assertIsArray($result);
        $this->assertGreaterThan(0, $result['stored_object_id']);

        $path = $this->mediaRoot . '/tmp/integration.txt';
        $this->assertFileExists($path);
        $this->assertSame('payload', file_get_contents($path));
    }

    public function testSelfTestSucceeds(): void
    {
        $test = MediaPathConfig::runSelfTest();
        $this->assertTrue($test['ok'], $test['message']);
    }

    private function removeTree(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                $this->removeTree($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
