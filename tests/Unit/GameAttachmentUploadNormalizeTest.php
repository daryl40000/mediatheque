<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\GameAttachmentRepository;
use PHPUnit\Framework\TestCase;

final class GameAttachmentUploadNormalizeTest extends TestCase
{
    public function testNormalizeSingleFileShape(): void
    {
        $uploads = GameAttachmentRepository::normalizeUploadedFiles([
            'name' => 'manuel.pdf',
            'tmp_name' => '/tmp/phpABC',
            'size' => 1024,
            'error' => UPLOAD_ERR_OK,
        ]);

        $this->assertCount(1, $uploads);
        $this->assertSame('manuel.pdf', $uploads[0]['name']);
        $this->assertSame(1024, $uploads[0]['size']);
    }

    public function testNormalizeMultipleFilesShape(): void
    {
        $uploads = GameAttachmentRepository::normalizeUploadedFiles([
            'name' => ['manuel.pdf', 'soluce.pdf', 'ignored.bin'],
            'tmp_name' => ['/tmp/a', '/tmp/b', ''],
            'size' => [10, 20, 0],
            'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE],
        ]);

        $this->assertCount(2, $uploads);
        $this->assertSame('manuel.pdf', $uploads[0]['name']);
        $this->assertSame('soluce.pdf', $uploads[1]['name']);
    }

    public function testNormalizeEmptyReturnsEmptyList(): void
    {
        $this->assertSame([], GameAttachmentRepository::normalizeUploadedFiles(null));
        $this->assertSame([], GameAttachmentRepository::normalizeUploadedFiles([
            'name' => '',
            'tmp_name' => '',
            'size' => 0,
            'error' => UPLOAD_ERR_NO_FILE,
        ]));
    }
}
