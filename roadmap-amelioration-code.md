# Roadmap d'amélioration de la qualité de code

**Dernière mise à jour :** 2026-07-19 (version **0.7.25** — fusion catalogue, liens sujets magazines)  
**Complément de :** [ROADMAP.md](ROADMAP.md) (fonctionnalités produit) — ce fichier traite uniquement de la **qualité et de la structure du code**.

## Objectif

Améliorer la maintenabilité du projet (objectif indicatif : passer d'un code **8.5/10** à **9.5/10**) par des **petits chantiers isolés**, testables, sans big bang.

## Principes directeurs

1. **Une PR = un pilote** — ne pas refactorer tous les repositories d'un coup.
2. **S'inspirer de l'existant** avant d'inventer de nouvelles abstractions.
3. **Tests verts** après chaque extraction (`./vendor/bin/phpunit`).
4. **Critères de fin** explicites pour chaque phase (voir ci-dessous).

### Bonnes pratiques déjà présentes dans le dépôt

À réutiliser plutôt que réinventer :

| Pattern existant | Rôle | Exemple |
|------------------|------|---------|
| `*RowMapper` | Formatage des lignes pour l'affichage | `BdRowMapper`, hydratation catalogue |
| `*PrintListService` | Données pour listes PDF / impression | `BdPrintListService`, `MagazinePrintListService` |
| `*ListFilter` | Filtres SQL de liste | `BdListFilter` |
| `LikePattern` | Recherche texte SQL sécurisée | recherches dans les repositories |
| Services métier | Logique hors des pages `www/` | `ShareLinkService`, `RegistrationService` |

---

## État des lieux (tailles réelles, juin 2026)

Fichiers les plus volumineux à surveiller :

| Fichier | Lignes (approx.) | Priorité découpage |
|---------|------------------|-------------------|
| `lib/MagazineRepository.php` | **481** (était ~2 354) | Fait (pilote 3) |
| `lib/CatalogFilmRepository.php` | ~2 170 | Haute |
| `lib/BdRepository.php` | **514** | Fait (pilote 2) |
| `lib/GameRepository.php` | **539** | Fait (pilote 1) |
| `lib/View.php` | ~1 300 | Moyenne (URLs par domaine) |
| `lib/FilmRepositoryLegacy.php` | ~1 290 | Dette — fusion ou suppression |
| `lib/UserPublicProfileService.php` | ~1 170 | Moyenne |
| `lib/UtilisateurRepository.php` | ~890 | Moyenne |

**Corrections par rapport à une ancienne version de ce document :**

- `www/import.php` fait **~300 lignes**, pas des milliers. L'import lourd est plutôt dans `MagazineCatalogImporter.php`, `CatalogDomainExtensions.php`, scripts CLI.
- Le dépôt compte **~97 fichiers de tests** PHPUnit — bonne base, à étendre de façon ciblée.

---

## Ordre d'exécution recommandé

L'ordre des numéros de phase **ne correspond pas** à l'ordre de travail conseillé.

| Ordre | Phase | Pourquoi |
|-------|-------|----------|
| **1** | Phase A — Services contrôleurs (pilote) | Petit périmètre, bénéfice immédiat, peu de risque |
| **2** | Phase B — Découpage d'un repository pilote | Apprendre sur `GameRepository` ou `BdRepository` |
| **3** | Phase C — Helpers SQL partagés | Factoriser ce qui est **vraiment** dupliqué |
| **4** | Phase D — Tests sur les extractions | Verrouiller les régressions |
| **5** | Phase E — Erreurs (`int\|string` → exceptions) | Progressif, **sans** handler global prématuré |
| **6** | Phase F — Validator commun | Après E, sur un formulaire pilote |
| **7** | Dette transversale | `FilmRepositoryLegacy`, `View.php`, `BibliothequeRepository` |

---

## Phase A : Extraire la logique des contrôleurs (Priorité : **HAUTE** — commencer ici)

### Pourquoi ?

Les pages `www/*.php` mélangent souvent HTTP (POST, redirections) et règles métier. Le projet a déjà des services (`ShareLinkService`, `BdPrintListService`…) : il faut **étendre** ce modèle.

### Pilote recommandé : `www/films.php`

~177 lignes dont des **actions de masse** (saga, support, enrichissement TMDB, suppression).

#### 1. Créer `lib/Service/FilmBulkActionService.php`

```php
<?php
declare(strict_types=1);

namespace Moncine\Service;

use Moncine\FilmRepository;
use Moncine\FilmEnricher;
use Moncine\CatalogAdmin;
use Moncine\SupportPhysique;
use Moncine\Exception\ValidationException;

class FilmBulkActionService
{
    public function __construct(
        private readonly FilmRepository $repo = new FilmRepository(),
    ) {
    }

    /**
     * @param list<int> $filmIds
     * @param array<string, mixed> $postData
     * @return array{success: true, message: string, count: int}
     */
    public function handleBulkAction(string $action, array $filmIds, array $postData): array
    {
        if ($filmIds === []) {
            throw new ValidationException('Sélectionnez au moins un film.');
        }

        return match ($action) {
            'assign_saga' => $this->handleAssignSaga($filmIds, $postData),
            'set_support' => $this->handleSetSupport($filmIds, $postData),
            'enrich_tmdb' => $this->handleEnrichTmdb($filmIds),
            'delete_films' => $this->handleDeleteFilms($filmIds),
            default => throw new ValidationException('Action inconnue.'),
        };
    }

    // … méthodes privées handleAssignSaga, handleSetSupport, etc.
}
```

#### 2. Simplifier `www/films.php`

Le contrôleur ne fait plus que : CSRF → parser les IDs → appeler le service → rediriger avec message.

```php
try {
    $result = (new FilmBulkActionService())->handleBulkAction($action, $filmIds, $_POST);
    moncine_films_bulk_redirect($redirectUrl, ['bulk_ok' => $result['count'], 'bulk_msg' => $result['message']]);
} catch (ValidationException $e) {
    moncine_films_bulk_redirect($redirectUrl, ['bulk_error' => $e->getMessage()]);
}
```

#### 3. Autres candidats (après le pilote films)

| Page / zone | Service à créer |
|-------------|-----------------|
| `www/jeux.php` (actions masse) | `GameBulkActionService` |
| Import catalogue magazines | étendre `MagazineCatalogImporter` ou `CatalogImportService` |
| `www/enregistrer-*.php` très longs | service dédié par domaine |

**Ne pas traiter en priorité :** `www/import.php` (déjà court).

### Critères de fin (Phase A)

- [ ] `FilmBulkActionService` créé + tests unitaires
- [ ] `www/films.php` allégé (logique bulk déléguée)
- [ ] Parcours manuel : assigner saga, changer support, supprimer — OK
- [ ] `./vendor/bin/phpunit` vert

### Bénéfice attendu

Contrôleurs de 50–100 lignes sur les pages traitées, logique réutilisable et testable.

---

## Phase B : Découper les classes volumineuses (Priorité : **HAUTE**)

### Pourquoi ?

Les classes de 1 500–2 200 lignes sont difficiles à lire, à tester et à faire évoluer (BD, magazines, jeux, films catalogue).

### Stratégie : découper par **responsabilité métier**, pas seulement par type technique

Préférer :

- **Liste / pagination** (requêtes + filtres)
- **Catalogue partagé** (création œuvre, métadonnées domaine)
- **Bibliothèque utilisateur** (collection, envies, possession)
- **Import / export**

Éviter de créer un `MagazineFormatter` qui duplique ce que font déjà les `RowMapper` et `PrintListService`.

### Ordre de découpage suggéré

1. **`GameRepository.php`** — **fait** (**539** lignes)
2. **`BdRepository.php`** — **fait** (**514** lignes)
3. **`MagazineRepository.php`** — **fait** (**481** lignes ; extractions SQL, query, catalogue, bibliothèque, PDF)
4. **`CatalogFilmRepository.php`** (~2 170 lignes) — prochain pilote suggéré

### Exemple d'extractions pour un repository

| Extraction | Fichier cible | Contenu |
|------------|---------------|---------|
| Requêtes liste série | `GameSeriesQuery.php` ou `GameQueryBuilder.php` | `JOIN`, `WHERE`, `ORDER BY` |
| Filtres possession / statut | garder dans le repository ou `GameListFilter` | comme `BdListFilter` |
| Formatage affichage | `GameRowMapper.php` si absent | pas un « Formatter » générique |

### Autres fichiers à traiter (priorité moyenne)

- **`lib/View.php`** — scinder par domaine : `ViewBd`, `ViewMagazine`, `ViewGame` (URLs et helpers)
- **`lib/UserPublicProfileService.php`** — extraire profils BD / magazine / jeu
- **`lib/FilmRepositoryLegacy.php`** — planifier fusion avec `FilmRepository` / `CatalogFilmRepository` ou suppression

### Critères de fin (Phase B — par pilote)

- [x] Repository pilote < **800 lignes** (objectif intermédiaire) — `GameRepository` : **539** lignes (était ~1487, puis ~1130)
- [x] Extractions pilote jeux : `GameFormPayload`, `GameCatalogSql`, `GameCatalogWriter`, `GameLibraryFields`
- [x] Extractions pilote jeux (suite) : `GameLibraryQuery`, `GameCatalogUpdater`, `GameCatalogCreator`, `GameLibraryAttach`, `GamePosterService`
- [x] Repository pilote 2 < **800 lignes** — `BdRepository` : **514** lignes (était ~1566)
- [x] Extractions pilote BD : `BdCatalogSql`, `BdCatalogWriter`, `BdTomeOrdre`, `BdLibraryQuery`, `BdCatalogUpdater`, `BdCatalogCreator`, `BdLibraryAttach`, `BdPosterService`
- [x] Repository pilote 3 < **800 lignes** — `MagazineRepository` : **481** lignes (était ~2 354)
- [x] Extractions pilote magazines : `MagazineCatalogSql`, `MagazineSearchSql`, `MagazineNumeroOrdre`, `MagazineLibraryQuery`, `MagazineCatalogValidator`, `MagazineCatalogWriter`, `MagazineCatalogCreator`, `MagazineCatalogUpdater`, `MagazineLibraryAttach`, `MagazineLibraryMutations`, `MagazinePdfService`
- [ ] Aucune régression sur les tests d'intégration du domaine (échecs préexistants en suite complète BD/magazines ; isolés OK)
- [ ] Une page liste + une page fiche testées manuellement

### Bénéfice attendu

Fichiers de 300–800 lignes, responsabilités claires.

---

## Phase C : Factoriser le SQL commun (Priorité : **MOYENNE**)

### Pourquoi ?

Un `AbstractRepository` avec `getTableName()` est **trop simpliste** : chaque domaine joint `oeuvres`, `bibliotheque`, tables d'extension (`oeuvre_bd`, `oeuvre_magazine`…).

En revanche, du code est **réellement dupliqué** aujourd'hui :

- `filterParamsForSql()` — présent dans `MagazineRepository`, `BdRepository`, `MagazineSubjectRepository`
- Validation des colonnes de tri (`isValidSortColumn`, `SORT_COLUMNS`)
- Clause `LIMIT` / `OFFSET`

### Actions concrètes (pragmatiques)

#### 1. Trait `lib/Repository/SqlNamedParamsTrait.php`

```php
<?php
declare(strict_types=1);

namespace Moncine\Repository;

trait SqlNamedParamsTrait
{
    /**
     * Ne garde que les paramètres nommés réellement utilisés dans la requête SQL.
     *
     * @param array<string, int|string> $params
     * @return array<string, int|string>
     */
    protected function filterParamsForSql(string $sql, array $params): array
    {
        if (!preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $sql, $matches)) {
            return [];
        }
        $filtered = [];
        foreach (array_unique($matches[1]) as $name) {
            if (array_key_exists($name, $params)) {
                $filtered[$name] = $params[$name];
            }
        }
        return $filtered;
    }
}
```

#### 2. Classe `lib/Repository/SortColumnHelper.php`

```php
public static function resolve(string $sortBy, array $columns, string $default = 'titre'): string
{
    return isset($columns[$sortBy]) ? $sortBy : $default;
}
```

#### 3. Classe `lib/Repository/PaginationClause.php`

Helper pour ajouter `ORDER BY … LIMIT … OFFSET …` de façon uniforme.

#### 4. Migration progressive

- Ajouter le trait sur **BdRepository** et **MagazineRepository**
- Supprimer les méthodes privées dupliquées
- **Ne pas** forcer l'héritage `extends AbstractRepository` sur tous les repos

### Critères de fin (Phase C)

- [ ] `filterParamsForSql` en un seul endroit
- [ ] Au moins 2 repositories migrés sur le trait
- [ ] Tests existants verts

### Bénéfice attendu

Moins de copier-coller, sans architecture rigide inadaptée aux JOIN multi-tables.

---

## Phase D : Renforcer les tests (Priorité : **MOYENNE** — en parallèle des phases A–C)

### Pourquoi ?

~97 fichiers de tests : bonne base, mais les refactorings doivent être **verrouillés** par des tests sur les zones touchées.

### Actions concrètes

#### 1. Tester chaque nouveau service (dès la Phase A)

```php
// tests/Unit/FilmBulkActionServiceTest.php
public function testAssignSagaRequiresSagaName(): void
{
    $this->expectException(ValidationException::class);
    (new FilmBulkActionService())->handleBulkAction('assign_saga', [1], []);
}
```

#### 2. Prioriser les tests d'intégration sur

- Filtres possession (magazines, BD)
- Tri et pagination
- `BibliothequeRepository::normalizeSupportPhysiqueForStorage` (multi-domaines)
- Imports catalogue (ABM)

#### 3. Objectifs réalistes

| Métrique | Actuel | Cible |
|----------|--------|-------|
| Fichiers de tests | ~97 | **120+** après phases A–C |
| Couverture globale | non mesurée | mesurer d'abord (`phpunit --coverage-text`), viser **+10 points** par chantier |

**Éviter** de viser 70 % de couverture globale sans baseline : peu actionnable.

### Critères de fin (Phase D)

- [ ] Chaque extraction de Phase A ou B a au moins un test unitaire ou d'intégration
- [ ] Baseline de couverture documentée dans ce fichier

---

## Phase E : Uniformiser les retours d'erreur (Priorité : **BASSE** — progressive)

### Pourquoi ?

Beaucoup de méthodes retournent `int|string` (ID ou message d'erreur). C'est verbeux mais **cohérent avec les pages** qui font :

```php
if (!is_int($result)) {
    header('Location: …&error=' . urlencode($result));
}
```

### Ce qu'il faut faire

#### 1. Créer `lib/Exception/` (pour le **nouveau** code)

- `ValidationException` — erreurs utilisateur (message affichable)
- `NotFoundException` — ressource absente
- `RepositoryException` — erreur technique base / transaction

#### 2. Utiliser les exceptions dans les **nouveaux services** (Phase A)

Les services lancent `ValidationException` ; le contrôleur `www/*.php` catch et redirige. **Pas de changement massif des repositories tout de suite.**

#### 3. Migrer les repositories **un par un**, avec adaptation des pages appelantes

Exemple pilote : `UtilisateurRepository::create()` — uniquement quand `RegistrationService` et les pages admin sont prêts.

### Ce qu'il ne faut **pas** faire (encore)

**Ne pas** ajouter un `set_exception_handler` global dans `bootstrap.php` tant que la majorité des pages attendent des redirections avec `?error=`. Un handler global afficherait une page 500 au lieu d'un message utilisateur compréhensible.

### Critères de fin (Phase E — par pilote)

- [ ] Un repository + ses pages appelantes migrés
- [ ] Comportement utilisateur identique (même message, même redirection)
- [ ] Pas de handler global sans stratégie complète

---

## Phase F : Validator commun (Priorité : **BASSE**)

### Pourquoi ?

Validation dispersée dans les repositories. Un helper commun aide, mais **après** la Phase E sur un cas pilote.

### Pilote recommandé

`UtilisateurRepository` / inscription (`RegistrationService`).

### Fichier cible : `lib/Validator/Validator.php`

Chaînage `required()`, `email()`, `minLength()`, `orThrow()` — voir implémentation type dans l'historique git de ce document.

### Critères de fin (Phase F)

- [ ] Validator utilisé sur **un** flux complet (inscription ou création utilisateur admin)
- [ ] Messages d'erreur inchangés côté utilisateur

---

## Dette transversale (à planifier)

| Sujet | Action |
|-------|--------|
| **`BibliothequeRepository`** | Documenter et centraliser la gestion multi-supports (films / magazines / BD) ; éviter les oublis comme supports BD non reconnus à l'insertion |
| **`FilmRepositoryLegacy.php`** | Auditer les appels restants → fusion ou suppression |
| **`ROADMAP.md` produit** | Tenir à jour séparément (versions, modules livrés) ; ce fichier ne remplace pas la roadmap fonctionnelle |
| **Sync doc** | Mettre à jour [doc/](doc/) quand une extraction change les points d'entrée |

---

## Planning indicatif

| Phase | Durée estimée | Difficulté | Impact | Ordre conseillé |
|-------|---------------|------------|--------|-----------------|
| A — Services contrôleurs | 1–2 jours | Faible | Élevé | **1** |
| B — Découpage repositories | 2–4 jours / pilote | Moyenne | Élevé | **2** |
| C — Helpers SQL | 1–2 jours | Faible | Moyen | **3** |
| D — Tests | continu | Faible | Élevé | **en parallèle** |
| E — Exceptions | 2–3 jours / pilote | Moyenne | Moyen | **5** |
| F — Validator | 1 jour / pilote | Faible | Faible | **6** |

**Total estimé :** 10–18 jours étalés (une PR à la fois).

---

## Conseils pour débutants

### Par où commencer ?

1. **Phase A** — `FilmBulkActionService` : petit, testable, vous voyez tout de suite le résultat dans `films.php`.
2. **Phase D** — écrire 2–3 tests pour ce service.
3. **Phase B** — découper **une seule** classe (`GameRepository` recommandé).
4. **Phase C** — extraire `filterParamsForSql` en trait commun.

### Règles d'or

- **Ne pas** tout refactorer d'un coup.
- **Toujours** lancer les tests après chaque extraction.
- **Copier** les patterns qui existent (`BdRowMapper`, `ShareLinkService`) avant d'en créer de nouveaux.
- **Une PR = un pilote** ; review plus simple, retour arrière facile.

### Phases à garder pour plus tard

- Migration globale `int|string` → exceptions (Phase E complète)
- Validator partout (Phase F)
- Découpage de `CatalogFilmRepository` (Phase B — prochain gros morceau)

---

## Suivi des progrès

- [x] **Phase A** — `FilmBulkActionService` + `films.php` allégé
- [x] **Phase B** — Pilote 1 : `GameRepository` (**539** lignes ; extractions `GameLibraryQuery`, `GameCatalogUpdater`, `GameCatalogCreator`, `GameLibraryAttach`, `GamePosterService`)
- [x] **Phase B** — Pilote 2 : `BdRepository` (**514** lignes ; extractions `BdCatalogSql`, `BdLibraryQuery`, `BdCatalogWriter`, `BdTomeOrdre`, `BdCatalogUpdater`, `BdCatalogCreator`, `BdLibraryAttach`, `BdPosterService`)
- [x] **Phase B** — Pilote 3 : `MagazineRepository` (**481** lignes ; extractions `MagazineCatalogSql`, `MagazineSearchSql`, `MagazineNumeroOrdre`, `MagazineLibraryQuery`, `MagazineCatalogValidator`, `MagazineCatalogWriter`, `MagazineCatalogCreator`, `MagazineCatalogUpdater`, `MagazineLibraryAttach`, `MagazineLibraryMutations`, `MagazinePdfService`)
- [ ] **Phase C** — `SqlNamedParamsTrait` + 2 repositories migrés
- [ ] **Phase D** — Baseline couverture documentée ; 120+ fichiers de tests
- [ ] **Phase E** — Pilote exceptions (1 repository + pages)
- [ ] **Phase F** — Pilote Validator (inscription / utilisateur)
- [ ] **Dette** — `FilmRepositoryLegacy` traité
- [ ] **Dette** — `View.php` scindé (au moins URLs BD / magazine / jeu)
