<?php
/**
 * Légende d’une vignette livre (survol).
 *
 * @var array<string, mixed> $book
 */
$displayTitle = (string) ($book['display_titre'] ?? $book['titre'] ?? '');
$sousTitre = trim((string) ($book['sous_titre'] ?? ''));
$annee = (int) ($book['annee'] ?? 0);
$auteur = trim((string) ($book['auteur'] ?? ''));
$sagaName = trim((string) ($book['saga'] ?? ''));
$sagaOrdre = (int) ($book['saga_ordre'] ?? 0);
?>
<div class="collection-grid__caption">
    <h3 class="collection-grid__title">
        <?= Moncine\View::escape($displayTitle) ?>
    </h3>
    <?php if ($sousTitre !== ''): ?>
        <p class="collection-grid__meta hint"><?= Moncine\View::escape($sousTitre) ?></p>
    <?php endif; ?>
    <p class="collection-grid__meta">
        <?php if ($auteur !== ''): ?>
            <span><?= Moncine\View::escape($auteur) ?></span>
        <?php endif; ?>
        <?php if ($annee > 0): ?>
            <span class="collection-grid__year"><?= $annee ?></span>
        <?php endif; ?>
    </p>
    <?php if ($sagaName !== ''): ?>
        <p class="collection-grid__meta hint">
            <?php if ($sagaOrdre > 0): ?><?= $sagaOrdre ?>. <?php endif; ?>
            <?= Moncine\View::escape($sagaName) ?>
        </p>
    <?php endif; ?>
</div>
