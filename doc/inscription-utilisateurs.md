# Inscription publique

**Migration :** `027_inscription_requests.sql`

## Réglage administrateur

Page **Comptes utilisateurs** (`/utilisateurs.php`) → section **Inscription publique** :

| Mode | Comportement |
|------|----------------|
| **Désactivée** | Seul l’admin crée les comptes (comportement historique). |
| **Ouverte** | L’utilisateur s’inscrit, confirme son e-mail, puis peut se connecter. |
| **Avec approbation** | Après confirmation e-mail, un admin doit approuver la demande. |

## Parcours utilisateur

1. **Créer un compte** (`/inscription.php`) — lien visible sur la page de connexion si l’inscription est activée.
2. Message neutre puis **retour à la connexion** : un e-mail de confirmation est envoyé si la demande est acceptée.
3. Clic sur le lien (**48 h**) → `/confirmer-inscription.php` affiche une page (le GET seul ne confirme pas).
4. Clic sur **« Confirmer mon adresse e-mail »** (POST + protection CSRF) → traitement effectif.
5. **Mode ouvert** : compte créé → retour connexion pour se connecter.
6. **Mode avec approbation** : message d’attente → l’admin valide dans **Inscriptions à valider** (`/demandes-inscription.php`).

## Règles

- **Une seule demande active** par adresse e-mail (`pending_email` ou `pending_admin`).
- Mot de passe : 8 à 128 caractères ; pendant la demande, le hash est **chiffré** en base (clé serveur dans `data/.keys/registration_password.key`) puis **effacé** dès que le compte est créé ou la demande refusée.
- Lien de confirmation : valable **24 h** ; après le premier clic, le jeton n’est plus affiché dans l’URL (stockage session court).
- Limite de tentatives d’inscription (session + **fichiers par IP** dans `data/auth_rate_limit/`), comme la connexion et « mot de passe oublié ».

## E-mails

Nécessitent un serveur mail fonctionnel (`MONCINE_MAIL_FROM`, `MONCINE_BASE_URL` sur YunoHost). Voir [comptes-mot-de-passe.md](comptes-mot-de-passe.md).

## Fichiers principaux

| Fichier | Rôle |
|---------|------|
| `lib/RegistrationSettings.php` | Mode d’inscription (`app_metadata`) |
| `lib/RegistrationService.php` | Logique métier |
| `lib/InscriptionRequestRepository.php` | Table `inscription_requests` |
| `lib/LockoutThrottleStore.php` | Compteurs session + IP (partagé avec connexion / reset MDP) |
| `lib/RegistrationPasswordCipher.php` | Chiffrement des hash en attente dans `inscription_requests` |
| `lib/RegistrationConfirmSession.php` | Jeton de confirmation hors URL après le 1er chargement |
| `www/inscription.php` | Formulaire public |
| `www/confirmer-inscription.php` | Lien e-mail |
| `www/demandes-inscription.php` | File admin |
