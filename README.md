# BOUGE. — boutique en ligne

Site e-commerce de matériel de natation : bonnets, lunettes, accessoires et
vêtements. Vente en ligne avec livraison en France ou retrait sur place, et
une interface d'administration prévue pour être utilisée sans compétence
technique.

## Sommaire

- [Démarrer en local](#démarrer-en-local)
- [Configuration](#configuration)
- [Brancher Stripe](#brancher-stripe)
- [Utiliser l'administration](#utiliser-ladministration)
- [Organisation du code](#organisation-du-code)
- [Mise en ligne sur Vercel](#mise-en-ligne-sur-vercel)
- [Points à traiter avant l'ouverture](#points-à-traiter-avant-louverture)

## Démarrer en local

Il faut Node.js 20 ou plus récent.

```bash
npm install
cp .env.example .env        # puis renseigner les valeurs, voir ci-dessous
npx prisma db push          # crée la base SQLite prisma/dev.db
npm run db:seed             # compte admin + catalogue de démonstration
npm run dev
```

La boutique répond sur http://localhost:3000 et l'administration sur
http://localhost:3000/admin.

### Commandes disponibles

| Commande | Effet |
| --- | --- |
| `npm run dev` | Serveur de développement |
| `npm run build` | Génère le client Prisma puis compile le site |
| `npm run start` | Sert la version compilée |
| `npm run lint` | Analyse statique du code |
| `npm run db:push` | Applique le schéma Prisma à la base |
| `npm run db:seed` | (Re)crée le catalogue de démonstration |
| `npm run db:studio` | Explorateur de base de données |
| `npm run db:reset` | Réinitialise complètement la base |

> `db:seed` vide et recrée le catalogue. Les **commandes ne sont jamais
> touchées** : elles conservent les libellés et les prix recopiés au moment de
> l'achat.

## Configuration

Toutes les variables sont décrites dans `.env.example`. Les essentielles :

| Variable | Rôle |
| --- | --- |
| `DATABASE_URL` | SQLite en développement, PostgreSQL en production |
| `NEXT_PUBLIC_SITE_URL` | URL publique, utilisée par Stripe et le référencement |
| `AUTH_SECRET` | Signe le cookie de session admin — 32 caractères minimum |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | Compte créé par `npm run db:seed` |
| `STRIPE_SECRET_KEY` | Clé secrète Stripe |
| `NEXT_PUBLIC_STRIPE_PUBLISHABLE_KEY` | Clé publique Stripe |
| `STRIPE_WEBHOOK_SECRET` | Secret de signature du webhook |
| `UPLOAD_DRIVER` | `local` (défaut) ou un service externe |

Générer un secret d'authentification :

```bash
openssl rand -base64 32
```

### Réglages commerciaux

Les frais de port, le seuil de livraison offerte et les coordonnées de la
boutique ne sont pas en base : ils vivent dans `src/lib/shop-config.ts`.
Modifier une valeur suffit, aucune migration n'est nécessaire.

```ts
export const SHIPPING = {
  flatRateCents: 490,    // 4,90 € de frais de port
  freeAboveCents: 6000,  // offerts à partir de 60 € — mettre null pour désactiver
  pickupCents: 0,        // le retrait est toujours gratuit
};
```

> Ces montants sont une **hypothèse de départ** : le cahier des charges ne
> fixait pas de grille tarifaire.

## Brancher Stripe

Le paiement est un vrai paiement Stripe Checkout, pas une simulation.

1. Récupérer les clés de test sur
   https://dashboard.stripe.com/test/apikeys et les mettre dans `.env`.
2. En local, relayer les événements vers l'application :

   ```bash
   stripe listen --forward-to localhost:3000/api/stripe/webhook
   ```

   La commande affiche un secret `whsec_…` à reporter dans
   `STRIPE_WEBHOOK_SECRET`.
3. En production, créer le point de terminaison dans le tableau de bord
   Stripe : `https://<votre-domaine>/api/stripe/webhook`, en écoutant
   `checkout.session.completed`.

### Pourquoi le webhook est indispensable

C'est **le seul endroit** où une commande passe à l'état « payée » et où le
stock est décompté. Le retour du navigateur sur la page de confirmation ne
prouve rien : il peut être rejoué, interrompu ou fabriqué. Sans webhook
configuré, les commandes resteront indéfiniment « en attente de paiement »,
même après un paiement réellement encaissé.

Le traitement est idempotent : Stripe rejoue parfois un même événement, et le
stock ne doit être décompté qu'une fois.

### Tester un paiement

En mode test, utiliser la carte `4242 4242 4242 4242`, n'importe quelle date
future et n'importe quel cryptogramme.

## Utiliser l'administration

`/admin`, accessible après connexion avec le compte créé au `db:seed`.

- **Tableau de bord** — commandes à traiter, stocks faibles, total encaissé.
- **Produits** — recherche, filtre par statut, tri. Le formulaire couvre le
  nom, la description, la catégorie, le prix, une promotion avec dates de
  validité, le stock, les déclinaisons taille/couleur et le référencement.
- **Photos** — envoi multiple, ordre réglé par les flèches ↑ ↓, texte
  alternatif modifiable, suppression. La première photo de la liste est celle
  qui s'affiche dans le catalogue.
- **Catégories** — création et renommage. Une catégorie qui contient encore
  des produits ne peut pas être supprimée : il faut d'abord les déplacer.
- **Commandes** — filtres par statut et par mode de remise, détail complet,
  changement de statut et note interne.

Quelques principes de fonctionnement utiles à connaître :

- Un nouveau produit part toujours en **brouillon**. Il n'apparaît sur la
  boutique qu'une fois passé « En ligne ».
- Changer le statut d'une commande **ne prévient pas le client** et ne
  modifie pas le stock.
- Supprimer un produit ne touche pas aux commandes déjà passées : elles
  gardent le nom et le prix pratiqués au moment de l'achat.

## Organisation du code

```
prisma/
  schema.prisma          Modèle de données, commenté
  seed.ts                Catalogue de démonstration
src/
  app/
    (boutique)/          Pages publiques — en-tête, panier, pied de page
    admin/
      connexion/         Formulaire de connexion, hors habillage admin
      (protege)/         Écrans protégés : produits, catégories, commandes
    api/
      panier/            Chiffrage du panier côté serveur
      stripe/webhook/    Confirmation de paiement
  components/            Composants réutilisables (boutique, admin, UI)
  lib/                   Accès aux données, prix, panier, authentification
  middleware.ts          Protection de /admin
```

Quelques conventions qui expliquent le reste :

- **Les montants sont des entiers en centimes**, partout. Aucun calcul en
  virgule flottante, et c'est déjà l'unité attendue par Stripe.
- **Le navigateur ne transmet jamais de prix.** Le panier ne contient que des
  identifiants et des quantités ; libellés, prix, promotions et stocks sont
  relus en base à chaque affichage et avant chaque paiement.
- **Les modules serveur sont marqués `server-only`.** Les importer depuis un
  composant client échoue explicitement plutôt que de faire échouer la
  compilation sur un message obscur.
- **La direction artistique tient dans `src/app/globals.css`.** Couleurs,
  typographies et rayons sont des jetons ; aucun composant ne code une valeur
  en dur.

### Rendu et fraîcheur des pages

Les pages publiques sont statiques et régénérées au maximum toutes les cinq
minutes, ce qui couvre l'ouverture et la fermeture automatiques des
promotions datées. L'administration déclenche en plus une régénération
immédiate à chaque enregistrement : un prix modifié est visible tout de suite.

## Mise en ligne sur Vercel

### 1. Passer sur PostgreSQL

SQLite écrit dans un fichier ; l'hébergement de Vercel n'a pas de disque
persistant. Il faut une base PostgreSQL (Vercel Postgres, Neon, Supabase…).

Dans `prisma/schema.prisma` :

```prisma
datasource db {
  provider = "postgresql"   // au lieu de "sqlite"
  url      = env("DATABASE_URL")
}
```

Le reste du schéma n'a pas besoin d'être touché : il n'utilise volontairement
aucune fonctionnalité absente de SQLite (pas d'`enum`, pas de liste de
scalaires), ce qui lui permet de fonctionner sur les deux moteurs.

Puis créer les tables :

```bash
npx prisma migrate deploy
```

### 2. Renseigner les variables d'environnement

Toutes celles du `.env`, avec les clés Stripe **de production** et
`NEXT_PUBLIC_SITE_URL` pointant sur le domaine réel.

### 3. Créer le compte administrateur

`npm run db:seed` crée le compte mais recrée aussi le catalogue de
démonstration : à n'utiliser qu'au tout premier déploiement.

### 4. Basculer le stockage des photos

Avec `UPLOAD_DRIVER=local`, les photos envoyées depuis l'admin sont écrites
dans `public/uploads` et **disparaissent à chaque déploiement**. Pour la
production, implémenter le service choisi dans `src/lib/storage.ts` : les deux
fonctions `saveUploadedImage` et `deleteStoredImage` sont les seuls points à
écrire, le reste de l'application n'a pas à changer. Penser à ajouter le
domaine du service dans `images.remotePatterns` (`next.config.ts`).

## Points à traiter avant l'ouverture

- [ ] **Mentions légales et CGV** : ce sont des gabarits, pas des documents
      juridiques validés. Les champs entre crochets sont à remplir et le tout
      à faire relire.
- [ ] **Direction artistique** : couleurs, typographies et visuels sont
      provisoires, en attente des fichiers de la charte BOUGE.
- [ ] **Photos produits** : les visuels de démonstration sont des formes
      abstraites, à remplacer par de vraies photos.
- [ ] **Grille tarifaire de livraison** : vérifier les montants de
      `src/lib/shop-config.ts`.
- [ ] **Point de retrait** : l'adresse est celle du jeu de démonstration.
- [ ] **Courriels de confirmation** : aucun courriel n'est envoyé pour
      l'instant, ni au client ni à la boutique.
- [ ] **Stockage externe des photos**, si l'admin doit servir à ajouter des
      produits après la mise en ligne.

### Avertissement de sécurité connu

`npm audit` signale des vulnérabilités dans `deepmerge-ts`, une dépendance
transitive du **CLI Prisma**. Ce paquet est une `devDependency` : il sert à
générer le client et à appliquer les migrations, et n'est jamais exécuté par
le site déployé. La seule correction proposée par npm est un retour à une
version antérieure de Prisma.
