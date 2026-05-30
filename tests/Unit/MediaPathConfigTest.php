<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\MediaPathConfig;
use PHPUnit\Framework\TestCase;

final class MediaPathConfigTest extends TestCase
{
    private string $tmpDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/moncine_media_cfg_' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0750, true);
    }

    protected function tearDown(): void
    {
        @rmdir($this->tmpDir);
        parent::tearDown();
    }

    public function testValidateRejectsRelativePath(): void
    {
        $result = MediaPathConfig::validateRootPath('data/media');
        $this->assertIsString($result);
    }

    public function testValidateRejectsEtc(): void
    {
        $result = MediaPathConfig::validateRootPath('/etc/moncine');
        $this->assertIsString($result);
        $this->assertStringContainsString('autorisé', (string) $result);
    }

    public function testValidateAcceptsWritableTempDir(): void
    {
        $result = MediaPathConfig::validateRootPath($this->tmpDir);
        $this->assertTrue($result);
    }
}
