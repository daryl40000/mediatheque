<?php
/**
 * Filtres de la liste « Mes jeux » (plateforme, genre, décennie, support, extensions).
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

    /** @param array<string, mixed> $query */
    public static function fromQuery(array $query): self
    {
        $platformRaw = trim((string) ($query['platform'] ?? ''));
        $platform = $platformRaw === '_none' ? '_none' : GamePlatform::normalize($platformRaw);

        $genre = mb_strtolower(trim((string) ($query['genre'] ?? '')));

        $decade = (int) ($query['decade'] ?? 0);
        if ($decade < 1970 || $decade % 10 !== 0) {
            $decade = 0;
        }

        $support = self::normalizeSupport((string) ($query['support'] ?? ''));
        $extensions = self::normalizeExtensions((string) ($query['extensions'] ?? ''));

        return new self($platform, $genre, $decade, $support, $extensions);
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

        if ($this->platform === '_none') {
            $parts[] = 'Plateforme non renseignée';
        } elseif ($this->platform !== '') {
            $label = GamePlatform::label($this->platform);
            $parts[] = $label !== '' ? $label : $this->platform;
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

        if ($this->platform === '_none') {
            $params['platform'] = '_none';
        } elseif ($this->platform !== '') {
            $params['platform'] = $this->platform;
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
        if ($this->platform === '_none') {
            $where[] = '(oj.platform IS NULL OR TRIM(oj.platform) = \'\')';
        } elseif ($this->platform !== '') {
            $where[] = 'oj.platform = :filter_platform';
            $params['filter_platform'] = $this->platform;
        }

        if ($this->genre !== '') {
            $where[] = 'LOWER(\',\' || REPLACE(oj.genre, \';\', \',\') || \',\') LIKE :filter_genre';
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
