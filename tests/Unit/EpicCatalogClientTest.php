<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\EpicCatalogClient;
use PHPUnit\Framework\TestCase;

final class EpicCatalogClientTest extends TestCase
{
    public function testParseSearchResponseElements(): void
    {
        $json = <<<'JSON'
{
  "data": {
    "Catalog": {
      "searchStore": {
        "elements": [
          {
            "title": "Hades",
            "productSlug": "hades",
            "urlSlug": ""
          }
        ]
      }
    }
  }
}
JSON;

        $items = EpicCatalogClient::parseSearchResponse($json);

        $this->assertCount(1, $items);
        $this->assertSame('hades', $items[0]['slug']);
        $this->assertSame('Hades', $items[0]['title']);
    }

    public function testResolveSlugFallsBackToMappings(): void
    {
        $slug = EpicCatalogClient::resolveSlug([
            'productSlug' => '',
            'urlSlug' => '',
            'catalogNs' => [
                'mappings' => [
                    ['pageSlug' => 'fortnite', 'pageType' => 'productHome'],
                ],
            ],
        ]);

        $this->assertSame('fortnite', $slug);
    }

    public function testStoreUrlBuildsEpicLink(): void
    {
        $this->assertSame(
            'https://store.epicgames.com/p/hades',
            EpicCatalogClient::storeUrl('hades')
        );
    }
}
