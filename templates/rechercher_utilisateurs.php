<?php
/**
 * @var string $pseudoQuery
 * @var string $villeQuery
 * @var bool $searched
 * @var list<array<string, mixed>> $results
 * @var array<int, string> $relations
 * @var bool $socialAvailable
 * @var string $error
 * @var string $success
 */
?>
<section class="account-page user-search-page">
    <h1>Rechercher des utilisateurs</h1>
    <p class="lead">
        Trouvez d’autres membres par <strong>pseudo</strong> et/ou <strong>ville</strong>.
        Seuls les comptes qui acceptent d’apparaître dans la recherche sont listés.
    </p>

    <?php if ($success !== ''): ?>
        <p class="alert alert-success"><?= Moncine\View::escape($success) ?></p>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <p class="alert alert-warning"><?= Moncine\View::escape($error) ?></p>
    <?php endif; ?>

    <form method="get" action="/rechercher-utilisateurs.php" class="import-form auth-form user-search-form">
        <label for="search_pseudo">Pseudo</label>
        <input type="search" name="pseudo" id="search_pseudo" autocomplete="off"
               placeholder="Ex. CineFan"
               value="<?= Moncine\View::escape($pseudoQuery) ?>">

        <label for="search_ville">Ville</label>
        <input type="search" name="ville" id="search_ville" autocomplete="off"
               placeholder="Ex. Lyon"
               value="<?= Moncine\View::escape($villeQuery) ?>">

        <button type="submit" class="btn btn-primary">Rechercher</button>
    </form>

    <?php if ($searched): ?>
        <?php if ($results === []): ?>
            <p class="hint user-search-page__empty">Aucun utilisateur trouvé pour ces critères.</p>
        <?php else: ?>
            <ul class="user-search-results">
                <?php foreach ($results as $row): ?>
                    <?php
                    $uid = (int) ($row['id'] ?? 0);
                    $display = Moncine\UserProfile::displayName($row);
                    $pseudo = trim((string) ($row['pseudo'] ?? ''));
                    $ville = trim((string) ($row['ville'] ?? ''));
                    $rel = $relations[$uid] ?? 'none';
                    ?>
                    <li class="user-search-results__item">
                        <span class="user-search-results__name"><?= Moncine\View::escape($display) ?></span>
                        <?php if ($pseudo !== ''): ?>
                            <span class="user-search-results__meta">@<?= Moncine\View::escape($pseudo) ?></span>
                        <?php endif; ?>
                        <?php if ($ville !== ''): ?>
                            <span class="user-search-results__meta"><?= Moncine\View::escape($ville) ?></span>
                        <?php endif; ?>

                        <?php if ($socialAvailable): ?>
                            <?php
                            $returnSearch = '/rechercher-utilisateurs.php';
                            if ($pseudoQuery !== '' || $villeQuery !== '') {
                                $returnSearch .= '?pseudo=' . rawurlencode($pseudoQuery)
                                    . '&ville=' . rawurlencode($villeQuery);
                            }
                            ?>
                            <?php if ($rel === 'none'): ?>
                                <form method="post" action="/demander-ami.php" class="inline-form">
                                    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                                    <input type="hidden" name="addressee_id" value="<?= $uid ?>">
                                    <input type="hidden" name="return_pseudo" value="<?= Moncine\View::escape($pseudoQuery) ?>">
                                    <input type="hidden" name="return_ville" value="<?= Moncine\View::escape($villeQuery) ?>">
                                    <button type="submit" class="btn btn-primary btn-sm">Demander en ami</button>
                                </form>
                                <form method="post" action="/bloquer-utilisateur.php" class="inline-form">
                                    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                                    <input type="hidden" name="blocked_user_id" value="<?= $uid ?>">
                                    <input type="hidden" name="return_to" value="<?= Moncine\View::escape($returnSearch) ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm">Bloquer</button>
                                </form>
                            <?php elseif ($rel === 'friends'): ?>
                                <span class="user-search-results__meta">Ami</span>
                                <form method="post" action="/bloquer-utilisateur.php" class="inline-form">
                                    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                                    <input type="hidden" name="blocked_user_id" value="<?= $uid ?>">
                                    <input type="hidden" name="return_to" value="<?= Moncine\View::escape($returnSearch) ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm">Bloquer</button>
                                </form>
                            <?php elseif ($rel === 'pending_sent'): ?>
                                <span class="user-search-results__meta">Demande envoyée</span>
                                <form method="post" action="/bloquer-utilisateur.php" class="inline-form">
                                    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                                    <input type="hidden" name="blocked_user_id" value="<?= $uid ?>">
                                    <input type="hidden" name="return_to" value="<?= Moncine\View::escape($returnSearch) ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm">Bloquer</button>
                                </form>
                            <?php elseif ($rel === 'pending_received'): ?>
                                <a href="/mes-amis.php" class="btn btn-secondary btn-sm">Répondre à la demande</a>
                                <form method="post" action="/bloquer-utilisateur.php" class="inline-form">
                                    <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                                    <input type="hidden" name="blocked_user_id" value="<?= $uid ?>">
                                    <input type="hidden" name="return_to" value="<?= Moncine\View::escape($returnSearch) ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm">Bloquer</button>
                                </form>
                            <?php elseif ($rel === 'blocked_by_me'): ?>
                                <span class="user-search-results__meta">Bloqué</span>
                                <a href="/mes-amis.php" class="btn btn-secondary btn-sm">Gérer les blocages</a>
                            <?php elseif ($rel === 'blocked_me'): ?>
                                <span class="user-search-results__meta">Indisponible</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="hint">Résultats limités à 50.</p>
        <?php endif; ?>
    <?php else: ?>
        <p class="hint">Saisissez au moins un pseudo ou une ville, puis lancez la recherche.</p>
    <?php endif; ?>

    <p class="collection-page__footer-links">
        <a href="/mes-amis.php">Mes amis</a>
        ·
        <a href="/parametres.php">← Mon compte</a>
    </p>
</section>
