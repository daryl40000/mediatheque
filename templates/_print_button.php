<?php
/**
 * Lien vers la version imprimable (même filtres que la liste courante).
 *
 * @var string $printUrl
 */
$printUrl = $printUrl ?? '';
if ($printUrl === '') {
    return;
}
?>
<a class="btn btn-secondary" href="<?= Moncine\View::escape($printUrl) ?>" target="_blank" rel="noopener">
    Version imprimable
</a>
