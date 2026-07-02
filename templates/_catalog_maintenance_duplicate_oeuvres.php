<?php
/**
 * Liste des fiches d’un groupe de doublons catalogue (comparaison avant fusion).
 *
 * @var list<array<string, mixed>> $oeuvres
 */
$oeuvres = is_array($oeuvres ?? null) ? $oeuvres : [];
?>
<?php if ($oeuvres === []): ?>
    <p class="hint">Aucun détail de fiche disponible.</p>
<?php else: ?>
    <ul class="catalog-maintenance-duplicate__list">
        <?php foreach ($oeuvres as $oeuvre): ?>
            <?php
            $oeuvreId = (int) ($oeuvre['id'] ?? 0);
            $ficheUrl = Moncine\View::catalogOeuvreUrl($oeuvre);
            $domainLabel = Moncine\MediaDomain::label((string) ($oeuvre['media_domain'] ?? Moncine\MediaDomain::FILM));
            $synopsis = trim((string) ($oeuvre['synopsis'] ?? ''));
            $synopsisPreview = $synopsis !== ''
                ? (mb_strlen($synopsis) > 120 ? mb_substr($synopsis, 0, 117) . '…' : $synopsis)
                : '';
            ?>
            <li class="catalog-maintenance-duplicate__item">
                <strong>#<?= $oeuvreId ?></strong>
                — <?= Moncine\View::escape(trim((string) ($oeuvre['titre'] ?? '')) ?: 'Sans titre') ?>
                <?php if (trim((string) ($oeuvre['realisateur'] ?? '')) !== ''): ?>
                    <span class="hint">· <?= Moncine\View::escape((string) $oeuvre['realisateur']) ?></span>
                <?php endif; ?>
                <?php if ((int) ($oeuvre['annee'] ?? 0) > 0): ?>
                    <span class="hint">· <?= (int) $oeuvre['annee'] ?></span>
                <?php endif; ?>
                <span class="hint">
                    · <?= Moncine\View::escape($domainLabel) ?>
                    · <?= (int) ($oeuvre['library_count'] ?? 0) ?> bibliothèque<?= (int) ($oeuvre['library_count'] ?? 0) > 1 ? 's' : '' ?>
                    <?php if ((int) ($oeuvre['tmdb_id'] ?? 0) > 0): ?>
                        · TMDB <?= (int) $oeuvre['tmdb_id'] ?>
                    <?php endif; ?>
                    <?php if (trim((string) ($oeuvre['poster_url'] ?? '')) !== ''): ?>
                        · affiche
                    <?php endif; ?>
                </span>
                <?php if ($synopsisPreview !== ''): ?>
                    <br><span class="hint catalog-maintenance-duplicate__synopsis"><?= Moncine\View::escape($synopsisPreview) ?></span>
                <?php endif; ?>
                <?php if ($oeuvreId > 0): ?>
                    · <a href="<?= Moncine\View::escape($ficheUrl) ?>" target="_blank" rel="noopener noreferrer">Ouvrir la fiche</a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
