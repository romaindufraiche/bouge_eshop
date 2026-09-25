# BOUGE Club — boutique en ligne

Boutique en ligne du concept store **BOUGE** : matériel de natation — bonnets,
lunettes, accessoires et vêtements — avec livraison en France ou retrait sur
place, et une interface d'administration prévue pour être utilisée sans
compétence technique.

BOUGE est le concept store ; **BOUGE Club** est son enseigne en ligne. Le logo
dessiné de la marque n'est pas modifié : le mot « Club » lui est adossé dans
une autre typographie (Manrope, capitales espacées). Le verrou tient dans un
seul gabarit, `templates/partials/logo.php`, et sa règle `.marque` dans la
feuille de style.

**PHP et MySQL, sans framework ni étape de compilation.** Le site tourne sur
n'importe quel hébergement mutualisé à quelques euros par mois : on dépose
les fichiers, on importe la base, c'est en ligne.

## Sommaire

- [Ce qu'il faut](#ce-quil-faut)
- [Démarrer en local](#démarrer-en-local)
- [Sur un Mac, en partant de zéro](#sur-un-mac-en-partant-de-zéro)
- [Configuration](#configuration)
- [Brancher Stripe](#brancher-stripe)
- [Mettre en ligne chez OVH](#mettre-en-ligne-chez-ovh)
- [Chez un autre hébergeur](#chez-un-autre-hébergeur)
- [Aperçu statique sur GitHub Pages](#aperçu-statique-sur-github-pages)
- [Le catalogue de démonstration](#le-catalogue-de-démonstration)
- [Structure du site](#structure-du-site)
- [Le compte client](#le-compte-client)
- [Utiliser l'administration](#utiliser-ladministration)
- [Direction artistique](#direction-artistique)
- [Organisation du code](#organisation-du-code)
- [Choix techniques](#choix-techniques)
- [Points à traiter avant l'ouverture](#points-à-traiter-avant-louverture)

## Ce qu'il faut

| Élément | Version | Remarque |
| --- | --- | --- |
| PHP | 8.1 ou plus | Avec `pdo_mysql`, `mbstring` et `curl` |
| MySQL ou MariaDB | 5.7+ / 10.2+ | Une base, un utilisateur |
| Apache | avec `mod_rewrite` | Standard chez tous les hébergeurs mutualisés |

Pas de Node.js, pas de compilation, pas de `composer install` sur le serveur :
le dossier `vendor/` (3,5 Mo, la bibliothèque Stripe) est versionné avec le
projet, précisément pour que la mise en ligne se résume à un transfert de
fichiers.

## Démarrer en local

```bash
# 1. La base
mysql -u root -p -e "CREATE DATABASE bouge CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

# 2. La configuration
cp config/config.example.php config/config.php
#    puis renseigner les identifiants de base et 'site_url' => 'http://localhost:8000'

# 3. Les tables et le compte d'administration
php database/install.php contact@bouge.fr bouge-dev-2026

# 4. Un catalogue de démonstration (facultatif)
php database/seed.php

# 5. Le serveur de développement
php -S localhost:8000 -t public dev-server.php
```

La boutique répond sur <http://localhost:8000> et l'administration sur
<http://localhost:8000/admin>.

> L'option `-t public` est indispensable : sans elle, les images et la feuille
> de style renvoient 404.

### Sur un Mac, en partant de zéro

macOS ne fournit plus PHP depuis la version 12, et jamais MySQL. Les deux
s'installent avec [Homebrew](https://brew.sh) :

```bash
# Homebrew, si vous ne l'avez pas encore (il demandera votre mot de passe)
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"

brew install php mysql
brew services start mysql
```

Sur un Mac à puce Apple, la fin de l'installation de Homebrew affiche deux
lignes « Next steps » à copier-coller : elles ajoutent `brew` au PATH. Sans
elles, `brew` reste introuvable au terminal suivant.

Ensuite, dans le dossier du projet, ces commandes règlent la configuration
pour une base locale — l'utilisateur `root` de MySQL n'a pas de mot de passe
tant qu'on ne lui en donne pas :

```bash
# Le -p est indispensable : si root a un mot de passe, la création échoue
# sinon, et les commandes suivantes butent sur « Unknown database ».
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS bouge CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p -e "SHOW DATABASES" | grep bouge      # doit répondre « bouge »

cp config/config.example.php config/config.php
sed -i '' "s/'user'     => 'bouge'/'user'     => 'root'/"               config/config.php
sed -i '' "s/'password' => 'a-remplacer'/'password' => ''/"             config/config.php
sed -i '' "s|'site_url' => 'https://www.bouge.fr'|'site_url' => 'http://localhost:8000'|" config/config.php
sed -i '' "s/'debug' => false/'debug' => true/"                          config/config.php

php database/install.php contact@bouge.fr bouge-dev-2026
php database/seed.php
php -S localhost:8000 -t public dev-server.php
```

La boutique répond sur <http://localhost:8000>, l'administration sur
<http://localhost:8000/admin>.

> `sed -i ''` avec deux apostrophes : c'est la forme macOS. Sous Linux, c'est
> `sed -i` tout court.

### Les deux scripts de base

| Script | Ce qu'il fait |
| --- | --- |
| `php database/install.php <email> <mot-de-passe>` | **Crée les tables** à partir de `database/schema.sql`, puis le compte d'administration. Le catalogue reste vide. C'est le script d'une vraie installation. |
| `php database/seed.php [email] [mot-de-passe]` | Remplit un catalogue de démonstration (5 catégories, 14 produits). Les tables doivent déjà exister. |
| `php database/seed-demo.php` | Ajoute une soixantaine de produits réels avec leurs visuels, pour une démonstration parlante. Voir [Le catalogue de démonstration](#le-catalogue-de-démonstration). |

Sur une base **déjà installée**, les évolutions du schéma sont dans
`database/migrations/`, à jouer dans l'ordre :

```bash
mysql -u UTILISATEUR -p NOM_DE_LA_BASE < database/migrations/001-usages.sql
```

Chacune porte en tête ce qu'elle fait et si elle vous concerne. La dernière,
`004-maillots.sql`, renomme la catégorie « Vêtements » en « Maillots » : son
adresse passe de `/boutique/vetements` à `/boutique/maillots`.

`install.php` **recrée les tables** et efface donc tout le contenu existant,
commandes comprises : il est fait pour une première installation.

`seed.php` peut être relancé à volonté pour remettre le catalogue de
démonstration à zéro : les **commandes ne sont jamais touchées**, elles
conservent les libellés et les prix recopiés au moment de l'achat.

## Configuration

Deux fichiers, aucune variable d'environnement.

### `config/config.php` — ce qui change d'une installation à l'autre

Copié depuis `config/config.example.php`, **jamais versionné** : il contient
vos mots de passe.

| Clé | Rôle |
| --- | --- |
| `db.host` / `name` / `user` / `password` | Identifiants donnés par l'hébergeur |
| `db.port` / `socket` | À laisser vides sauf exigence de l'hébergeur |
| `site_url` | Adresse publique, sans slash final. Sert aux retours Stripe et au référencement |
| `stripe.secret_key` / `publishable_key` | Clés du tableau de bord Stripe |
| `stripe.webhook_secret` | Donné à la création du webhook |
| `debug` | `true` en développement seulement |

> `debug` doit rester à `false` en production : une erreur détaillée affichée
> au public révèle la structure du site et parfois des identifiants.

### `config/shop.php` — les réglages commerciaux

Versionné, car il décrit la boutique et non le serveur : nom, baseline,
adresse de contact, frais de port (`490` centimes), franco de port
(`6000` centimes), plafonds du panier, taille maximale des photos.

Il porte aussi la section **`store`** : le concept store BOUGE, dont ce site
est le prolongement en ligne. Elle alimente un bloc sur l'accueil et un
rappel dans le pied de page. **`store.url` est vide** : renseignez l'adresse
du site de la salle et le bouton « Découvrir la salle » apparaît. Laissée
vide, seule l'adresse postale est affichée — un lien mort vaudrait moins que
pas de lien.

## Brancher Stripe

1. Créer un compte sur [stripe.com](https://stripe.com) et récupérer les clés
   de test dans « Développeurs » → « Clés API ».
2. Les reporter dans `config/config.php`.
3. Créer le point de terminaison dans le tableau de bord Stripe :
   `https://<votre-domaine>/webhook/stripe`, en écoutant
   **`checkout.session.completed`**. Le secret `whsec_…` affiché va dans
   `stripe.webhook_secret`.

En local, le webhook n'est pas joignable depuis Internet ; utilisez la CLI
Stripe :

```bash
stripe listen --forward-to localhost:8000/webhook/stripe
```

### Pourquoi le webhook est indispensable

C'est **le seul endroit** où une commande passe à l'état « payée » et où le
stock est décompté. Le retour du navigateur sur la page de confirmation ne
prouve rien : il peut être rejoué, interrompu ou fabriqué. Sans webhook
configuré, les commandes resteront indéfiniment « en attente de paiement »,
même après un paiement réellement encaissé — et l'administration refusera de
les marquer payées à la main, pour la même raison.

Le traitement est idempotent : Stripe rejoue parfois un même événement, et le
stock ne doit être décompté qu'une fois.

### Tester un paiement

En mode test, utiliser la carte `4242 4242 4242 4242`, n'importe quelle date
future et n'importe quel cryptogramme.

## Mettre en ligne chez OVH

La marche à suivre complète, dans l'ordre. Comptez une heure la première
fois, l'essentiel étant de l'attente : propagation du domaine, émission du
certificat.

Les intitulés du panneau OVH changent au fil des refontes. Ceux donnés ici
décrivent la fonction plutôt que le bouton : si le libellé diffère, cherchez
l'intention.

### 0. Choisir l'offre

| Offre | Ce que ça change pour nous |
| --- | --- |
| **Perso** | Suffit : PHP 8, une base MySQL, un domaine. **Pas de SSH** — l'installation se fait alors par phpMyAdmin, décrite ci-dessous. |
| **Pro / Performance** | Ajoute le SSH, donc `php database/install.php` directement sur le serveur, et plusieurs bases. |

Le site tient sans difficulté sur une offre Perso : il n'y a ni compilation,
ni processus à faire tourner en continu.

### 1. La base de données

Panneau OVH → votre hébergement → onglet **Bases de données** → *Créer une
base de données*. Choisissez **MySQL**, le plus récent proposé, et notez les
quatre valeurs affichées.

> **Le piège numéro un :** chez OVH, le serveur de base n'est pas
> `localhost`. C'est une adresse du genre `bougexxxxx.mysql.db`. La recopier
> telle quelle, sinon rien ne se connectera.

### 2. Envoyer les fichiers

Par FTP, avec FileZilla et les identifiants de l'onglet **FTP - SSH**.

L'espace FTP contient un dossier `www` : c'est ce que le web sert. Deux
dispositions possibles.

**La bonne** — le domaine pointe sur `public/`, le reste du code n'est pas
servi du tout :

1. Déposez le projet dans un dossier **à côté** de `www`, par exemple
   `boutique/`, en excluant `.git`, `docs/` et `config/config.php`.
2. Panneau OVH → **Multisite** → ajoutez votre domaine, et renseignez le
   *dossier racine* : `boutique/public`.

**La dépannante**, si le multisite vous résiste : déposez tout le contenu du
projet directement dans `www`. Le fichier `.htaccess` fourni à la racine
redirige vers `public/` et refuse l'accès à `config/`, `src/`, `templates/`,
`database/`, `vendor/`, `bin/` et `docs/`.

### 3. La version de PHP

Panneau OVH → votre hébergement → **Modifier la version de PHP**, et
choisissez **8.1 ou plus récent**.

Si le réglage n'existe pas dans votre panneau, renommez `.ovhconfig.example`
en `.ovhconfig` et déposez-le à la racine de l'espace FTP — au même niveau
que `www`, pas dedans. En cas de souci après l'avoir ajouté, supprimez-le :
le site repart sur la configuration par défaut.

### 4. La configuration

Copiez `config/config.example.php` en `config/config.php` **sur le serveur**,
et remplissez-le avec les valeurs OVH :

```php
'db' => [
    'host'     => 'bougexxxxx.mysql.db',   // PAS localhost
    'name'     => 'bougexxxxx',
    'user'     => 'bougexxxxx',
    'password' => '...',                   // celui choisi à l'étape 1
    'port'     => '',
    'socket'   => '',
],

'site_url' => 'https://www.votre-domaine.fr',   // sans slash final
'debug'    => false,                            // jamais true en ligne
```

N'envoyez pas votre `config/config.php` local : il contient les identifiants
de votre Mac, qui ne valent rien ici.

### 5. Créer les tables

**Avec SSH** (offres Pro et plus) :

```bash
cd ~/boutique
php database/install.php contact@votre-domaine.fr "un mot de passe long"
```

**Sans SSH** (offre Perso), par phpMyAdmin, accessible depuis l'onglet Bases
de données :

1. Onglet **Importer** → choisissez `database/schema.sql` → *Exécuter*.
2. Calculez le haché de votre mot de passe **sur votre Mac** — il ne doit
   jamais circuler en clair :

   ```bash
   php -r 'echo password_hash("votre mot de passe", PASSWORD_DEFAULT), PHP_EOL;'
   ```

3. Onglet **SQL**, et collez, en remplaçant les deux valeurs :

   ```sql
   INSERT INTO admin_users (email, password_hash, name)
   VALUES ('contact@votre-domaine.fr', '<le haché obtenu>', 'Administration');
   ```

Le catalogue de démonstration, lui, n'a pas sa place en ligne : ses visuels
ne nous appartiennent pas. Ne lancez pas `seed-demo.php` sur le serveur.

### 6. Droits d'écriture sur les photos

Dans FileZilla, clic droit sur `public/uploads` → *Droits d'accès au
fichier* → `755`. C'est le seul dossier où le site écrit. Si l'envoi de
photos échoue depuis l'administration, essayez `775`.

### 7. Le certificat HTTPS

Panneau OVH → **Multisite** → votre domaine → activez le certificat SSL
(Let's Encrypt, gratuit). Comptez de quelques minutes à une heure.

Une fois qu'il répond, forcez le HTTPS : dans `public/.htaccess`,
décommentez les trois lignes du bloc « HTTPS obligatoire ». **Pas avant** :
sans certificat, la règle envoie les visiteurs vers une adresse muette.

### 8. Stripe

1. Tableau de bord Stripe, passez en mode **production** (l'interrupteur
   « Mode test » en haut).
2. Reportez les clés `sk_live_…` et `pk_live_…` dans `config/config.php`.
3. **Développeurs → Webhooks → Ajouter un point de terminaison** :
   `https://www.votre-domaine.fr/webhook/stripe`, événement
   `checkout.session.completed`. Reportez le secret `whsec_…`.

Sans ce webhook, les commandes payées resteront « en attente » et le stock
ne bougera pas. C'est le seul endroit où une commande devient payée.

### 9. Vérifier

- [ ] La boutique s'affiche, avec ses images et ses polices.
- [ ] L'adresse en `http://` bascule bien en `https://`.
- [ ] `/admin/connexion` accepte le compte créé.
- [ ] Une photo s'envoie depuis l'administration sans erreur.
- [ ] Un paiement de test aboutit **et** la commande passe à « Payée » —
      c'est ce qui prouve que le webhook fonctionne.
- [ ] `config/config.php` n'est pas lisible depuis un navigateur :
      `https://votre-domaine.fr/config/config.php` doit renvoyer une erreur.

### Mettre à jour le site ensuite

Il n'y a rien à compiler : on remplace les fichiers modifiés par FTP. En
pratique, `src/`, `templates/`, `public/assets/` et `vendor/` se remplacent
sans risque. **Ne touchez jamais** à `config/config.php` ni à
`public/uploads/` : le premier porte vos identifiants, le second vos photos.

Si une mise à jour ajoute une colonne, un fichier apparaît dans
`database/migrations/` : jouez-le par phpMyAdmin, onglet Importer. Les
migrations déjà passées ne se rejouent pas.

### Sauvegardes

Deux choses, et rien d'autre : **la base** (phpMyAdmin → Exporter) et
**`public/uploads/`** (copie FTP). OVH propose ses propres sauvegardes ;
vérifiez qu'elles couvrent bien les deux, et faites-en une à vous avant
chaque intervention.

## Chez un autre hébergeur

o2switch, Ionos, Hostinger, LWS : la marche à suivre est celle décrite pour
OVH, à trois nuances près.

- **L'hôte de la base** est souvent `localhost` ailleurs que chez OVH. La
  valeur exacte est toujours affichée à la création de la base ; c'est elle
  qui fait foi, jamais l'habitude.
- **La racine du domaine** se choisit librement chez o2switch et Hostinger :
  déposez le projet hors de l'espace web et pointez le domaine sur
  `public/`. C'est la disposition à préférer partout où elle est possible.
- **Le dossier web** s'appelle `public_html` plutôt que `www` chez beaucoup
  d'entre eux.

Tout le reste — PHP 8.1, `config/config.php`, l'import de `schema.sql`, les
droits sur `public/uploads/`, le certificat, le webhook Stripe — est
identique.

À propos de `public/uploads/` : il contient un `.htaccess` qui **interdit
l'exécution de code**. Si un fichier `.php` y parvenait malgré les contrôles
de type, il ne serait servi qu'en texte.

## Aperçu statique sur GitHub Pages

`docs/` contient une **photographie statique des pages publiques** : le HTML
réellement produit par le site, enregistré page par page, avec les liens
internes réécrits en fichiers `.html` voisins. GitHub Pages publie ce dossier
tel quel, à l'adresse <https://romaindufraiche.github.io/bouge_eshop/>.

À activer une fois, dans **Settings → Pages** du dépôt : *Source* = « Deploy
from a branch », *Branch* = `claude/bold-tesla-g5oa8g`, dossier `/docs`. La
mise en ligne prend une minute ou deux.

Ce qu'on y voit : l'accueil, le catalogue, les cinq catégories, les quatorze
fiches produits, le panier vide et les pages légales — le design, les polices
et les visuels réels. Ce qu'on n'y voit pas : **l'ajout au panier, le paiement
et l'administration**, qui ont besoin de PHP et d'une base de données. Un
bandeau le rappelle en haut de chaque page.

C'est donc une vitrine, pas la boutique : pour la vraie, voir
[Mise en ligne sur un hébergement mutualisé](#mise-en-ligne-sur-un-hébergement-mutualisé).

### Le régénérer après une modification

```bash
php database/seed.php                          # le catalogue de l'aperçu
php -S localhost:8000 -t public dev-server.php &
php bin/apercu.php                             # réécrit docs/
```

## Le catalogue de démonstration

Avant que le vrai catalogue n'existe, des formes abstraites ne donnent aucune
idée du rendu. `database/seed-demo.php` importe donc une soixantaine de
produits réels — lunettes, bonnets, accessoires, sacs, maillots — avec leurs
visuels, leurs prix, leurs coloris et leurs tailles, depuis le site d'arena.

```bash
php database/seed-demo.php            # importe (en remplaçant l'import précédent)
php database/seed-demo.php --purger   # les retire tous
```

**Ces produits ne sont pas à vous, et ne doivent pas être mis en vente.** Les
visuels et les intitulés appartiennent à arena. Trois garde-fous sont en
place pour que cela ne parte pas en production par inadvertance :

| | |
| --- | --- |
| **Marqués en base** | La colonne `demo_source` porte le nom de la source, et une pastille « Démo » apparaît dans la liste des produits de l'administration. |
| **Hors du dépôt** | Les visuels sont téléchargés dans `public/uploads/demo/`, que Git ignore : ils ne partent ni dans le dépôt, ni chez un autre développeur. |
| **Hors de l'aperçu public** | `bin/apercu.php` refuse de construire l'aperçu tant que des produits de démonstration sont en base, et dit comment les retirer. |

Le jour où le client fournit son catalogue : `--purger`, et il ne reste rien.

## Structure du site

L'arborescence reprend celle des marques de natation (Speedo, Arena), adaptée
à un catalogue de quelques dizaines d'articles : **trois portes d'entrée vers
les mêmes produits**, chacune avec sa propre adresse.

| Entrée | Adresse | À quoi elle répond |
| --- | --- | --- |
| Par catégorie | `/boutique/{categorie}` | « Il me faut un bonnet » |
| Par usage | `/usage/{usage}` | « Je viens pour m'entraîner » |
| Par raccourci | `/boutique/selection/{nouveautes\|promotions\|en-magasin}` | « Montrez-moi ce qui est nouveau » |
| Par recherche | `/recherche?q=…` | « Je cherche des lunettes junior » |

Les filtres se cumulent en paramètres d'URL — `/usage/competition?categorie=lunettes&tri=prix-asc`
— si bien qu'une sélection se partage, se met en favori et se recharge telle
quelle. Tout se fait en GET, sans JavaScript.

### Les usages

C'est l'axe que les deux marques mettent en avant, parce qu'un nageur sait
d'abord ce qu'il vient faire : **Entraînement, Compétition, Loisir et
bien-être, Eau libre et triathlon, Apprentissage**. Un produit peut en servir
plusieurs ; les cases se cochent sur sa fiche dans l'administration.

Les valeurs autorisées sont dans `src/Support/Usage.php` — en ajouter un
revient à ajouter une ligne, sans toucher à la base : la colonne `usages`
stocke la liste entre virgules.

### Ce qui n'a pas été repris

Speedo et Arena organisent d'abord par **Femme / Homme / Enfant**. Avec une
quinzaine d'articles, chacune de ces pages en contiendrait un ou deux : un
rayon vide donne l'impression d'une boutique vide. L'axe reste disponible le
jour où le catalogue le justifie — le genre deviendrait alors un filtre de
plus, sur le modèle des usages.

Leurs pages de gammes (Fastskin, Biofuse…) supposent de même des familles de
produits qui n'existent pas encore ici.

## Le compte client

Facultatif, et jamais imposé : on peut commander sans, et c'est le chemin par
défaut. Le compte sert à trois choses concrètes.

| | |
| --- | --- |
| **Retrouver son panier** | Il est enregistré sur le compte à chaque modification. On le remplit sur son téléphone, on le retrouve sur son ordinateur. |
| **Ne plus retaper son adresse** | Le tunnel de commande est prérempli, et l'adresse utilisée est mémorisée après chaque commande livrée. |
| **Suivre ses commandes** | Historique, avancement en trois jalons, et le numéro de suivi du colis dès que la boutique le saisit. |

À l'inscription, les commandes déjà passées avec la même adresse
électronique sont rattachées au compte : l'historique ne commence pas vide.

Côté sécurité : mots de passe hachés (`password_hash`, algorithme par défaut
de PHP), identifiant de session régénéré à la connexion, jeton CSRF sur
chaque formulaire, et une temporisation de cinq minutes après cinq tentatives
ratées. Une commande n'est lisible que par le compte auquel elle appartient —
le filtre est dans la requête SQL, pas dans l'affichage.

La session client est **distincte de celle de l'administration** : elles ne
partagent aucune clé, et être connecté d'un côté n'ouvre rien de l'autre.

### Le suivi de livraison

Dans l'administration, la fiche d'une commande livrée porte deux champs :
transporteur et numéro de suivi. Renseignés, ils apparaissent aussitôt dans
l'espace du client, avec la date d'expédition. Le site ne consulte pas le
transporteur : il montre le numéro, à reporter sur le site de celui-ci.

## Utiliser l'administration

**L'adresse est `/admin`** — par exemple `https://votre-domaine.fr/admin`.
Sans session ouverte, elle renvoie vers `/admin/connexion`.

Les identifiants sont ceux donnés au script d'installation :

```bash
php database/install.php contact@votre-domaine.fr "un mot de passe long"
```

En développement, `php database/seed.php` crée le compte
`contact@bouge.fr` avec le mot de passe `bouge-dev-2026`. **À ne jamais
laisser en ligne** : rejouez `install.php` avec vos propres identifiants
avant l'ouverture.

Pour changer le mot de passe ensuite, relancez `install.php`… mais il recrée
les tables. Sur une boutique en service, passez plutôt par une requête, le
hachage étant calculé sur votre poste :

```bash
php -r 'echo password_hash("nouveau mot de passe", PASSWORD_DEFAULT), PHP_EOL;'
```

```sql
UPDATE admin_users SET password_hash = '<le hachage obtenu>' WHERE email = 'contact@votre-domaine.fr';
```

Une fois connecté :

- **Tableau de bord** — commandes à préparer, total encaissé, stocks faibles.
- **Produits** — recherche, filtre par statut, tri. Le formulaire couvre le
  nom, la description, la catégorie, le prix, une promotion avec dates de
  validité, le stock, les déclinaisons taille/couleur et le référencement.
- **Usages** — à quoi sert le produit : entraînement, compétition, loisir,
  eau libre, apprentissage. Ces cases décident des pages sur lesquelles il
  apparaît et du filtre du catalogue.
- **Photos** — envoi multiple, ordre réglé par « Avancer » et « Reculer »,
  texte alternatif modifiable, suppression. La première photo de la liste est
  celle qui s'affiche dans le catalogue.
- **Vente et disponibilité** — trois réglages par produit : le mettre en avant
  en haut de l'accueil, signaler qu'il est disponible en magasin, ou indiquer
  qu'il est vendu par un revendeur. Dans ce dernier cas, la fiche remplace
  « Ajouter au panier » par un lien vers le revendeur, n'affiche aucun prix —
  c'est celui du revendeur qui fait foi — et le serveur refuse de mettre le
  produit au panier, même si la requête est forgée à la main.
- **Catégories** — création et renommage. Une catégorie qui contient encore
  des produits ne peut pas être supprimée : il faut d'abord les déplacer.
- **Points de retrait** — les adresses proposées au client qui vient chercher
  sa commande. Sans point actif, seule la livraison est possible. Un point
  rattaché à des commandes ne peut pas être supprimé, seulement retiré du
  tunnel : les clients concernés doivent continuer à lire l'adresse.
- **Commandes** — filtres par statut et par mode de remise, détail complet,
  changement de statut et note interne.

Quelques principes de fonctionnement utiles à connaître :

- Un nouveau produit part toujours en **brouillon**. Il n'apparaît sur la
  boutique qu'une fois passé « En ligne ».
- Changer le statut d'une commande **ne prévient pas le client** et ne modifie
  pas le stock.
- Le passage à « Payée » n'est jamais manuel : il vient de Stripe.
- Supprimer un produit ne touche pas aux commandes déjà passées : elles
  gardent le nom et le prix pratiqués au moment de l'achat.
- Les suppressions demandent toujours une confirmation, en deux temps.

## Direction artistique

Le site applique la charte officielle
(`BOUGE._BRANDGUIDELINESHD.pdf`, version 1.0, septembre 2026).

### Couleurs

| Rôle | Nom | Hex |
| --- | --- | --- |
| Primaire | Noir anthracite | `#232323` |
| Primaire | Crème | `#fffbe8` |
| Primaire | Orange vif | `#e26129` |
| Secondaire | Vert jade | `#439677` |
| Secondaire | Brun café | `#59443a` |
| Secondaire | Bleu ciel | `#69acde` |
| Secondaire | Blanc | `#ffffff` |

Trois valeurs dérivées complètent la palette pour des besoins d'interface
qu'un livret de marque ne couvre pas : `--sand` et `--line` (du crème mêlé de
brun café, pour les surfaces et les filets) et `--accent-deep` (`#a8441b`).

Cette dernière mérite une explication : **l'orange vif de la charte plafonne à
3,4:1 sur le crème**, ce qui suffit pour un aplat ou un contour mais pas pour
du texte courant, où le niveau AA exige 4,5:1. Le ton assombri atteint 5,7:1
en gardant la teinte de la marque. L'orange vif reste donc utilisé pour les
aplats décoratifs, et sa variante assombrie dès qu'il y a du texte.

Tout est déclaré en jetons CSS en tête de `public/assets/css/site.css` : aucun
composant ne code une couleur en dur.

### Typographies

| Usage | Police | Fichier |
| --- | --- | --- |
| Titres | Sun Motter | `public/assets/fonts/SunMotter.woff2` |
| Sous-titres et corps | Manrope | `Manrope-400/600/700.woff2` |
| Notes manuscrites | Reenie Beanie | `ReenieBeanie-400.woff2` |

Les trois sont **hébergées avec le site**. Aucune requête n'est faite vers
Google Fonts : c'est plus rapide, et cela évite de transmettre l'adresse IP
des visiteurs à un tiers — un point régulièrement reproché au RGPD.

> **Particularité de Sun Motter, à connaître avant d'écrire du CSS :** dans
> cette police, les minuscules accentuées pointent vers le glyphe NON accentué
> — « é » est dessiné comme « e ». Les capitales accentuées, elles, ont bien le
> leur. Les titres de la boutique sont donc passés en majuscules par CSS
> (`text-transform: uppercase`), sans quoi « matériel » s'afficherait
> « materiel ». La police ne dessinant de toute façon que des capitales, le
> rendu est identique, accents en plus.

La police d'affichage est réservée à la boutique (`body.boutique`).
L'administration garde Manrope pour ses titres : elle est dense et lue de
près, une police d'affichage grasse y nuirait à la lecture.

### Logo et visuels

Les visuels du livre *Corps et esprit* sont dans `public/assets/images/livre/`
(couverture, neuf doubles pages, portrait de l'auteur), redimensionnés et
recompressés pour le web depuis la page de l'éditeur.

Les fichiers de marque sont dans `public/assets/brand/`, redimensionnés depuis les
originaux de la charte (jusqu'à 25 000 px de large) :

- `wordmark-anthracite.png` / `wordmark-creme.png` — en-tête et pied de page
- `logo-complet-*.png` — logo, mascotte et baseline réunis
- `tampon-*.png`, `monogramme-*.png` — sceau et monogramme
- `mascotte-*.png` — la grenouille, dans ses trois poses

Le favicon (`icone-512.png`) et l'icône iOS (`icone-apple.png`) reprennent le
monogramme : le tampon complet, avec son texte circulaire, est illisible à
32 px.

### Formes

Le logo est très arrondi et le ton de la marque est chaleureux : boutons,
filtres et pastilles reprennent cette rondeur, comme les stickers de
l'identité. Tout tient dans un jeton — passer `--radius-control` de `9999px` à
`6px` suffit pour une allure anguleuse.

### Le titre de l'accueil

`templates/boutique/home.php`, une seule ligne. Il est aujourd'hui à
l'impératif, comme le nom de la marque : **« Nagez. Le matériel suivra. »** —
le client fait sa part, la boutique fait la sienne.

Trois autres formulations du même registre, si celle-ci lasse :

| | |
| --- | --- |
| `Le matériel qui ne lâche pas avant vous.` | La promesse d'endurance, avec un clin d'œil. Tient sur quatre lignes. |
| `Le matériel qui suit, séance après séance.` | Plus sage, plus proche de l'ancienne formule. |
| `Tout pour avoir envie d'y retourner.` | Le motif plutôt que l'objet — met le geste en avant, pas le produit. |

La coupure est forcée après la première phrase : laissé libre, le titre
coupait après « le », ce qui laisse un article seul en bout de ligne. En
changeant de formule, vérifiez où il casse — la police d'affichage est large
et la colonne étroite.

### Ton

La charte décrit une voix « chaleureuse, motivante, avec une pointe d'humour,
sans jamais être agressive ou corporate », identique pour tous les âges. Les
textes du site suivent cette ligne, en restant concrets et sans superlatif.

## Organisation du code

```
config/
  config.example.php     Modèle de configuration à copier
  shop.php               Réglages commerciaux (frais de port, contact…)
database/
  schema.sql             Structure des 8 tables, commentée
  install.php            Installation : tables + compte admin
  seed.php               Catalogue de démonstration
public/                  ← seule racine exposée au web
  index.php              Contrôleur frontal : tout passe par lui
  .htaccess              Réécriture d'URL, compression, cache
  assets/                CSS, polices, visuels de marque
  uploads/               Photos envoyées depuis l'administration
src/
  Controller/            Boutique, panier, commande, webhook
  Controller/Admin/      Écrans protégés
  Repository/            Requêtes SQL, une classe par table
  Support/               Routeur, vues, session, panier, prix, Stripe…
  routes.php             Table des routes
templates/
  layout/                shop, admin, blank
  boutique/              Pages publiques
  admin/                 Écrans d'administration
  partials/              Fragments réutilisés (prix, vignette, panier…)
bin/
  apercu.php             Génère l'aperçu statique dans docs/
docs/                    Aperçu statique publié par GitHub Pages
vendor/                  Bibliothèque Stripe (versionnée, voir plus haut)
dev-server.php           Routeur du serveur PHP intégré, développement seul
```

## Choix techniques

Quelques conventions qui expliquent le reste du code.

- **Les montants sont des entiers en centimes**, partout. Aucun calcul en
  virgule flottante, et c'est déjà l'unité attendue par Stripe.
- **Le navigateur ne transmet jamais de prix.** Le panier en session ne
  contient que des identifiants et des quantités ; libellés, prix, promotions
  et stocks sont relus en base à chaque affichage et avant chaque paiement.
- **Toutes les requêtes sont préparées**, avec `ATTR_EMULATE_PREPARES` à
  `false` : les valeurs ne sont jamais concaténées dans le SQL. Les rares
  fragments variables (le tri d'une liste) viennent d'une liste fermée.
- **Tout ce qui s'affiche passe par `e()`**, la fonction d'échappement HTML.
  Un nom de produit contenant du HTML est affiché, jamais interprété.
- **Chaque formulaire POST porte un jeton CSRF**, comparé en temps constant.
  Chaque écran et chaque action de l'administration appellent `Auth::require()`
  en première ligne : une action déclenchée directement ne passe pas.
- **Le type des photos est déterminé à partir de leur contenu**, pas de
  l'en-tête envoyé par le navigateur, et leur nom est réécrit en aléatoire.
- **Le site fonctionne sans JavaScript.** Menu mobile en case à cocher,
  galerie par liens `?photo=N`, choix livraison/retrait piloté par le CSS
  `:has()`, confirmations de suppression en `<details>`. Rien à charger, rien
  qui casse.
- **Les statuts sont des `VARCHAR`, pas des `ENUM`**, et leurs valeurs vivent
  dans `src/Support/Status.php` : ajouter un statut ne demande pas de modifier
  la structure d'une table.

### Pourquoi PHP plutôt que Node.js

Le site a besoin d'un serveur : sessions, paiement, webhook, administration.
Un export statique (GitHub Pages, Netlify sans fonctions) est donc exclu,
quelle que soit la technologie.

Restait le choix de la plateforme. PHP et MySQL tournent sur l'offre à 3 €
par mois de n'importe quel hébergeur français, avec phpMyAdmin pour la base et
un accès FTP pour les fichiers ; il n'y a ni build, ni dépendances à
installer, ni version de Node à maintenir. C'est ce qui a été retenu.

## Points à traiter avant l'ouverture

- [ ] **Mentions légales et CGV** : ce sont des gabarits, pas des documents
      juridiques validés. Les champs entre crochets sont à remplir et le tout
      à faire relire.
- [ ] **Photos produits** : les visuels de démonstration sont des formes
      abstraites aux couleurs de la marque, à remplacer par de vraies photos.
      Si `seed-demo.php` a été lancé, **purgez-le avant l'ouverture** : ces
      visuels appartiennent à arena.
      Seul le livre *Corps et esprit* a ses vrais visuels (couverture, doubles
      pages, portrait), repris de la page de son éditeur.
- [ ] **Licence de Sun Motter** : la police est livrée avec la charte et
      hébergée avec le site, donc téléchargeable par n'importe quel visiteur.
      Vérifier que la licence l'autorise pour un usage web avant la mise en
      ligne.
- [ ] **Grille tarifaire de livraison** : vérifier les montants de
      `config/shop.php`.
- [ ] **Points de retrait** : l'adresse en place est celle du jeu de
      démonstration.
- [ ] **Courriels de confirmation** : la boutique n'en envoie aucun. Activer
      les reçus Stripe (« Paramètres » → « Reçus par e-mail ») couvre le
      justificatif de paiement ; la confirmation de commande reste à écrire.
- [ ] **Adresse du site de la salle** : `store.url` dans `config/shop.php`
      est vide, faute de connaître l'adresse officielle. Le bloc « Avant
      d'être une boutique » s'affiche sans bouton tant qu'elle manque.
- [ ] **Courriel de bienvenue et mot de passe oublié** : la création de
      compte n'envoie aucun courriel, et il n'y a pas encore de procédure de
      réinitialisation — un mot de passe perdu se change aujourd'hui en base.
- [ ] **Certificat HTTPS** : indispensable au paiement. Tous les hébergeurs
      proposent Let's Encrypt gratuitement, souvent en une case à cocher.
