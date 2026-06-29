<?php
/**
 * @var list<array<string, mixed>> $submissions
 * @var bool $submitted
 */
?>
<section class="catalog-submission-page">
    <h1>Mes propositions au catalogue</h1>
    <p class="lead">Suivi des œuvres que vous avez proposées à l’administrateur.</p>

    <?php if ($submitted): ?>
        <p class="alert alert-success">
            Votre proposition a été envoyée. Vous recevrez une
            <a href="/notifications.php">notification</a> (et un e-mail si votre compte en a un) lorsqu’elle sera traitée.
        </p>
    <?php endif; ?>

    <p>
        <a href="/proposer-oeuvre.php" class="btn btn-primary">Proposer un film</a>
        <?php if (Moncine\GameRepository::isAvailable() && Moncine\CatalogSubmission::canSubmit()): ?>
            <a href="/proposer-jeu.php" class="btn btn-secondary">Proposer un jeu</a>
        <?php endif; ?>
    </p>

    <?php if ($submissions === []): ?>
        <p class="hint">Aucune proposition pour le moment.</p>
    <?php else: ?>
        <table class="data-table catalog-submission-table">
            <thead>
                <tr>
                    <th>Œuvre</th>
                    <th>Statut</th>
                    <th>Envoyée le</th>
                    <th>Réponse</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($submissions as $row): ?>
                    <?php
                    $payload = is_array($row['payload'] ?? null) ? $row['payload'] : [];
                    $status = (string) ($row['status'] ?? '');
                    $oeuvreId = (int) ($row['resulting_oeuvre_id'] ?? 0);
                    $rowDomain = Moncine\CatalogSubmissionPayload::domain($payload);
                    $isGameRow = Moncine\MediaDomain::isGame($rowDomain);
                    ?>
                    <tr>
                        <td>
                            <span class="hint"><?= Moncine\View::escape(Moncine\MediaDomain::label($rowDomain)) ?></span><br>
                            <strong><?= Moncine\View::escape((string) ($payload['titre'] ?? '—')) ?></strong>
                            <?php if ($isGameRow && trim((string) ($payload['studio'] ?? '')) !== ''): ?>
                                <br><span class="hint"><?= Moncine\View::escape((string) $payload['studio']) ?></span>
                            <?php elseif (trim((string) ($payload['realisateur'] ?? '')) !== ''): ?>
                                <br><span class="hint"><?= Moncine\View::escape((string) $payload['realisateur']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="submission-badge submission-badge--<?= Moncine\View::escape($status) ?>">
                                <?= Moncine\View::escape(Moncine\CatalogSubmission::statusLabel($status)) ?>
                            </span>
                        </td>
                        <td><?= Moncine\View::escape((string) ($row['created_at'] ?? '')) ?></td>
                        <td>
                            <?php if ($status === Moncine\CatalogSubmissionRepository::STATUS_APPROVED && $oeuvreId > 0): ?>
                                <?php if ($isGameRow): ?>
                                    <a href="<?= Moncine\View::escape(Moncine\View::addGameChoiceUrl($oeuvreId)) ?>">Ajouter à Mes jeux ou envies</a>
                                    <?php if (Moncine\CatalogAdmin::canAccess()): ?>
                                        · <a href="/oeuvre-jeu.php?id=<?= $oeuvreId ?>">Fiche catalogue</a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="<?= Moncine\View::escape(Moncine\View::addFilmChoiceUrl($oeuvreId)) ?>">Ajouter à mes films ou envies</a>
                                    <?php if (Moncine\CatalogAdmin::canAccess()): ?>
                                        · <a href="/oeuvre.php?id=<?= $oeuvreId ?>">Fiche catalogue</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php elseif ($status === Moncine\CatalogSubmissionRepository::STATUS_REJECTED): ?>
                                <?php if (trim((string) ($row['review_note'] ?? '')) !== ''): ?>
                                    <?= Moncine\View::escape((string) $row['review_note']) ?>
                                <?php else: ?>
                                    <span class="hint">Proposition refusée.</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="hint">En cours d’examen…</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
