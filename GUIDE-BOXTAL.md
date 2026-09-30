# Brancher Boxtal, pas à pas

Ce guide part de zéro. À la fin, le client choisit son point relais dans la
liste des commerces réellement autour de chez lui, et vous n'avez plus qu'à
**cliquer sur un bouton, imprimer l'étiquette, emballer, déposer**.

Comptez **une heure** pour la partie technique, plus le délai d'ouverture du
compte de production (quelques jours ouvrés). Comme pour Stripe, les deux ne
se suivent pas : on branche et on teste tout de suite sur le serveur de test,
gratuitement.

> **Boxtal s'appelait Envoimoinscher.** Le nom de société a changé, pas
> l'infrastructure : l'API et les serveurs portent toujours l'ancien nom
> (`envoimoinscher.com`). Ce n'est pas une erreur de configuration.

---

## Sommaire

1. [Ce que Boxtal fait](#1-ce-que-boxtal-fait)
2. [Les deux comptes](#2-les-deux-comptes)
3. [Ouvrir le compte de test et obtenir les accès API](#3-ouvrir-le-compte-de-test-et-obtenir-les-accès-api)
4. [Les coller dans la boutique](#4-les-coller-dans-la-boutique)
5. [Vérifier l'adresse d'expédition](#5-vérifier-ladresse-dexpédition)
6. [Le poids des produits — à ne pas sauter](#6-le-poids-des-produits--à-ne-pas-sauter)
7. [Premier essai : les points relais](#7-premier-essai--les-points-relais)
8. [Deuxième essai : acheter une étiquette](#8-deuxième-essai--acheter-une-étiquette)
9. [Ouvrir le compte de production](#9-ouvrir-le-compte-de-production)
10. [Basculer en production](#10-basculer-en-production)
11. [Le geste quotidien](#11-le-geste-quotidien)
12. [Quand ça ne marche pas](#12-quand-ça-ne-marche-pas)
13. [Ce que ça coûte](#13-ce-que-ça-coûte)

---

## 1. Ce que Boxtal fait

Boxtal est un **courtier**, pas un transporteur. Un seul compte donne accès à
Mondial Relay, Colissimo, Chronopost, UPS et quelques autres — à domicile
comme en point relais —, sans abonnement, sans engagement de volume, sans
contrat à négocier avec chacun. On paie au colis, aux tarifs négociés par
Boxtal pour l'ensemble de ses clients, moins chers que le tarif guichet.

Dans la boutique, il fait trois choses, et trois seulement :

| Ce qu'il fait | Où ça se voit |
| --- | --- |
| **Liste les points relais** autour du code postal saisi par le client | Dans le tunnel de commande, à l'étape livraison |
| **Donne le détail d'un point** (adresse, horaires) | Sur la page de confirmation, et dans l'accusé de réception |
| **Vend l'étiquette** prépayée, avec son numéro de suivi | Dans l'administration, sur la fiche commande |

Tout le reste — le prix affiché au client, le seuil de livraison offerte, les
délais annoncés — vient de `config/shop.php` et vous appartient.

> **Boxtal n'encaisse rien.** L'argent du client, c'est Stripe. Les deux ne se
> parlent pas et n'ont pas à se parler.

---

## 2. Les deux comptes

Boxtal a **deux environnements totalement séparés** :

| | Serveur | Ce qui s'y passe |
| --- | --- | --- |
| **Test** | `test.envoimoinscher.com` | Les expéditions sont fictives. Aucune étiquette réelle, aucun colis, **aucune facturation**. |
| **Production** | `www.envoimoinscher.com` | Chaque étiquette achetée est **facturée**, immédiatement. |

Ils ont des **identifiants distincts** : ceux du test ne fonctionnent pas en
production, et réciproquement. Dans la boutique, on passe de l'un à l'autre
avec un seul réglage, `boxtal.test`.

Faites tout le chemin en test d'abord. C'est gratuit, et c'est le seul moment
où se tromper ne coûte rien.

---

## 3. Ouvrir le compte de test et obtenir les accès API

1. Créez un compte sur **[boxtal.com](https://www.boxtal.com)** : adresse
   électronique, mot de passe, type d'activité **e-commerce**.
2. Dans votre espace, cherchez la rubrique **« Intégrations »**, « API » ou
   « Accès développeur » (le libellé varie selon les comptes). Vous y
   demandez l'**accès API**, et vous y récupérez :
   - un **identifiant** (souvent votre adresse électronique) ;
   - un **mot de passe d'API**, ou une clé, distincte du mot de passe avec
     lequel vous vous connectez au site.
3. Demandez en même temps l'ouverture du **compte de test**. Sur certains
   comptes il est fourni d'office, sur d'autres il faut le réclamer au
   support — écrivez-leur, la réponse arrive dans la journée : vous
   développez une boutique et vous avez besoin des accès API sur
   l'environnement de test.

> **Si on vous propose l'API v3 :** la boutique parle l'**API v1**, celle qui
> échange du XML avec `envoimoinscher.com`. Les deux coexistent et v1 reste
> documentée et maintenue ; c'est elle que nous utilisons, parce qu'elle
> expose la liste des points relais dans la cotation, ce qui évite un
> deuxième appel. Il n'y a rien à faire de particulier : les identifiants
> sont les mêmes.
>
> Documentation : [developer.boxtal.com](https://developer.boxtal.com/fr/fr/apiv1/guide/)

---

## 4. Les coller dans la boutique

Deux fichiers, et ce n'est pas un hasard : **ce qui est secret ne va jamais
dans le dépôt.**

**`config/config.php`** — vos identifiants, jamais versionné :

```php
'boxtal' => [
    'user'     => 'votre-identifiant',
    'password' => 'votre-mot-de-passe-api',
    'test'     => true,          // ← on commence par là
],
```

**`config/shop.php`** — les réglages commerciaux, versionnés :

```php
'carrier' => [
    'driver' => 'boxtal',        // '' = aucun, 'demonstration' = points inventés
    'from'   => [ /* voir l'étape suivante */ ],
],
```

**Sur Render**, ce sont des variables d'environnement, dans l'onglet
« Environment » du service :

| Variable | Valeur |
| --- | --- |
| `BOXTAL_USER` | votre identifiant |
| `BOXTAL_PASSWORD` | votre mot de passe d'API |
| `BOXTAL_TEST` | `1` en test, `0` en production |
| `BOUGE_CARRIER_DRIVER` | `boxtal` |

> **Si les identifiants manquent, la boutique ne tombe pas.** Elle se rabat
> sur « aucun transporteur », le signale dans le journal d'erreurs, et
> continue de vendre en retrait au concept store. Une clé oubliée ne ferme
> pas le magasin.

---

## 5. Vérifier l'adresse d'expédition

Dans `config/shop.php`, sous `carrier.from` :

```php
'from' => [
    'company'     => 'BOUGE',
    'address'     => '8 rue Albert Simonin',
    'postal_code' => '92400',
    'city'        => 'Courbevoie',
    'country'     => 'FR',
    'phone'       => '',
    'email'       => 'contact@bouge.fr',
],
```

Cette adresse n'est pas décorative : c'est **celle qui s'imprime sur
l'étiquette**, et c'est d'elle que part le calcul du tarif. Une erreur ici et
le colis revient au mauvais endroit en cas de refus.

**Renseignez le téléphone.** Il est vide, et certains transporteurs le
réclament pour un enlèvement ou pour joindre l'expéditeur en cas de problème
d'adresse. C'est le genre de champ dont l'absence ne se remarque que le jour
où un colis est bloqué.

---

## 6. Le poids des produits — à ne pas sauter

Le poids détermine le tarif. Un poids faux, c'est un tarif faux, et un colis
refusé au dépôt ou refacturé.

Dans l'administration, chaque produit a un champ **poids en grammes**. La
boutique additionne les lignes de la commande pour obtenir le poids du colis.
Les produits sans poids saisi tombent sur une valeur par défaut, qui est un
pis-aller.

Pesez-les une fois, pour de bon. Un ordre de grandeur, pour se repérer :

| | |
| --- | --- |
| Bonnet de bain silicone | 50 à 70 g |
| Lunettes avec leur étui | 100 à 150 g |
| Maillot d'entraînement | 100 à 200 g |
| Serviette microfibre | 150 à 300 g |
| Le livre | 400 à 600 g |

Ajoutez l'emballage : une enveloppe à bulles, c'est 30 à 50 g ; un carton,
150 à 300 g. Mieux vaut arrondir au-dessus.

Le colis type utilisé pour la cotation mesure **25 × 20 × 10 cm**. Si vous
expédiez systématiquement plus gros, dites-le : ça se change dans
`src/Shipping/BoxtalCarrier.php`.

---

## 7. Premier essai : les points relais

Avec `test` à `true`, aucune facturation possible. Allez-y franchement.

1. Ajoutez un article au panier, allez jusqu'à l'étape livraison.
2. Choisissez **« Livraison en point relais »**, saisissez un code postal et
   une ville réels.
3. La liste doit afficher des **commerces qui existent vraiment** : un tabac,
   une supérette, un pressing, avec leur adresse et leurs horaires.

Si vous voyez des noms visiblement inventés (« Tabac de la Mairie », « 72 rue
de la République »), c'est le pilote `demonstration` qui répond, pas Boxtal :
vérifiez `driver` dans `config/shop.php`.

Dans l'administration, le nom du transporteur affiché doit être **« Boxtal
(mode test) »**. Cette mention est là exprès : tant qu'elle s'affiche, rien
de ce que vous faites n'a d'effet dans le monde réel.

---

## 8. Deuxième essai : acheter une étiquette

Toujours en mode test.

1. Passez une commande complète, payée avec une carte de test Stripe (voyez
   l'autre guide) pour qu'elle arrive bien au statut **Payée**.
2. Ouvrez-la dans `/admin/commandes`.
3. Dans l'encadré **Étiquette**, un bouton annonce le **poids calculé**.
   Vérifiez-le : c'est la somme des poids de l'étape 6, et c'est l'occasion
   de voir si l'un d'eux manque.
4. Cliquez. Une confirmation en deux temps s'ouvre — **en production,
   l'étiquette est facturée dès ce clic, et rien ne la rembourse**. C'est
   pour ça qu'on confirme deux fois.
5. Confirmez. La commande passe **Expédiée**, le numéro de suivi apparaît, et
   un lien donne le PDF de l'étiquette.

Ouvrez le PDF : votre adresse doit être à l'expéditeur, celle du client (ou
du point relais) au destinataire, avec un code-barres lisible.

Quelques garde-fous, qu'il vaut mieux connaître avant de s'y heurter :

- Une étiquette **déjà achetée ne peut pas l'être une seconde fois** ; le
  lien vers le PDF reste valable, donc si l'impression rate, on réimprime
  sans racheter.
- Une commande **impayée n'affiche aucun bouton**. Rien ne part avant le
  paiement.
- En cas d'échec — Boxtal injoignable, adresse refusée —, le message le dit
  et **rien n'est enregistré**. Pas de commande fantôme à rattraper.

---

## 9. Ouvrir le compte de production

1. Dans votre espace Boxtal, complétez le profil de l'entreprise : **SIRET**,
   adresse, activité.
2. Renseignez le **moyen de paiement**. Boxtal fonctionne soit par
   prélèvement automatique (il faut un IBAN et un mandat SEPA), soit par
   porte-monnaie prépayé qu'on recharge. Le prélèvement est plus simple au
   quotidien ; le porte-monnaie évite les mauvaises surprises.
3. Demandez l'**activation des accès API en production**. Vous recevrez des
   identifiants **différents** de ceux du test.
4. Vérifiez quels transporteurs sont activés sur votre compte. Tous ne le
   sont pas d'office, et un compte sans Mondial Relay ne proposera pas de
   point relais bon marché.

---

## 10. Basculer en production

Trois choses, dans cet ordre :

```php
// config/config.php
'boxtal' => [
    'user'     => 'identifiant-de-PRODUCTION',   // 1. les nouveaux identifiants
    'password' => 'mot-de-passe-de-PRODUCTION',
    'test'     => false,                          // 2. la bascule
],
```

3. Rechargez une fiche commande dans l'administration : la mention **« mode
   test »** doit avoir **disparu**. Tant qu'elle est là, vous êtes encore en
   test, quoi qu'en disent les identifiants.

Puis faites un vrai envoi, pour de vrai : expédiez-vous un colis à
vous-même, en point relais. Quelques euros pour vérifier que l'étiquette est
acceptée au dépôt, que le suivi se met à jour, et que le point relais vous
prévient. C'est la seule preuve qui vaille.

---

## 11. Le geste quotidien

Une fois tout branché, une commande se traite ainsi :

1. Une **pastille rouge** apparaît sur « Commandes » dans le menu de
   l'administration : autant de commandes nouvelles que de chiffre affiché.
2. Ouvrez la commande. Vérifiez les articles et le mode de remise.
3. **Achetez l'étiquette.** La commande passe « Expédiée » toute seule :
   acheter l'étiquette est l'acte qui engage l'envoi, inutile de le redire
   en deux clics.
4. **Imprimez** le PDF, collez-le sur le colis.
5. Emballez, **déposez** au point relais ou au bureau de poste indiqué.
6. Le client reçoit son numéro de suivi ; le transporteur le préviendra de
   l'arrivée.

Rien à ressaisir nulle part, rien à recopier d'un site à l'autre.

---

## 12. Quand ça ne marche pas

| Symptôme | Cause la plus fréquente |
| --- | --- |
| Aucun point relais ne s'affiche | `driver` n'est pas `boxtal`, ou les identifiants sont vides. Regardez le journal d'erreurs PHP : la boutique y écrit la raison. |
| Des points relais manifestement inventés | Le pilote `demonstration` est encore actif. **À ne jamais laisser en production** : un client choisirait un commerce qui n'existe pas. |
| « Transporteur injoignable » | Boxtal ne répond pas dans les 8 secondes imparties, ou l'hébergement bloque les appels sortants. Certains mutualisés ferment les connexions sortantes : il faut les ouvrir. |
| Identifiants refusés | Vous utilisez ceux du test avec `'test' => false`, ou l'inverse. Les deux environnements ne se connaissent pas. |
| L'API répond mais aucune offre | Le code postal est invalide, ou aucun transporteur n'est activé sur le compte pour cette destination. |
| Le tarif semble faux | Le poids. Toujours le poids. Vérifiez la fiche produit. |
| L'étiquette est refusée au dépôt | L'adresse d'expédition de `config/shop.php` ne correspond pas à celle du compte Boxtal. |

---

## 13. Ce que ça coûte

Pas d'abonnement, pas d'engagement : on paie chaque colis. Les tarifs
dépendent du poids, de la destination et du transporteur choisi. Pour donner
l'ordre de grandeur, un colis de moins d'un kilo en France :

- **point relais** : autour de 4 €
- **domicile** : autour de 6 €

À comparer avec ce que la boutique facture au client, dans `config/shop.php` :
**3,90 € en point relais, 4,90 € à domicile**, offert au-delà de 60 €.

Le point relais tombe à peu près juste. **Le domicile est vendu à perte**,
d'environ un euro par colis : c'est un choix commercial défendable — le prix
affiché reste lisible, et la marge sur les articles absorbe la différence —
mais c'est un choix, pas un hasard, et il vaut mieux le faire en le sachant.
Au-dessus de 60 €, la livraison offerte coûte le prix plein.

Si vous changez le seuil ou les tarifs, refaites ce calcul : ajoutez la
commission Stripe (1,5 % + 0,25 €) et vous aurez le coût réel d'une commande.

Les tarifs en vigueur : [boxtal.com/fr/fr/tarifs](https://www.boxtal.com).

---

## En résumé, la liste à cocher

- [ ] Compte Boxtal créé, accès API demandés
- [ ] Identifiants de **test** dans `config/config.php`, `'test' => true`
- [ ] `'driver' => 'boxtal'` dans `config/shop.php`
- [ ] Adresse d'expédition vérifiée, **téléphone renseigné**
- [ ] Poids saisi sur **tous** les produits
- [ ] Les points relais affichés sont de vrais commerces
- [ ] Une étiquette de test achetée, le PDF vérifié
- [ ] Compte de production activé, moyen de paiement en place
- [ ] Identifiants de production en place, `'test' => false`
- [ ] La mention « mode test » a disparu de l'administration
- [ ] Un vrai colis envoyé et reçu
