<?php
/**
 * Enrichissement TMDB d’un film (bouton + correction par ID TMDB).
 *
 * @var int $filmId
 * @var bool $hasTmdbKey
 * @var string|null $enrichStatus ok|not_found|error
 * @var string $enrichMessage
 * @var string $returnPage film|resultat
 * @var int $currentTmdbId
 * @var string $currentTmdbMediaType
 * @var string $currentTmdbTvKind
 */
$enrichTarget = 'film';
$entityId = (int) ($filmId ?? 0);
require MONCINE_ROOT . '/templates/_enrich_entity_panel.php';
