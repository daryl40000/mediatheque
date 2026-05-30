<?php
/**
 * Lien vers les films du même support physique.
 *
 * @var string $supportKey clé interne (dvd, bluray, bluray_4k)
 */
$supportKey = (string) ($supportKey ?? '');
$label = Moncine\SupportPhysique::label($supportKey);
if ($label === ''): ?>
    —
<?php else: ?>
    <a href="<?= Moncine\View::escape(Moncine\View::supportFilterUrl($supportKey)) ?>"
       class="support-link"
       title="Voir tous les films en <?= Moncine\View::escape($label) ?>"><?= Moncine\View::escape($label) ?></a>
<?php endif; ?>
