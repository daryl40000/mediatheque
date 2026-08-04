<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\MagazineRatingPeriod;
use Moncine\MagazineRatingScale;
use PHPUnit\Framework\TestCase;

final class MagazineRatingPeriodTest extends TestCase
{
    public function testResolvePrefersMatchingPeriodThenDefault(): void
    {
        $periods = [
            [
                'from_numero_ordre' => 1.0,
                'to_numero_ordre' => 92.0,
                'rating_scale' => '5',
            ],
            [
                'from_numero_ordre' => 93.0,
                'to_numero_ordre' => 110.0,
                'rating_scale' => '100',
            ],
            [
                'from_numero_ordre' => 111.0,
                'to_numero_ordre' => null,
                'rating_scale' => '20',
            ],
        ];

        $this->assertSame('5', MagazineRatingPeriod::resolve('10', $periods, 1.0));
        $this->assertSame('5', MagazineRatingPeriod::resolve('10', $periods, 50.0));
        $this->assertSame('5', MagazineRatingPeriod::resolve('10', $periods, 92.0));
        $this->assertSame('100', MagazineRatingPeriod::resolve('10', $periods, 93.0));
        $this->assertSame('100', MagazineRatingPeriod::resolve('10', $periods, 110.0));
        $this->assertSame('20', MagazineRatingPeriod::resolve('10', $periods, 111.0));
        $this->assertSame('20', MagazineRatingPeriod::resolve('10', $periods, 999.0));
        // Hors plage (avant la première) → échelle par défaut.
        $this->assertSame('10', MagazineRatingPeriod::resolve('10', $periods, 0.0));
        $this->assertNull(MagazineRatingPeriod::resolve(null, $periods, 0.0));
    }

    public function testResolveWithoutPeriodsUsesDefault(): void
    {
        $this->assertSame('20', MagazineRatingPeriod::resolve('20', [], 42.0));
        $this->assertNull(MagazineRatingPeriod::resolve(null, [], 42.0));
    }

    public function testParseFromPostAndOverlap(): void
    {
        $ok = MagazineRatingPeriod::parseFromPost([
            'rating_period_from' => ['1', '93'],
            'rating_period_to' => ['92', ''],
            'rating_period_scale' => ['5', '100'],
        ]);
        $this->assertIsArray($ok);
        $this->assertCount(2, $ok);
        $this->assertSame(1.0, $ok[0]['from_numero_ordre']);
        $this->assertSame(92.0, $ok[0]['to_numero_ordre']);
        $this->assertSame('5', $ok[0]['rating_scale']);
        $this->assertNull($ok[1]['to_numero_ordre']);
        $this->assertSame('100', $ok[1]['rating_scale']);

        $overlap = MagazineRatingPeriod::parseFromPost([
            'rating_period_from' => ['1', '50'],
            'rating_period_to' => ['92', '60'],
            'rating_period_scale' => ['5', '10'],
        ]);
        $this->assertIsString($overlap);

        $empty = MagazineRatingPeriod::parseFromPost([
            'rating_period_from' => [''],
            'rating_period_to' => [''],
            'rating_period_scale' => [''],
        ]);
        $this->assertSame([], $empty);
    }

    public function testFormatLabel(): void
    {
        $this->assertSame(
            'n°1 → 92 : Sur 5',
            MagazineRatingPeriod::formatLabel([
                'from_numero_ordre' => 1,
                'to_numero_ordre' => 92,
                'rating_scale' => '5',
            ])
        );
        $this->assertSame(
            'n°111 → … : Sur 20',
            MagazineRatingPeriod::formatLabel([
                'from_numero_ordre' => 111,
                'to_numero_ordre' => null,
                'rating_scale' => '20',
            ])
        );
        $this->assertSame('Sur 5', MagazineRatingScale::label('5'));
    }
}
