# Questionnaire du soir

Le **questionnaire du soir** aide à choisir un film dans votre collection selon vos envies (type, durée, styles, etc.).

## Parcours utilisateur

1. **Accueil** ou menu → **Lancer le questionnaire** (`/quiz.php`).
2. Répondre aux questions (critères de sélection).
3. **Proposition** (`/resultat.php`) : un film est tiré au hasard parmi ceux qui correspondent.
4. Noter la proposition ou demander un **autre tirage**.
5. Optionnel : consulter les **mieux notés** de la session (`/meilleurs.php`).
6. **Refaire la sélection** pour changer les critères du questionnaire.

## Page proposition (`/resultat.php`) — v0.9.0

En haut de la fiche (sous le titre « Votre proposition ») :

| Zone | Contenu |
|------|---------|
| Barre d’actions | Boutons de note (Non, Bof, Pourquoi pas, Pas mal, Génial) |
| | Bouton **Autre tirage** (exclut le film affiché et en propose un autre) |
| | Lien **Voir les mieux notés** (si au moins une note a été donnée dans la session) |

En dessous : affiche, titre, détails, synopsis, **On le regarde ce soir**.

En bas : **Refaire la sélection** (retour au questionnaire), **Accueil**.

Les critères du questionnaire (durée, type, etc.) ne sont plus répétés en texte sur cette page : ils restent actifs en arrière-plan pour les tirages suivants.

## Fichiers techniques

| Fichier | Rôle |
|---------|------|
| `www/quiz.php` | Formulaire du questionnaire |
| `www/resultat.php` | Tirage et affichage de la proposition |
| `www/noter.php` | Enregistrement d’une note (POST) |
| `www/meilleurs.php` | Films les mieux notés dans la session |
| `lib/QuizSession.php` | Critères, films déjà proposés, notes en session |
| `lib/Recommender.php` | Filtrage et tirage aléatoire |
| `lib/ChoixNote.php` | Libellés et scores des notes |
| `templates/resultat.php` | Mise en page de la proposition |
| `templates/_notes_form.php` | Boutons de notation |

## Notes de session

Les notes du questionnaire sont **temporaires** (session PHP) : elles servent à classer les propositions de la soirée en cours. Elles ne remplacent pas les notes permanentes sur la fiche film dans « Mes films ».
