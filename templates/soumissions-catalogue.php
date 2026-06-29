<?php
/**
 * @var list<array<string, mixed>> $pending
 * @var array<string, mixed>|null $review
 * @var string $saveError
 * @var bool $approved
 * @var bool $rejected
 * @var bool $hasTmdbKey
 * @var bool $hasIgdbKey
 * @var int $pendingCount
 * @var array<string, string> $platformChoices
 * @var list<string> $knownGenres
 */
$reviewId = $review !== null ? (int) ($review['id'] ?? 0) : 0;
$reviewIsGame = $review !== null && Moncine\MediaDomain::isGame((string) ($review['submission_domain'] ?? ''));
?>
<section class="catalog-submission-admin">
    <div class="catalog-admin-page__head">
        <div>
            <h1>Soumissions au catalogue</h1>
            <p class="lead">
                Propositions des utilisateurs en attente de validation.
                <?php if ($pendingCount > 0): ?>
                    <strong><?= (int) $pendingCount ?></strong> en attente.
                <?php endif; ?>
            </p>
        </div>
        <p><a href="/catalogue.php" class="btn btn-secondary">← Catalogue</a></p>
    </div>

    <?php if ($approved): ?>
        <p class="alert alert-success">Proposition acceptée : l’œuvre est maintenant au catalogue.</p>
    <?php endif; ?>
    <?php if ($rejected): ?>
        <p class="alert alert-success">Proposition refusée.</p>
    <?php endif; ?>
    <?php if ($saveError !== ''): ?>
        <p class="alert alert-warning"><?= Moncine\View::escape($saveError) ?></p>
    <?php endif; ?>

    <?php if ($review !== null && $reviewId > 0): ?>
        <section class="catalog-submission-review">
            <h2>Examiner la proposition #<?= $reviewId ?> — <?= Moncine\View::escape(Moncine\MediaDomain::label((string) ($review['submission_domain'] ?? ''))) ?></h2>
            <p class="hint">
                Proposée par <strong><?= Moncine\View::escape(
                    Moncine\View::userDisplayName(is_array($review['submitter'] ?? null) ? $review['submitter'] : [])
                ) ?></strong>
                le <?= Moncine\View::escape((string) ($review['created_at'] ?? '')) ?>.
                <a href="/soumissions-catalogue.php">← Liste des attentes</a>
            </p>
            <?php if (trim((string) ($review['user_note'] ?? '')) !== ''): ?>
                <blockquote class="submission-user-note">
                    <strong>Message de l’utilisateur :</strong>
                    <?= Moncine\View::escape((string) $review['user_note']) ?>
                </blockquote>
            <?php endif; ?>

            <form method="post" action="/traiter-soumission.php" class="film-edit-form catalog-submission-review-form">
                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                <input type="hidden" name="submission_id" value="<?= $reviewId ?>">

                <?php if ($reviewIsGame): ?>
                    <?php
                    $formAction = '';
                    $fieldPrefix = 'review_game';
                    $game = is_array($review['form_prefill'] ?? null) ? $review['form_prefill'] : [];
                    $userNote = '';
                    $showUserNote = false;
                    $submitLabel = '';
                    $cancelUrl = '';
                    $hiddenFields = [];
                    $isReviewMode = true;
                    $platformChoices = $platformChoices ?? Moncine\GamePlatform::choices();
                    $knownGenres = $knownGenres ?? [];
                    require MONCINE_ROOT . '/templates/_game_catalog_submission_form.php';
                    ?>
                <?php else: ?>
                <?php
                $formAction = '';
                $fieldPrefix = 'review';
                $film = is_array($review['form_prefill'] ?? null) ? $review['form_prefill'] : [];
                $userNote = '';
                $showUserNote = false;
                $submitLabel = '';
                $cancelUrl = '';
                $hiddenFields = [];
                require MONCINE_ROOT . '/templates/_catalog_submission_form.php';
                ?>
                <?php endif; ?>

                <fieldset>
                    <legend>Réponse administrateur</legend>
                    <label for="review_note">Message pour l’utilisateur (optionnel, surtout en cas de refus)</label>
                    <textarea name="review_note" id="review_note" rows="2" maxlength="500"
                              placeholder="Ex. doublon, informations insuffisantes…"></textarea>
                </fieldset>

                <p class="form-actions form-actions--stack">
                    <button type="submit" name="action" value="approve" class="btn btn-primary">
                        Accepter et publier au catalogue
                    </button>
                    <?php if (!$reviewIsGame && $hasTmdbKey): ?>
                        <button type="submit" name="action" value="approve_enrich" class="btn btn-secondary">
                            Accepter avec enrichissement TMDB
                        </button>
                    <?php elseif ($reviewIsGame && !empty($hasIgdbKey)): ?>
                        <button type="submit" name="action" value="approve_enrich" class="btn btn-secondary">
                            Accepter avec enrichissement IGDB
                        </button>
                    <?php endif; ?>
                    <button type="submit" name="action" value="reject" class="btn btn-danger">
                        Refuser
                    </button>
                    <a href="/soumissions-catalogue.php" class="btn btn-secondary">Annuler</a>
                </p>
            </form>
        </section>
    <?php elseif ($pending === []): ?>
        <p class="alert alert-info">Aucune proposition en attente.</p>
    <?php else: ?>
        <table class="data-table catalog-submission-table">
            <thead>
                <tr>
                    <th>Œuvre</th>
                    <th>Utilisateur</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending as $row): ?>
                    <?php
                    $payload = is_array($row['payload'] ?? null) ? $row['payload'] : [];
                    $rowDomain = Moncine\CatalogSubmissionPayload::domain($payload);
                    ?>
                    <tr>
                        <td>
                            <span class="hint"><?= Moncine\View::escape(Moncine\MediaDomain::label($rowDomain)) ?></span><br>
                            <strong><?= Moncine\View::escape((string) ($payload['titre'] ?? '—')) ?></strong>
                            <?php if (Moncine\MediaDomain::isGame($rowDomain) && trim((string) ($payload['studio'] ?? '')) !== ''): ?>
                                <br><span class="hint"><?= Moncine\View::escape((string) $payload['studio']) ?></span>
                            <?php elseif (trim((string) ($payload['realisateur'] ?? '')) !== ''): ?>
                                <br><span class="hint"><?= Moncine\View::escape((string) $payload['realisateur']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= Moncine\View::escape((string) ($row['submitter_label'] ?? '')) ?></td>
                        <td><?= Moncine\View::escape((string) ($row['created_at'] ?? '')) ?></td>
                        <td>
                            <a href="/soumissions-catalogue.php?id=<?= (int) ($row['id'] ?? 0) ?>" class="btn btn-primary btn-sm">
                                Examiner
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
