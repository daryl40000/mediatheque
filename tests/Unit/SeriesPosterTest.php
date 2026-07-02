<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\PosterStorage;
use Moncine\SeriesPoster;
use PHPUnit\Framework\TestCase;

final class SeriesPosterTest extends TestCase
{
    /** @var list<string> */
    private array $tempPosterFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempPosterFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        parent::tearDown();
    }

    public function testResolveWebPathPrefersDedicatedSeriesPoster(): void
    {
        $seriesPoster = $this->seedPosterFile('s42.jpg');
        $firstPoster = $this->seedPosterFile('10.jpg');

        $path = SeriesPoster::resolveWebPath([
            'poster_url' => '/posters/s42.jpg',
            'first_volume_poster_url' => '/posters/10.jpg',
            'latest_poster_url' => '/posters/99.jpg',
        ]);

        $this->assertSame('/posters/s42.jpg', $path);
        $this->assertFileExists($seriesPoster);
        $this->assertFileExists($firstPoster);
    }

    public function testResolveWebPathFallsBackToFirstVolumeWhenSeriesPosterMissingOnDisk(): void
    {
        $firstPoster = $this->seedPosterFile('10.jpg');

        $path = SeriesPoster::resolveWebPath([
            'poster_url' => '/posters/s42.jpg',
            'first_volume_poster_url' => '/posters/10.jpg',
            'latest_poster_url' => '/posters/99.jpg',
        ]);

        $this->assertSame('/posters/10.jpg', $path);
        $this->assertFileExists($firstPoster);
    }

    public function testResolveWebPathReturnsEmptyWhenNoDisplayableCandidate(): void
    {
        $path = SeriesPoster::resolveWebPath([
            'poster_url' => '/posters/missing-series.jpg',
            'first_volume_poster_url' => '/posters/missing-first.jpg',
        ]);

        $this->assertSame('', $path);
    }

    public function testCandidateWebPathsKeepsPriorityOrder(): void
    {
        $paths = SeriesPoster::candidateWebPaths([
            'poster_url' => '/posters/s1.jpg',
            'first_volume_poster_url' => '/posters/1.jpg',
            'latest_poster_url' => '/posters/9.jpg',
        ]);

        $this->assertSame(['/posters/s1.jpg', '/posters/1.jpg', '/posters/9.jpg'], $paths);
    }

    public function testEnrichSeriesUsesFirstCatalogCoverWhenNoSeriesPoster(): void
    {
        $this->seedPosterFile('1.jpg');

        $series = SeriesPoster::enrichSeries([
            'id' => 0,
            'poster_url' => '',
            'first_volume_poster_url' => '/posters/1.jpg',
        ]);

        $this->assertSame('/posters/1.jpg', $series['effective_poster_url']);
    }

    private function seedPosterFile(string $basename): string
    {
        $dir = PosterStorage::postersFilesystemDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir . '/' . $basename;
        file_put_contents($path, "\xFF\xD8\xFF\xE0" . str_repeat('x', 32));
        $this->tempPosterFiles[] = $path;

        return $path;
    }
}
