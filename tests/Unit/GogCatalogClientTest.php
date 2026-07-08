<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\GogCatalogClient;
use PHPUnit\Framework\TestCase;

final class GogCatalogClientTest extends TestCase
{
    public function testParseSearchResponseCatalogV1(): void
    {
        $json = <<<'JSON'
{
  "products": [
    {"id": "1207664663", "title": "The Witcher 3: Wild Hunt", "slug": "the_witcher_3_wild_hunt"}
  ]
}
JSON;

        $items = GogCatalogClient::parseSearchResponse($json);

        $this->assertCount(1, $items);
        $this->assertSame(1207664663, $items[0]['product_id']);
        $this->assertSame('https://www.gog.com/game/the_witcher_3_wild_hunt', GogCatalogClient::storeUrl($items[0]['slug']));
    }

    public function testParseSearchResponseProductsArray(): void
    {
        $json = <<<'JSON'
{
  "products": [
    {"product_id": 1207658930, "title": "The Witcher 3: Wild Hunt", "slug": "the_witcher_3_wild_hunt"}
  ]
}
JSON;

        $items = GogCatalogClient::parseSearchResponse($json);

        $this->assertCount(1, $items);
        $this->assertSame(1207658930, $items[0]['product_id']);
        $this->assertSame('the_witcher_3_wild_hunt', $items[0]['slug']);
    }

    public function testParseProductRowRejectsMissingSlug(): void
    {
        $this->assertNull(GogCatalogClient::parseProductRow([
            'title' => 'Sans slug',
            'slug' => '',
        ]));
    }

    public function testStoreUrlBuildsGogLink(): void
    {
        $this->assertSame(
            'https://www.gog.com/game/hades',
            GogCatalogClient::storeUrl('hades')
        );
    }

    public function testSlugFromStoreUrlSupportsLocalePath(): void
    {
        $this->assertSame('diablo', GogCatalogClient::slugFromStoreUrl('https://www.gog.com/fr/game/diablo'));
        $this->assertSame(
            'dungeon_keeper',
            GogCatalogClient::slugFromStoreUrl(
                'https://www.gog.com/en/game/dungeon_keeper?utm_campaign=adtraction'
            )
        );
        $this->assertSame('hades', GogCatalogClient::slugFromStoreUrl('https://www.gog.com/game/hades'));
    }

    public function testNormalizeImageUrlAddsHttpsScheme(): void
    {
        $this->assertSame(
            'https://images.gog.com/x.jpg',
            GogCatalogClient::normalizeImageUrl('//images.gog.com/x.jpg')
        );
    }
}
