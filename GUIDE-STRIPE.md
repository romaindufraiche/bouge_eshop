# Brancher Stripe, pas à pas

Ce guide part de zéro : aucun compte, aucune clé. À la fin, un client peut
payer sur la boutique, et la commande arrive dans l'administration avec le
stock décompté.

Comptez **une heure** pour la partie technique, puis **un à trois jours
ouvrés** de délai côté Stripe pour la vérification du compte. Les deux ne se
suivent pas : on branche et on teste tout de suite en mode test, la
vérification se fait en parallèle.

> **Les libellés du tableau de bord bougent.** Stripe refond son interface
> régulièrement — les « Webhooks » ont déménagé des « Développeurs » vers
> « Workbench ». Les adresses directes données ici (`dashboard.stripe.com/…`)
> sont stables, elles ; en cas de doute, utilisez-les plutôt que de chercher
> dans les menus.

---

## Sommaire

1. [Ce que Stripe fait, et ce qu'il ne fait pas](#1-ce-que-stripe-fait-et-ce-quil-ne-fait-pas)
2. [Ce qu'il faut avoir sous la main](#2-ce-quil-faut-avoir-sous-la-main)
3. [Créer le compte](#3-créer-le-compte)
4. [Récupérer les clés de test](#4-récupérer-les-clés-de-test)
5. [Les coller dans la boutique](#5-les-coller-dans-la-boutique)
6. [Le webhook, en local](#6-le-webhook-en-local)
7. [Un paiement d'essai de bout en bout](#7-un-paiement-dessai-de-bout-en-bout)
8. [Activer le compte pour de vrai](#8-activer-le-compte-pour-de-vrai)
9. [Passer en production](#9-passer-en-production)
10. [Le webhook de production](#10-le-webhook-de-production)
11. [La vérification finale : un vrai paiement](#11-la-vérification-finale--un-vrai-paiement)
12. [Les réglages à ne pas oublier](#12-les-réglages-à-ne-pas-oublier)
13. [Quand ça ne marche pas](#13-quand-ça-ne-marche-pas)
14. [Ce que ça coûte](#14-ce-que-ça-coûte)

---

## 1. Ce que Stripe fait, et ce qu'il ne fait pas

**Ce qu'il fait :** il affiche la page de paiement, encaisse la carte, se
charge du 3-D Secure et de la conformité bancaire, et vire l'argent sur votre
compte. La boutique ne voit jamais un numéro de carte — c'est tout l'intérêt.

**Ce qu'il ne fait pas :** il ne sait rien du colis. Ni le poids, ni le point
relais, ni l'étiquette. Ça, c'est Boxtal, et c'est l'objet de l'autre guide.

---

## 2. Ce qu'il faut avoir sous la main

Pour la partie test, rien. Pour l'activation du compte, Stripe demandera :

- le **SIRET** de la structure (ou votre numéro d'auto-entrepreneur) ;
- l'**IBAN** du compte qui recevra les virements ;
- une **pièce d'identité** du dirigeant, photographiée recto-verso ;
- l'**adresse** de l'établissement ;
- une description de l'activité et l'adresse du site.

Rassemblez-les avant de commencer l'étape 8 : le formulaire n'aime pas être
laissé à moitié rempli.

---

## 3. Créer le compte

1. Allez sur **[stripe.com](https://stripe.com)**, « Démarrer maintenant ».
2. Adresse électronique, nom, mot de passe, pays **France**.
3. Validez l'adresse électronique depuis le message reçu.
4. Activez l'**authentification à deux facteurs** quand Stripe le propose.
   Ce compte donne accès à votre argent : ce n'est pas le moment de gagner
   trente secondes.

Vous arrivez sur le tableau de bord. En haut, un interrupteur **« Mode
test »** (ou « Sandbox »). Laissez-le **activé** : tout ce qui suit, jusqu'à
l'étape 9, se fait en mode test. Rien n'est facturé, aucune vraie carte n'est
débitée.

---

## 4. Récupérer les clés de test

Allez sur **[dashboard.stripe.com/apikeys](https://dashboard.stripe.com/apikeys)**
(menu « Développeurs » → « Clés API », ou « Workbench » → « API keys »).

Deux clés vous y attendent :

| Clé | Ressemble à | À quoi elle sert ici |
| --- | --- | --- |
| Clé publiable | `pk_test_51…` | À rien pour l'instant. La boutique redirige vers la page de paiement hébergée par Stripe ; elle n'exécute aucun code Stripe côté navigateur. Le champ existe dans la configuration pour le jour où l'on intégrerait le formulaire de carte dans la page. |
| Clé secrète | `sk_test_51…` | **Celle-là compte.** Cliquez « Révéler la clé de test » pour l'afficher. |

> **La clé secrète est un mot de passe.** Qui l'a peut encaisser et
> rembourser en votre nom. Elle ne va jamais dans un courriel, jamais dans
> une capture d'écran, jamais dans le dépôt Git. Si elle fuit, révoquez-la
> depuis cette même page : Stripe en fabrique une autre en un clic.

---

## 5. Les coller dans la boutique

Ouvrez `config/config.php` — le fichier de votre installation, celui qui
n'est jamais versionné. Si vous ne l'avez pas encore, copiez
`config/config.example.php` sous ce nom.

```php
'stripe' => [
    'secret_key'      => 'sk_test_51…',   // ← collée ici
    'publishable_key' => 'pk_test_51…',
    'webhook_secret'  => '',              // ← à l'étape suivante
],
```

Vérifiez aussi, un peu plus haut dans le même fichier :

```php
'site_url' => 'http://localhost:8000',    // en local
```

C'est cette adresse qui construit les liens de retour après paiement. Si elle
est fausse, le client paie puis atterrit nulle part.

**Sur Render**, il n'y a pas de fichier à éditer : le conteneur fabrique
`config/config.php` au démarrage à partir des variables d'environnement.
Renseignez-les dans l'onglet « Environment » du service :

| Variable | Valeur |
| --- | --- |
| `STRIPE_SECRET_KEY` | `sk_test_51…` |
| `STRIPE_PUBLISHABLE_KEY` | `pk_test_51…` |
| `STRIPE_WEBHOOK_SECRET` | `whsec_…` (étape 10) |

---

## 6. Le webhook, en local

### Pourquoi il est indispensable

C'est **le seul endroit** du code où une commande devient « payée » et où le
stock est décompté. Le retour du navigateur sur la page de confirmation ne
prouve rien : il peut être rejoué, interrompu, ou fabriqué de toutes pièces
par quelqu'un qui devine l'adresse.

Sans webhook configuré, vos commandes resteront **indéfiniment « en attente
de paiement »**, même une fois l'argent réellement encaissé. Et
l'administration refusera de les passer payées à la main — pour exactement la
même raison.

### En local, Stripe ne peut pas vous joindre

Votre machine n'a pas d'adresse publique. La ligne de commande Stripe fait le
pont.

```bash
# Une fois pour toutes
brew install stripe/stripe-cli/stripe    # macOS
stripe login                             # ouvre le navigateur, à confirmer

# À chaque session de développement, dans un terminal à part
stripe listen --forward-to localhost:8000/webhook/stripe
```

La commande affiche, au démarrage :

```
Ready! Your webhook signing secret is whsec_xxxxxxxxxxxx (^C to quit)
```

Copiez ce `whsec_…` dans `config/config.php` :

```php
'webhook_secret' => 'whsec_xxxxxxxxxxxx',
```

> Ce secret **change à chaque `stripe listen`**. Si les paiements de test
> cessent de fonctionner d'un jour à l'autre, c'est presque toujours ça :
> recopiez le nouveau.

Laissez ce terminal ouvert. Il affichera chaque événement reçu, avec le code
de réponse de la boutique — c'est votre meilleur outil de diagnostic.

---

## 7. Un paiement d'essai de bout en bout

Trois terminaux : la base, le serveur, `stripe listen`.

1. Sur la boutique, ajoutez un article au panier et allez jusqu'au paiement.
2. Sur la page Stripe, utilisez la carte de test :

   | | |
   | --- | --- |
   | Numéro | `4242 4242 4242 4242` |
   | Date | n'importe quelle date **future** (`12/30`) |
   | Cryptogramme | n'importe quels trois chiffres (`123`) |
   | Code postal | n'importe lequel (`92400`) |

3. Validez. Vous revenez sur la page de confirmation de la boutique.
4. Dans le terminal `stripe listen`, vous devez lire quelque chose comme :

   ```
   checkout.session.completed [evt_…]
   2026-09-30 14:12:03  <--  [200] POST http://localhost:8000/webhook/stripe
   ```

   **`[200]`, c'est gagné.** Un `[400]` ou un `[500]` : voyez la section 13.

5. Ouvrez `/admin/commandes` : la commande est là, statut **Payée**, et le
   stock du produit a baissé du nombre commandé.

### Les autres cartes à essayer

Une boutique qui ne marche que quand tout va bien ne marche pas.

| Numéro | Ce qui doit se passer |
| --- | --- |
| `4000 0025 0000 3155` | Demande une authentification 3-D Secure. Validez-la : la commande passe payée. |
| `4000 0000 0000 9995` | Refusée (fonds insuffisants). Le client revient au panier, **le stock n'a pas bougé**, la commande reste « en attente ». |
| `4000 0000 0000 0002` | Refusée par la banque. Même chose. |

Vérifiez aussi le cas de l'abandon : lancez un paiement, puis fermez l'onglet
Stripe sans payer. Au bout d'un moment, Stripe envoie
`checkout.session.expired` et la commande passe « annulée ». C'est normal, et
c'est géré.

---

## 8. Activer le compte pour de vrai

Tant que le compte n'est pas activé, vous ne pouvez encaisser que des cartes
de test.

1. Sur le tableau de bord, cliquez **« Activer le compte »** (ou
   « Compléter votre profil »).
2. Remplissez le formulaire avec les pièces de l'étape 2.
3. À la question du **type d'activité**, dites la vérité : vente en ligne
   d'équipement sportif.
4. Le **nom qui apparaîtra sur le relevé bancaire** du client : mettez
   `BOUGE CLUB`. C'est ce que votre client lira sur son relevé un mois plus
   tard, quand il aura oublié sa commande. Un nom obscur ici, c'est une
   contestation de paiement plus tard.
5. Envoyez.

Stripe répond en général sous 24 à 48 heures. Il peut réclamer une pièce
complémentaire : surveillez la boîte de réception du compte.

---

## 9. Passer en production

Une fois le compte activé :

1. **Désactivez le mode test** (l'interrupteur en haut du tableau de bord).
2. Retournez sur
   **[dashboard.stripe.com/apikeys](https://dashboard.stripe.com/apikeys)**.
   Les clés affichées commencent maintenant par `sk_live_` et `pk_live_`.
3. Remplacez-les dans `config/config.php` (ou dans les variables
   d'environnement Render).

> Les clés de test et de production sont **deux mondes étanches**. Un
> paiement de test n'apparaît pas dans les vrais paiements, un webhook de
> test ne déclenche pas le webhook de production, et un `whsec_` de test ne
> valide pas une signature de production. Quand quelque chose « marchait
> hier », commencez par vérifier qu'on n'a pas mélangé les deux.

---

## 10. Le webhook de production

Là, plus de ligne de commande : Stripe appelle directement votre serveur.

1. Allez sur
   **[dashboard.stripe.com/webhooks](https://dashboard.stripe.com/webhooks)**
   (l'onglet « Webhooks » de Workbench), **mode test désactivé**.
2. Cliquez **« Create an event destination »** / « Ajouter un point de
   terminaison ».
3. Portée : **« Your account »** (votre compte). Pas « Connected accounts ».
4. Version de l'API : laissez celle proposée par défaut.
5. **Événements à écouter** — cochez ces quatre-là, et rien d'autre :

   | Événement | Ce que la boutique en fait |
   | --- | --- |
   | `checkout.session.completed` | Passe la commande en « payée » et décompte le stock. **Le seul vraiment indispensable.** |
   | `checkout.session.async_payment_succeeded` | Même chose, pour les moyens de paiement différés. |
   | `checkout.session.expired` | Annule un panier abandonné au moment de payer. |
   | `checkout.session.async_payment_failed` | Annule un paiement différé qui n'a pas abouti. |

   N'en cochez pas davantage : chaque événement inutile est une requête de
   plus sur votre serveur, pour rien.

6. Type de destination : **« Webhook endpoint »**.
7. **Endpoint URL** :

   ```
   https://votre-domaine.fr/webhook/stripe
   ```

   En HTTPS obligatoirement, et **sans barre oblique finale**. Voyez
   l'encadré ci-dessous.

8. Validez. Sur la page du point de terminaison, un secret commençant par
   `whsec_` apparaît : cliquez **« Reveal secret »** / « Cliquer pour
   révéler », copiez-le dans `stripe.webhook_secret`.

> **Attention aux redirections.** Stripe considère toute réponse `3xx` comme
> un **échec**, et ne suit pas la redirection. Si votre serveur renvoie
> `http://` vers `https://`, ou `bouge.fr` vers `www.bouge.fr`, inscrivez
> directement l'adresse **finale** — celle qui répond sans rediriger.
> Testez-la : `curl -I https://votre-domaine.fr/webhook/stripe` doit
> répondre `400` (signature absente, c'est normal), surtout pas `301` ni
> `302`.

---

## 11. La vérification finale : un vrai paiement

Les cartes de test ne prouvent pas que la production marche. Faites un vrai
achat :

1. Créez un produit à **1,00 €**, sans le mettre en avant.
2. Achetez-le avec votre propre carte, comme un client.
3. Vérifiez les cinq choses :
   - le paiement apparaît dans
     [les paiements Stripe](https://dashboard.stripe.com/payments) ;
   - la commande est « Payée » dans `/admin/commandes` ;
   - le stock a baissé ;
   - vous avez reçu l'accusé de réception de la boutique ;
   - dans Workbench → votre point de terminaison → onglet **« Event
     deliveries »**, l'événement est marqué `Delivered` en `200`.
4. Remboursez-vous depuis le tableau de bord Stripe, et supprimez le produit.

Le premier virement vers votre compte bancaire arrive **sous 7 jours** (le
délai est réduit ensuite). Ne vous inquiétez pas de ne pas voir l'argent le
lendemain.

---

## 12. Les réglages à ne pas oublier

Dans les paramètres du tableau de bord Stripe :

- **Reçus par e-mail** (« Paramètres » → « Reçus par e-mail ») : activez-les.
  Le client recevra alors deux messages, qui ne disent pas la même chose — la
  boutique confirme la commande et le point de retrait, Stripe atteste du
  paiement. Les deux sont utiles.
- **Nom sur le relevé bancaire** : `BOUGE CLUB`, comme décidé à l'étape 8.
- **Notifications** : faites-vous prévenir par courriel des contestations de
  paiement. On a trois semaines pour y répondre, et personne ne pense à aller
  regarder.
- **Adresse de facturation** : Stripe peut la réclamer au client. La boutique
  la collecte déjà ; en la demandant deux fois, on perd des paniers.

---

## 13. Quand ça ne marche pas

| Symptôme | Cause la plus fréquente |
| --- | --- |
| Les commandes restent « en attente de paiement » alors que l'argent est encaissé | Le webhook n'arrive pas. Regardez l'onglet « Event deliveries » : le code de réponse vous dira lequel des cas ci-dessous. |
| Le webhook répond `400` « Signature invalide » | Le `whsec_` ne correspond pas. En local il change à chaque `stripe listen` ; en ligne, vérifiez que vous n'avez pas collé celui du mode test. |
| Le webhook répond `400` « Signature absente » | L'appel n'est pas celui de Stripe (un robot, un scanner). Sans gravité. |
| Le webhook répond `301` ou `302` | Redirection : voyez l'encadré de l'étape 10. Stripe ne la suit pas. |
| Le webhook répond `404` | L'adresse est fausse, ou la réécriture d'URL n'est pas active sur l'hébergement. Testez avec `curl -I`. |
| Le webhook répond `500` | Une erreur dans la boutique. Regardez le journal d'erreurs PHP de l'hébergement. |
| « Vous devez fournir une clé d'API » au moment de payer | `secret_key` est vide, ou le fichier de configuration n'est pas lu. |
| Le client paie et revient sur une page d'erreur | `site_url` est fausse dans la configuration. |
| Tout marchait, plus rien ne marche | Vérifiez l'interrupteur mode test, des deux côtés : clés **et** webhook. |

**Rejouer un événement** : dans Workbench, ouvrez l'événement et cliquez
**« Resend »**. Stripe le renvoie, et le traitement est prévu pour ça — il est
idempotent, une commande déjà payée ne sera pas payée deux fois, le stock ne
sera pas décompté deux fois, et le client ne recevra pas un second accusé de
réception.

Stripe retente d'ailleurs tout seul pendant **trois jours** en cas d'échec.
Une panne de serveur d'une heure ne perd aucune commande.

---

## 14. Ce que ça coûte

Au 30 septembre 2026, pour une carte européenne : **1,5 % + 0,25 €** par
transaction. Pas d'abonnement, pas de frais d'ouverture, pas d'engagement.
Les cartes hors Europe coûtent davantage ; un remboursement ne rend pas la
commission.

Sur une commande de 30 €, comptez environ **0,70 €**.

Les tarifs en vigueur : [stripe.com/fr/pricing](https://stripe.com/fr/pricing).

---

## En résumé, la liste à cocher

- [ ] Compte créé, double authentification activée
- [ ] Clés de test dans `config/config.php`
- [ ] `stripe listen` lancé, `whsec_` recopié
- [ ] Paiement de test réussi, commande « Payée », stock décompté
- [ ] Carte refusée testée : le stock n'a pas bougé
- [ ] Compte activé auprès de Stripe
- [ ] Clés `sk_live_` en place
- [ ] Point de terminaison de production créé, quatre événements cochés
- [ ] `whsec_` de production recopié
- [ ] Vrai paiement de 1 € vérifié de bout en bout, puis remboursé
- [ ] Reçus par e-mail activés, nom sur le relevé bancaire renseigné
