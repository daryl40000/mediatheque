<?php
/**
 * Lien vers la recherche par personne (réalisateur / acteur).
 *
 * @var string $name Nom affiché et utilisé pour la recherche
 */
$name = trim($name ?? '');
if ($name === ''): ?>
    —
<?php else: ?>
    <a href="<?= Moncine\View::personSearchUrl($name) ?>" class="personne-link"
       title="Voir les films avec <?= Moncine\View::escape($name) ?>"><?= Moncine\View::escape($name) ?></a>
<?php endif; ?>
