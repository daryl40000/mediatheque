<?php
/**
 * Filtres de la liste BD (type, support physique).
 */

declare(strict_types=1);

namespace Moncine;

final class BdListFilter
{
    public function __construct(
        public ?string $kind = null,
        public ?string $support = null,
    ) {
    }

    public static function empty(): self
    {
        return new self();
    }

    /** @param array<string, mixed> $query */
    public static function fromQuery(array $query): self
    {
        return self::fromQueryParams($query);
    }

    /** @param array<string, mixed> $query */
    public static function fromQueryParams(array $query): self
    {
        $kindRaw = trim((string) ($query['kind'] ?? ''));
        $supportRaw = trim((string) ($query['support'] ?? ''));

        $kind = null;
        if ($kindRaw !== '' && isset(BdKind::choices()[BdKind::normalize($kindRaw)])) {
            $kind = BdKind::normalize($kindRaw);
        }

        $support = BdPhysicalSupport::normalize($supportRaw);
        if ($support === '') {
            $support = null;
        }

        return new self(kind: $kind, support: $support);
    }

    /** @param list<string> $where */
    /** @param array<string, mixed> $params */
    public function applyToSql(array &$where, array &$params): void
    {
        if ($this->kind !== null) {
            $where[] = 'ob.kind = :bd_kind';
            $params['bd_kind'] = $this->kind;
        }
        if ($this->support !== null) {
            $where[] = 'b.support_physique = :bd_support';
            $params['bd_support'] = $this->support;
        }
    }

    /** @return array<string, string> */
    public function toQueryParams(): array
    {
        $params = [];
        if ($this->kind !== null) {
            $params['kind'] = $this->kind;
        }
        if ($this->support !== null) {
            $params['support'] = $this->support;
        }

        return $params;
    }

    public function isActive(): bool
    {
        return $this->kind !== null || $this->support !== null;
    }

    public function activeLabel(): string
    {
        $parts = [];
        if ($this->kind !== null) {
            $parts[] = BdKind::label($this->kind);
        }
        if ($this->support !== null) {
            $parts[] = BdPhysicalSupport::label($this->support);
        }

        return implode(' · ', $parts);
    }
}
