<?php
/**
 * Export / import léger : bibliothèque personnelle (collection + envies).
 * Les métadonnées catalogue sont référencées par ID catalogue (oeuvre_id).
 */

declare(strict_types=1);

namespace Moncine;

final class LibraryExportSchema
{
    public const SHEET_BIBLIOTHEQUE = 'Bibliotheque';
    public const SHEET_HISTORIQUE = 'Historique';

    /**
     * Colonnes export bibliothèque : clé interne => libellé.
   *
   * @var array<string, string>
   */
  public const COLUMNS = [
    'oeuvre_id' => 'ID catalogue',
    'bibliotheque_id' => 'ID bibliothèque',
    'titre' => 'Titre',
    'realisateur' => 'Réalisateur',
    'statut' => 'Statut',
    'support_physique' => 'Support',
    'format_image' => 'format image',
    'format_son' => 'Bande sonore FR',
    'saga' => 'Saga',
    'saga_ordre' => 'N° saga',
    'saison_numero' => 'Saison n°',
    'saison_label' => 'Libellé saison',
    'ean' => 'EAN',
    'vu' => 'Vu',
    'note' => 'Note',
  ];

  /** @var array<string, list<string>> */
  public const COLUMN_ALIASES = [
    'oeuvre_id' => [
      'id catalogue',
      'oeuvre_id',
      'oeuvre id',
      'id oeuvre',
      'catalogue id',
      'id moncine',
    ],
    'bibliotheque_id' => [
      'id bibliotheque',
      'bibliotheque_id',
      'id bibliothèque',
      'film_id',
      'id film',
    ],
    'titre' => ['titre', 'title', 'nom', 'film'],
    'realisateur' => ['realisateur', 'director', 'auteur'],
    'statut' => [
      'statut',
      'status',
      'wishlist',
      'liste',
      'mes envies',
      'collection',
    ],
    'format_image' => ['format image', 'format_image', 'image', 'video'],
    'format_son' => [
      'format son',
      'format_son',
      'bande sonore fr',
      'bande sonore',
      'son',
      'audio',
    ],
    'support_physique' => [
      'support',
      'support physique',
      'support_physique',
      'type support',
    ],
    'saga' => ['saga', 'suite', 'franchise'],
    'saga_ordre' => [
      'n saga',
      'n° saga',
      'saga ordre',
      'saga_ordre',
      'ordre saga',
    ],
    'saison_numero' => ['saison n', 'saison numero', 'saison_numero', 'numero saison'],
    'saison_label' => ['libelle saison', 'saison label', 'saison_label'],
    'ean' => ['ean', 'code barres', 'code-barres', 'barcode'],
    'vu' => ['vu', 'vu le', 'date vu', 'visionne'],
    'note' => ['note', 'notes', 'notation'],
  ];

  /** @var array<string, list<string>> */
  public const HISTORIQUE_COLUMN_ALIASES = [
    'oeuvre_id' => [
      'id catalogue',
      'oeuvre_id',
      'oeuvre id',
      'id oeuvre',
      'catalogue id',
    ],
    'titre' => ['titre', 'title', 'nom', 'film'],
    'realisateur' => ['realisateur', 'director', 'auteur'],
    'date_vue' => ['date vue', 'date_vue', 'vu', 'date'],
    'note' => ['note', 'notes', 'notation'],
  ];

  public const HISTORIQUE_HEADERS = [
    'ID catalogue',
    'Titre',
    'Réalisateur',
    'Date vue',
    'Note',
  ];

  /** @return list<string> */
  public static function headers(): array
  {
    return array_values(self::COLUMNS);
  }

  /** Champs stockés sur bibliotheque (hors clés de liaison et vu/note). */
  /** @return list<string> */
  public static function libraryDatabaseFields(): array
  {
    return [
      'support_physique',
      'format_image',
      'format_son',
      'saga',
      'saga_ordre',
      'saison_numero',
      'saison_label',
      'ean',
      'statut',
    ];
  }

  /**
   * @param array<string, mixed> $film
   * @return list<string>
   */
  public static function rowToExport(array $film): array
  {
    $note = $film['derniere_note'] ?? null;
    $row = [];

    foreach (self::COLUMNS as $key => $_label) {
      $row[] = match ($key) {
        'oeuvre_id' => (int) ($film['oeuvre_id'] ?? 0) > 0 ? (string) (int) $film['oeuvre_id'] : '',
        'bibliotheque_id' => (int) ($film['id'] ?? 0) > 0 ? (string) (int) $film['id'] : '',
        'support_physique' => SupportPhysique::label((string) ($film['support_physique'] ?? '')),
        'vu' => CollectionExportSchema::formatVueDateForExport((string) ($film['derniere_vue'] ?? '')),
        'note' => $note !== null && $note !== '' ? (string) $note : '',
        'saga' => trim((string) ($film['saga'] ?? '')),
        'saga_ordre' => (int) ($film['saga_ordre'] ?? 0) > 0
            && trim((string) ($film['saga'] ?? '')) !== ''
            ? (string) (int) $film['saga_ordre']
            : '',
        'saison_numero' => (int) ($film['saison_numero'] ?? 0) > 0
            ? (string) (int) $film['saison_numero']
            : '',
        'statut' => LibraryStatut::label((string) ($film['statut'] ?? LibraryStatut::COLLECTION)),
        default => (string) ($film[$key] ?? ''),
      };
    }

    return $row;
  }

  public static function columnLabelsText(): string
  {
    return implode(', ', self::headers());
  }
}
