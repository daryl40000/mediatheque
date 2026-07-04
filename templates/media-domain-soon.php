<?php
/** @var string $domain */
use Moncine\MediaDomain;

$label = MediaDomain::label($domain);
$filmUrl = '/set-media-domain.php?domain=film&redirect=' . rawurlencode('/films.php');
$implementedLabels = array_values(array_filter(
    MediaDomain::choices(),
    static fn (string $key): bool => MediaDomain::isCollectionImplemented($key),
    ARRAY_FILTER_USE_KEY
));
$availableList = implode(', ', array_map(
    static fn (string $name): string => '<strong>' . Moncine\View::escape($name) . '</strong>',
    $implementedLabels
));
?>
<section class="media-domain-soon">
    <h1><?= Moncine\View::escape($label) ?></h1>
    <p class="lead">
        Cette section arrive bientôt. Pour l’instant, les onglets <?= $availableList ?> sont disponibles.
    </p>
    <p>
        <a href="<?= Moncine\View::escape($filmUrl) ?>" class="btn btn-primary">Retour aux films</a>
    </p>
</section>
