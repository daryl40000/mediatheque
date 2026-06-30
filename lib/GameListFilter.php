<?php
/**
 * Filtres de la liste « Mes jeux » (plateforme, genre, décennie, support, magasin démat, extensions).
 */

declare(strict_types=1);

namespace Moncine;

final class GameListFilter
{
    public const EXTENSIONS_ALL = 'all';
    public const EXTENSIONS_ONLY = 'only';
    public const EXTENSIONS_EXCLUDE = 'exclude';

    public const SUPPORT_DIGITAL = 'digital';
    public const SUPPORT_PHYSICAL = 'physical';

    private function __construct(
        public readonly string $platform = '',
        public readonly string $platformKind = '',
        public readonly string $store = '',
        public readonly string $genre = '',
        public readonly int $decade = 0,
        public readonly string $support = '',
        public readonly string $extensions = self::EXTENSIONS_ALL,
    ) {
    }

    public static function empty(): self
    {
        return new self();
    }

    /** @return array<string, string> */
    public static function platformKindChoices(): array
    {
        return [
            'pc' => 'PC',
            'console' => 'Consoles',
            'mobile' => 'Mobile',
            'multi' => 'Multi-plateformes',
        ];
    }

    /** @return array<string, string> */
    public static function supportChoices(): array
    {
        return [
            self::SUPPORT_PHYSICAL => 'Physique uniquement',
            self::SUPPORT_DIGITAL => 'Dématérialisé uniquement',
        ];
    }

    /** @param array<string, mixed> $query */
    public static function fromQuery(array $query): self
    {
        $platformRaw = trim((string) ($query['platform'] ?? ''));
        $platform = $platformRaw === '_none' ? '_none' : GamePlatform::normalize($platformRaw);

        $platformKind = self::normalizePlatformKind((string) ($query['platform_kind'] ?? ''));
        $store = GameDigitalStore::normalizeFilterKey((string) ($query['store'] ?? ''));

        $genre = mb_strtolower(trim((string) ($query['genre'] ?? '')));

        $decade = (int) ($query['decade'] ?? 0);
        if ($decade < 1970 || $decade % 10 !== 0) {
            $decade = 0;
        }

        $support = self::normalizeSupport((string) ($query['support'] ?? ''));
        $extensions = self::normalizeExtensions((string) ($query['extensions'] ?? ''));

        return new self($platform, $platformKind, $store, $genre, $decade, $support, $extensions);
    }

    public static function forPlatform(string $platformKey): self
    {
        if ($platformKey === '_none') {
            return new self(platform: '_none', extensions: self::EXTENSIONS_EXCLUDE);
        }

        $platform = GamePlatform::normalize($platformKey);

        return new self(
            platform: $platform,
            extensions: self::EXTENSIONS_EXCLUDE,
        );
    }

    public static function forGenre(string $genreKey): self
    {
        return new self(
            genre: mb_strtolower(trim($genreKey)),
            extensions: self::EXTENSIONS_EXCLUDE,
        );
    }

    public static function forDecade(int $decadeStart): self
    {
        return new self(
            decade: $decadeStart,
            extensions: self::EXTENSIONS_EXCLUDE,
        );
    }

    public static function forSupport(string $support): self
    {
        return new self(
            support: self::normalizeSupport($support),
            extensions: self::EXTENSIONS_EXCLUDE,
        );
    }

    public static function forDigitalStore(string $storeKey): self
    {
        return new self(
            store: GameDigitalStore::normalizeFilterKey($storeKey),
            extensions: self::EXTENSIONS_EXCLUDE,
        );
    }

    public static function forExtensionsOnly(): self
    {
        return new self(extensions: self::EXTENSIONS_ONLY);
    }

    public static function excludingExtensions(): self
    {
        return new self(extensions: self::EXTENSIONS_EXCLUDE);
    }

    public function isActive(): bool
    {
        return $this->platform !== ''
            || $this->platformKind !== ''
            || $this->store !== ''
            || $this->genre !== ''
            || $this->decade > 0
            || $this->support !== ''
            || $this->extensions !== self::EXTENSIONS_ALL;
    }

    /** Libellé court pour la bannière de filtre active. */
    public function activeLabel(): string
    {
        $parts = [];

        if ($this->extensions === self::EXTENSIONS_ONLY) {
            $parts[] = 'Extensions uniquement';
        } elseif ($this->extensions === self::EXTENSIONS_EXCLUDE) {
            $parts[] = 'Jeux de base uniquement';
        }

        if ($this->platformKind !== '') {
            $parts[] = self::platformKindChoices()[$this->platformKind] ?? $this->platformKind;
        }

        if ($this->platform === '_none') {
            $parts[] = 'Plateforme non renseignée';
        } elseif ($this->platform !== '') {
            $label = GamePlatform::label($this->platform);
            $parts[] = $label !== '' ? $label : $this->platform;
        }

        if ($this->store !== '') {
            $parts[] = GameDigitalStore::label($this->store);
        }

        if ($this->genre !== '') {
            $parts[] = 'Genre « ' . $this->genre . ' »';
        }

        if ($this->decade > 0) {
            $parts[] = 'Sortie ' . $this->decade . '–' . ($this->decade + 9);
        }

        if ($this->support === self::SUPPORT_DIGITAL) {
            $parts[] = 'Versions dématérialisées';
        } elseif ($this->support === self::SUPPORT_PHYSICAL) {
            $parts[] = 'Versions physiques';
        }

        return $parts !== [] ? implode(' · ', $parts) : '';
    }

    /** @return array<string, string|int> */
    public function toQueryParams(): array
    {
        $params = [];

        if ($this->platformKind !== '') {
            $params['platform_kind'] = $this->platformKind;
        }

        if ($this->platform === '_none') {
            $params['platform'] = '_none';
        } elseif ($this->platform !== '') {
            $params['platform'] = $this->platform;
        }

        if ($this->store !== '') {
            $params['store'] = $this->store;
        }

        if ($this->genre !== '') {
            $params['genre'] = $this->genre;
        }

        if ($this->decade > 0) {
            $params['decade'] = (string) $this->decade;
        }

        if ($this->support !== '') {
            $params['support'] = $this->support;
        }

        if ($this->extensions !== self::EXTENSIONS_ALL) {
            $params['extensions'] = $this->extensions;
        }

        return $params;
    }

    /**
     * Ajoute les conditions SQL liées au filtre.
     *
     * @param list<string> $where
     * @param array<string, mixed> $params
     */
    public function applyToSql(array &$where, array &$params): void
    {
        if ($this->platformKind !== '') {
            $this->appendPlatformKindSql($where, $params);
        }

        if ($this->platform === '_none') {
            $where[] = '(TRIM(COALESCE(oj.platform, \'\')) = \'\' AND TRIM(COALESCE(oj.platforms, \'\')) = \'\''
                . ' AND TRIM(COALESCE(b.owned_platforms, \'\')) = \'\')';
        } elseif ($this->platform !== '') {
            $where[] = '('
                . GamePlatformList::sqlCsvContains('b.owned_platforms', ':filter_platform')
                . ' OR ' . GamePlatformList::sqlCsvContains('oj.platforms', ':filter_platform')
                . ' OR oj.platform = :filter_platform'
                . ')';
            $params['filter_platform'] = $this->platform;
        }

        if ($this->store !== '' && GameRepository::hasEditionColumns()) {
            $where[] = GameDigitalStore::sqlStoredJsonContains('oj.digital_stores', ':filter_digital_store');
            $params['filter_digital_store'] = $this->store;
        }

        if ($this->genre !== '') {
            $where[] = GameGenre::sqlTaggedCsvLower('oj.genre') . ' LIKE :filter_genre';
            $params['filter_genre'] = '%,' . $this->genre . ',%';
        }

        if ($this->decade > 0) {
            $where[] = 'o.annee >= :filter_decade_start AND o.annee < :filter_decade_end';
            $params['filter_decade_start'] = $this->decade;
            $params['filter_decade_end'] = $this->decade + 10;
        }

        if ($this->support === self::SUPPORT_DIGITAL) {
            $where[] = 'oj.is_digital = 1';
        } elseif ($this->support === self::SUPPORT_PHYSICAL) {
            $where[] = 'oj.is_digital = 0';
        }

        if ($this->extensions === self::EXTENSIONS_ONLY) {
            if (GameRepository::hasExtensionColumns()) {
                $where[] = 'oj.is_extension = 1';
            } else {
                $where[] = '1 = 0';
            }
        } elseif ($this->extensions === self::EXTENSIONS_EXCLUDE && GameRepository::hasExtensionColumns()) {
            $where[] = '(oj.is_extension = 0 OR oj.is_extension IS NULL)';
        }
    }

    /**
     * @param list<string> $where
     * @param array<string, mixed> $params
     */
    private function appendPlatformKindSql(array &$where, array &$params): void
    {
        if (GamePlatformRegistry::isAvailable()) {
            $where[] = 'EXISTS (
                SELECT 1 FROM game_platform gp
                WHERE gp.kind = :filter_platform_kind
                  AND gp.active = 1
                  AND (
                    ' . GamePlatformList::sqlCsvContains('b.owned_platforms', 'gp.platform_key') . '
                    OR ' . GamePlatformList::sqlCsvContains('oj.platforms', 'gp.platform_key') . '
                    OR oj.platform = gp.platform_key
                  )
            )';
            $params['filter_platform_kind'] = $this->platformKind;

            return;
        }

        $orParts = [];
        $index = 0;
        foreach (array_keys(GamePlatform::choices()) as $platformKey) {
            if (GamePlatformRegistry::kind($platformKey) !== $this->platformKind) {
                continue;
            }

            $param = 'filter_kind_platform_' . $index;
            $index++;
            $orParts[] = '('
                . GamePlatformList::sqlCsvContains('b.owned_platforms', ':' . $param)
                . ' OR ' . GamePlatformList::sqlCsvContains('oj.platforms', ':' . $param)
                . ' OR oj.platform = :' . $param
                . ')';
            $params[$param] = $platformKey;
        }

        if ($orParts === []) {
            $where[] = '1 = 0';

            return;
        }

        $where[] = '(' . implode(' OR ', $orParts) . ')';
    }

    private static function normalizePlatformKind(string $value): string
    {
        $value = strtolower(trim($value));

        return isset(self::platformKindChoices()[$value]) ? $value : '';
    }

    private static function normalizeSupport(string $value): string
    {
        $value = strtolower(trim($value));

        return match ($value) {
            self::SUPPORT_DIGITAL, self::SUPPORT_PHYSICAL => $value,
            default => '',
        };
    }

    private static function normalizeExtensions(string $value): string
    {
        $value = strtolower(trim($value));

        return match ($value) {
            self::EXTENSIONS_ONLY, self::EXTENSIONS_EXCLUDE => $value,
            default => self::EXTENSIONS_ALL,
        };
    }
}
