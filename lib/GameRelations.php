<?php
/**
 * Extensions et remakes jeux : validation et fragments SQL.
 */

declare(strict_types=1);

namespace Moncine;

final class GameRelations
{
    /**
     * Valide extension / remake (mutuellement exclusifs, jeu lié obligatoire).
     *
     * @param array<string, mixed> $data
     */
    public static function validateFlags(array $data, int $selfOeuvreId = 0): ?string
    {
        $isExtension = !empty($data['is_extension']);
        $baseGameOeuvreId = max(0, (int) ($data['base_game_oeuvre_id'] ?? 0));
        $isRemake = !empty($data['is_remake']);
        $originalGameOeuvreId = max(0, (int) ($data['original_game_oeuvre_id'] ?? 0));

        if ($isExtension && $isRemake) {
            return 'Une fiche ne peut pas être à la fois une extension et un remake.';
        }
        if ($isExtension && $baseGameOeuvreId <= 0) {
            return 'Pour une extension, choisissez un jeu de base dans le catalogue.';
        }
        if ($isRemake && $originalGameOeuvreId <= 0) {
            return 'Pour un remake, choisissez le jeu d\'origine dans le catalogue.';
        }
        if ($selfOeuvreId > 0 && $isExtension && $baseGameOeuvreId === $selfOeuvreId) {
            return 'Une extension ne peut pas pointer vers elle-même.';
        }
        if ($selfOeuvreId > 0 && $isRemake && $originalGameOeuvreId === $selfOeuvreId) {
            return 'Un remake ne peut pas pointer vers lui-même.';
        }

        return null;
    }

    public static function selectColumns(): string
    {
        $cols = '';
        if (GameSchema::hasExtensionColumns()) {
            $cols .= ', oj.is_extension, oj.base_game_oeuvre_id';
        }
        if (GameSchema::hasRemakeColumns()) {
            $cols .= ', oj.is_remake, oj.original_game_oeuvre_id';
        }

        return $cols;
    }

    public static function insertColumns(): string
    {
        $cols = '';
        if (GameSchema::hasExtensionColumns()) {
            $cols .= ', is_extension, base_game_oeuvre_id';
        }
        if (GameSchema::hasRemakeColumns()) {
            $cols .= ', is_remake, original_game_oeuvre_id';
        }

        return $cols;
    }

    public static function insertPlaceholders(): string
    {
        $ph = '';
        if (GameSchema::hasExtensionColumns()) {
            $ph .= ', ?, ?';
        }
        if (GameSchema::hasRemakeColumns()) {
            $ph .= ', ?, ?';
        }

        return $ph;
    }

    public static function updateSet(): string
    {
        $set = '';
        if (GameSchema::hasExtensionColumns()) {
            $set .= ', is_extension = ?, base_game_oeuvre_id = ?';
        }
        if (GameSchema::hasRemakeColumns()) {
            $set .= ', is_remake = ?, original_game_oeuvre_id = ?';
        }

        return $set;
    }

    /**
     * @param array<string, mixed> $data
     * @return list<mixed>
     */
    public static function writeParams(array $data): array
    {
        $params = [];
        if (GameSchema::hasExtensionColumns()) {
            $isExtension = !empty($data['is_extension']);
            $baseId = max(0, (int) ($data['base_game_oeuvre_id'] ?? 0));
            $params[] = $isExtension ? 1 : 0;
            $params[] = $isExtension && $baseId > 0 ? $baseId : null;
        }
        if (GameSchema::hasRemakeColumns()) {
            $isRemake = !empty($data['is_remake']);
            $originalId = max(0, (int) ($data['original_game_oeuvre_id'] ?? 0));
            $params[] = $isRemake ? 1 : 0;
            $params[] = $isRemake && $originalId > 0 ? $originalId : null;
        }

        return $params;
    }
}
