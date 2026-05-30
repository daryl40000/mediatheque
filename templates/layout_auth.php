<?php
/** @var string $templateFile */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= Moncine\View::escape($pageTitle ?? MONCINE_APP_NAME) ?> — <?= Moncine\View::escape(MONCINE_APP_NAME) ?></title>
    <link rel="icon" href="/assets/img/favicon.ico" sizes="any">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/responsive.css">
</head>
<body class="auth-layout<?= !empty($wideLayout) ? ' page-wide' : '' ?>">
    <main class="container<?= !empty($wideLayout) ? ' container--wide' : ' container--narrow' ?>">
        <?php require $templateFile; ?>
    </main>
</body>
</html>
