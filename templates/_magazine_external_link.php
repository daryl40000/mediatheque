<?php
/**
 * Lien « consulter en ligne » pour une série ou un numéro magazine.
 *
 * @var string $externalUrl
 * @var string $label libellé du bouton (défaut : Consulter en ligne)
 * @var string $class classes CSS supplémentaires
 */
$externalUrl = \Moncine\MagazineExternalUrl::sanitize((string) ($externalUrl ?? ''));
if ($externalUrl === '') {
    return;
}
$label = trim((string) ($label ?? 'Consulter en ligne'));
if ($label === '') {
    $label = 'Consulter en ligne';
}
$class = trim((string) ($class ?? 'btn btn-secondary btn-sm'));
?>
<a href="<?= Moncine\View::escape($externalUrl) ?>"
   class="<?= Moncine\View::escape($class) ?>"
   target="_blank"
   rel="noopener noreferrer"
   title="Ouvre le site externe dans un nouvel onglet">
    <?= Moncine\View::escape($label) ?>
</a>
