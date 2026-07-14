<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\MagazineSeriesCategory;
use PHPUnit\Framework\TestCase;

final class MagazineSeriesCategoryTest extends TestCase
{
    public function testNormalizeAndParseCategories(): void
    {
        $this->assertSame(MagazineSeriesCategory::JEUX_VIDEO, MagazineSeriesCategory::normalizeLabel('jeux video'));
        $this->assertSame(MagazineSeriesCategory::CINEMA, MagazineSeriesCategory::normalizeLabel('cinema'));
        $this->assertSame(MagazineSeriesCategory::FIGURINES, MagazineSeriesCategory::normalizeLabel('figurine'));
        $this->assertSame(MagazineSeriesCategory::DIVERS, MagazineSeriesCategory::normalizeLabel('divers'));

        $this->assertSame(
            ['Jeux vidéo', 'Cinéma'],
            MagazineSeriesCategory::parseList('Jeux vidéo, cinema, jeux video')
        );

        $this->assertSame(
            'Jeux vidéo, Cinéma',
            MagazineSeriesCategory::serializeList(['Jeux vidéo', 'Cinéma', 'jeux video'])
        );
    }

    public function testDefaultSuggestionsIncludeInitialCategories(): void
    {
        $suggestions = MagazineSeriesCategory::suggestionLabels();
        $this->assertContains(MagazineSeriesCategory::JEUX_VIDEO, $suggestions);
        $this->assertContains(MagazineSeriesCategory::CINEMA, $suggestions);
        $this->assertContains(MagazineSeriesCategory::FIGURINES, $suggestions);
        $this->assertContains(MagazineSeriesCategory::DIVERS, $suggestions);
    }

    public function testFilterChoicesForSeriesList(): void
    {
        $choices = MagazineSeriesCategory::filterChoicesForSeriesList([
            ['categories' => 'Jeux vidéo, Cinéma'],
            ['categories' => 'Jeux vidéo'],
            ['categories' => ''],
        ]);

        $this->assertCount(2, $choices);
        $jeux = null;
        foreach ($choices as $choice) {
            if ($choice['label'] === MagazineSeriesCategory::JEUX_VIDEO) {
                $jeux = $choice;
                break;
            }
        }
        $this->assertNotNull($jeux);
        $this->assertSame(2, $jeux['count']);
        $this->assertSame('jeux vidéo', $jeux['key']);
    }

    public function testFilterChoicesHideEmptyCategories(): void
    {
        $choices = MagazineSeriesCategory::filterChoicesForSeriesList([
            ['categories' => ''],
        ]);

        $this->assertSame([], $choices);
    }
}
