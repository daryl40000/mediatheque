<?php
/** @var string $domain */
use Moncine\MediaDomain;

$label = MediaDomain::label($domain);
$filmUrl = '/set-media-domain.php?domain=film&redirect=' . rawurlencode('/films.php');
?>
<section class="media-domain-soon">
    <h1><?= Moncine\View::escape($label) ?></h1>
    <p class="lead">
        Cette section arrive bientôt. Pour l’instant, seule la gestion des <strong>films</strong> est disponible.
    </p>
    <p>
        <a href="<?= Moncine\View::escape($filmUrl) ?>" class="btn btn-primary">Retour aux films</a>
    </p>
</section>
