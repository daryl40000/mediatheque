<?php
/**
 * @var array<string, mixed> $issue
 */
$supportTags = Moncine\MagazineSupport::tagsForIssue($issue);
if ($supportTags === []) {
    return;
}
?>
<span class="magazine-support-tags">
    <?php foreach ($supportTags as $tag): ?>
        <span class="magazine-tag magazine-tag--<?= Moncine\View::escape($tag) ?>">
            <?= Moncine\View::escape(Moncine\MagazineSupport::label($tag)) ?>
        </span>
    <?php endforeach; ?>
</span>
