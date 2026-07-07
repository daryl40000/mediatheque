<?php
/**
 * Liens magasins à valider manuellement.
 *
 * @var list<array<string, mixed>> $storeLinksPendingReview
 */
$storeLinksPendingReview = $storeLinksPendingReview ?? [];
?>
<?php if (!Moncine\OeuvreStoreLinkRepository::isAvailable()): ?>
    <p class="hint">Migration <code>063_oeuvre_store_links.sql</code> non appliquée.</p>
<?php elseif ($storeLinksPendingReview === []): ?>
    <p class="hint">Aucun lien magasin en attente de validation.</p>
<?php else: ?>
    <div class="table-scroll">
        <table class="films-table catalog-admin-table">
            <thead>
                <tr>
                    <th>Œuvre</th>
                    <th>Magasin</th>
                    <th>Titre proposé</th>
                    <th>Confiance</th>
                    <th>Lien</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($storeLinksPendingReview as $row): ?>
                    <?php
                    $oeuvreId = (int) ($row['oeuvre_id'] ?? 0);
                    $store = (string) ($row['store'] ?? '');
                    $confidence = (float) ($row['match_confidence'] ?? 0);
                    $url = trim((string) ($row['store_url'] ?? ''));
                    ?>
                    <tr>
                        <td>
                            <a href="/oeuvre-jeu.php?id=<?= $oeuvreId ?>"><?= Moncine\View::escape((string) ($row['oeuvre_titre'] ?? '')) ?></a>
                        </td>
                        <td><?= Moncine\View::escape(Moncine\GameDigitalStore::label($store)) ?></td>
                        <td><?= Moncine\View::escape((string) ($row['store_title'] ?? '')) ?></td>
                        <td><?= number_format($confidence * 100, 0) ?> %</td>
                        <td>
                            <?php if ($url !== ''): ?>
                                <a href="<?= Moncine\View::escape($url) ?>" target="_blank" rel="noopener">Ouvrir</a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="catalog-admin-table__actions">
                            <form method="post" action="/valider-lien-magasin.php" class="inline-form">
                                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                                <input type="hidden" name="action" value="verify_store_link">
                                <input type="hidden" name="oeuvre_id" value="<?= $oeuvreId ?>">
                                <input type="hidden" name="store" value="<?= Moncine\View::escape($store) ?>">
                                <button type="submit" class="btn btn-secondary btn-sm">Valider</button>
                            </form>
                            <form method="post" action="/valider-lien-magasin.php" class="inline-form"
                                  onsubmit="return confirm('Ignorer ce lien proposé ?');">
                                <?php require MONCINE_ROOT . '/templates/_csrf_field.php'; ?>
                                <input type="hidden" name="action" value="reject_store_link">
                                <input type="hidden" name="oeuvre_id" value="<?= $oeuvreId ?>">
                                <input type="hidden" name="store" value="<?= Moncine\View::escape($store) ?>">
                                <button type="submit" class="btn btn-ghost btn-sm">Ignorer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
