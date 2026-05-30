<?php
/**
 * Enregistre une note « du soir » pour un film (session uniquement).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

use Moncine\ChoixNote;
use Moncine\Csrf;
use Moncine\QuizSession;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /quiz.php');
    exit;
}

$filmId = (int) ($_POST['film_id'] ?? 0);
$return = (string) ($_POST['return'] ?? 'resultat');

$failUrl = $return === 'meilleurs' ? '/meilleurs.php' : '/resultat.php?film_id=' . $filmId;
Csrf::rejectUnlessValid($_POST, $failUrl);

$noteKey = (string) ($_POST['note'] ?? '');

if ($filmId > 0 && ChoixNote::isValid($noteKey)) {
    QuizSession::setRating($filmId, $noteKey);
}

if ($return === 'meilleurs') {
    header('Location: /meilleurs.php');
    exit;
}

header('Location: /resultat.php?film_id=' . $filmId);
exit;
