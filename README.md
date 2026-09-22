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
- [Configuration](#configuration)
- [Brancher Stripe](#brancher-stripe)
- [Mise en ligne sur un hébergement mutualisé](#mise-en-ligne-sur-un-hébergement-mutualisé)
- [Aperçu statique sur GitHub Pages](#aperçu-statique-sur-github-pages)
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

### Les deux scripts de base

| Script | Ce qu'il fait |
| --- | --- |
| `php database/install.php <email> <mot-de-passe>` | **Crée les tables** à partir de `database/schema.sql`, puis le compte d'administration. Le catalogue reste vide. C'est le script d'une vraie installation. |
| `php database/seed.php [email] [mot-de-passe]` | Remplit un catalogue de démonstration (5 catégories, 14 produits). Les tables doivent déjà exister. |

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

## Mise en ligne sur un hébergement mutualisé

Testé dans l'esprit des offres courantes : o2switch, OVH, Ionos, Hostinger,
LWS. Aucune n'a besoin d'un accès SSH, sauf pour lancer le script
d'installation — et une solution sans SSH est décrite plus bas.

### 1. Créer la base de données

Dans l'espace client de l'hébergeur, rubrique « Bases de données » : créer une
base **en utf8mb4** et un utilisateur. Noter les quatre valeurs (hôte, nom,
utilisateur, mot de passe).

### 2. Transférer les fichiers

En FTP (FileZilla) ou par le gestionnaire de fichiers de l'hébergeur.

**Le mieux**, si l'hébergeur permet de choisir la racine du domaine (c'est le
cas chez o2switch et Hostinger) : déposer tout le projet en dehors de l'espace
web, puis faire pointer le domaine sur le dossier `public/`. Le code de la
boutique, la configuration et les gabarits restent alors hors de portée du web.

**Sinon** : déposer tout le projet dans `www/` (ou `public_html/`). Le fichier
`.htaccess` à la racine du projet redirige vers `public/` et refuse l'accès
direct à `config/`, `src/`, `templates/`, `database/` et `vendor/`.

### 3. Renseigner la configuration

Copier `config/config.example.php` en `config/config.php`, y mettre les
identifiants de la base, l'adresse réelle du site, les clés Stripe **de
production**, et `'debug' => false`.

### 4. Créer les tables et le compte d'administration

Avec un accès SSH :

```bash
php database/install.php contact@votre-domaine.fr "un mot de passe long"
```

Sans SSH : importer `database/schema.sql` depuis phpMyAdmin (onglet
« Importer »), puis créer le compte avec une requête SQL, le mot de passe
étant haché **sur votre poste** — il ne doit jamais circuler en clair :

```bash
php -r 'echo password_hash("votre-mot-de-passe", PASSWORD_DEFAULT), PHP_EOL;'
```

```sql
INSERT INTO admin_users (email, password_hash, name)
VALUES ('contact@votre-domaine.fr', '<le hachage obtenu>', 'Administration');
```

### 5. Droits du dossier des photos

`public/uploads/` doit être accessible en écriture par PHP (permissions `755`,
ou `775` selon l'hébergeur). C'est le seul dossier dans ce cas.

Il contient un `.htaccess` qui **interdit l'exécution de code** : si un fichier
`.php` y parvenait malgré les contrôles de type, il ne serait servi qu'en
texte.

### 6. Vérifier

- La boutique s'affiche, avec ses images et sa feuille de style.
- `/admin/connexion` accepte le compte créé.
- Un paiement de test aboutit et la commande apparaît dans l'administration
  au statut « Payée » — c'est ce qui prouve que le webhook fonctionne.

### Sauvegardes

Deux choses à sauvegarder, et rien d'autre :

1. **La base** (export SQL depuis phpMyAdmin) — le catalogue et les commandes.
2. **`public/uploads/`** — les photos des produits.

La plupart des hébergeurs proposent une sauvegarde automatique ; vérifier
qu'elle couvre bien les deux.

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

## Utiliser l'administration

`/admin`, accessible après connexion.

- **Tableau de bord** — commandes à préparer, total encaissé, stocks faibles.
- **Produits** — recherche, filtre par statut, tri. Le formulaire couvre le
  nom, la description, la catégorie, le prix, une promotion avec dates de
  validité, le stock, les déclinaisons taille/couleur et le référencement.
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
- [ ] **Certificat HTTPS** : indispensable au paiement. Tous les hébergeurs
      proposent Let's Encrypt gratuitement, souvent en une case à cocher.
