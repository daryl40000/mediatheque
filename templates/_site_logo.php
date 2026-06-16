<?php
/**
 * Logo Médiathèque (en-tête, pages auth).
 *
 * @var string $logoClass Classes CSS additionnelles sur le lien (ex. logo--auth)
 */
$logoClass = trim('logo ' . ($logoClass ?? ''));
$logoSize = (int) ($logoSize ?? 56);
if ($logoSize <= 0) {
    $logoSize = 56;
}
?>
<a href="/" class="<?= Moncine\View::escape($logoClass) ?>">
    <img class="logo__img" src="/assets/img/logo.png"
         alt="<?= Moncine\View::escape(MONCINE_APP_NAME) ?>"
         width="<?= $logoSize ?>" height="<?= $logoSize ?>" decoding="async">
</a>
