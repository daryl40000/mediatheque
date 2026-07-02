<?php
/**
 * Lecture fiable des cases à cocher HTML dans un formulaire POST.
 */

declare(strict_types=1);

namespace Moncine;

final class FormCheckbox
{
    /**
     * Vrai seulement si la case a été cochée (valeur « 1 »).
     * Une case décochée est absente du POST — pas besoin de champ caché.
     */
    public static function isChecked(array $post, string $key): bool
    {
        if (!array_key_exists($key, $post)) {
            return false;
        }

        $value = $post[$key];
        if (is_array($value)) {
            $value = end($value);
        }

        return (string) $value === '1';
    }
}
