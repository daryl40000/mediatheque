# Comptes et mots de passe — Moncine

## Pour les utilisateurs

- **Inscription publique** (si activée par l’admin) : voir [inscription-utilisateurs.md](inscription-utilisateurs.md).
- **Mon compte** (`/parametres.php`) : modifier le nom, le profil et le mot de passe.
- **Changement d’e-mail** : mot de passe actuel requis ; lien de confirmation envoyé à la **nouvelle** adresse ; l’**ancienne** adresse reçoit un message d’information (migration `029`).
- **Supprimer mon compte** (même page, section dédiée) : réservé aux comptes **utilisateur** (pas aux administrateurs). Il faut saisir le **mot de passe actuel** et confirmer. Après suppression, redirection vers la page de connexion.
- **Connexion** (`/connexion.php`) : adresse e-mail **ou pseudo** (si renseigné sur le compte) + mot de passe.
- **Mot de passe oublié** (`/mot-de-passe-oublie.php`) : recevoir un lien par e-mail (valable 1 heure) — **uniquement via l’e-mail**, pas le pseudo.
- Un administrateur peut aussi vous donner un **mot de passe provisoire** ; changez-le ensuite dans Mon compte.

### Connexion par pseudo (**0.7.18**)

| Règle | Détail |
|-------|--------|
| **Champ de connexion** | « Adresse e-mail ou pseudo » |
| **Pseudo requis** | Sans pseudo sur le compte, seul l’e-mail fonctionne |
| **Unicité** | Deux comptes ne peuvent pas avoir le même pseudo (insensible à la casse) |
| **Détection e-mail** | Si la saisie contient `@`, elle est traitée comme un e-mail |

Migration : `064_utilisateur_pseudo_login.sql` · classes `LoginIdentifier`, `Auth::login()`.

### Suppression du compte (détail)

| Élément | Comportement |
|---------|----------------|
| **Qui peut supprimer ?** | Tout utilisateur connecté **sauf** les comptes avec le rôle administrateur. |
| **Données supprimées** | Compte, envies personnelles, historique de vision personnel. |
| **Groupe famille** | Les films de la collection partagée restent pour les **autres** membres (réattribués). Si vous êtes **seul** dans le groupe, ce groupe et sa collection sont **supprimés**. |
| **Demandes d’inscription** | Les demandes liées à votre e-mail sont effacées. |
| **Administrateur** | Un admin supprime les comptes depuis **Comptes utilisateurs** (`/utilisateurs.php`), pas depuis Mon compte. |

## Pour l’administrateur (YunoHost)

### Envoi des e-mails

La réinitialisation utilise la fonction PHP `mail()`. Sur YunoHost, configurez l’envoi de mails du serveur (ex. `postfix`) ou définissez :

| Variable | Rôle |
|----------|------|
| `MONCINE_MAIL_FROM` | Adresse expéditeur (ex. `moncine@votredomaine.fr`) |
| `MONCINE_BASE_URL` | URL publique de l’app (ex. `https://moncine.example.net`) si le lien dans l’e-mail est incorrect |
| `MONCINE_TRUST_PROXY` | Mettre `1` derrière Nginx/YunoHost pour que le limiteur de tentatives utilise la vraie IP client (`X-Real-IP` / `X-Forwarded-For`). Sans cela, seule `REMOTE_ADDR` est utilisée. |
| `MONCINE_DATA_PATH` | Dossier des données (base SQLite, clés, `auth_rate_limit/`) |

Sans serveur mail fonctionnel, les utilisateurs peuvent demander à l’admin un **Réinit. MDP** depuis la page Comptes.

### Migration base

Après mise à jour du paquet :

```bash
php lib/cli/migrate.php
```

Cela applique les migrations en attente (ex. `004` mots de passe, `027`–`029` inscription / changement d’e-mail, **`064` pseudo connexion**).

### Sécurité

- Limite de tentatives sur la connexion, l’**inscription** et « mot de passe oublié » (session + compteurs **par IP** dans `data/auth_rate_limit/`, non contournables en changeant de navigateur).
- Jetons stockés **hachés** en base ; usage unique.
- Message neutre si l’e-mail n’existe pas (pas d’énumération des comptes).
