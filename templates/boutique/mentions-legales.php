<?php
/**
 * Mentions légales.
 *
 * Les informations de société viennent de `config/shop.php`, bloc `legal` :
 * elles sont identiques ici et dans les CGV, et ne doivent donc être saisies
 * qu'une fois.
 *
 * @var array<string, mixed> $shop
 */

$legal = $shop['legal'] ?? [];
$hote = $legal['host'] ?? [];
$mediateur = $legal['mediator'] ?? [];
$mediateurManquant = trim((string) ($mediateur['name'] ?? '')) === '';
?>
<div class="wrap wrap--narrow">
    <article class="section">
        <h1 class="t-xl">Mentions légales</h1>

        <div class="stack-l muted" style="margin-top:2.5rem">
            <?php if ($mediateurManquant): ?>
                <?php /* Un avertissement plutôt qu'un nom inventé : publier un
                         médiateur auquel on n'a pas adhéré serait pire que de
                         n'en afficher aucun. */ ?>
                <p class="notice notice--accent">
                    <strong>Médiateur de la consommation à renseigner.</strong>
                    Tout vendeur en ligne doit adhérer à un médiateur et publier ses
                    coordonnées (article L.612-1 du code de la consommation). Complétez
                    le bloc <code>legal.mediator</code> de <code>config/shop.php</code>
                    avant l'ouverture.
                </p>
            <?php endif; ?>

            <section>
                <h2 class="t-s" style="color:var(--ink)">Éditeur du site</h2>
                <p style="margin-top:.5rem">
                    <?= e((string) ($legal['company'] ?? '')) ?>,
                    <?= e((string) ($legal['form'] ?? '')) ?>
                    au capital de <?= e((string) ($legal['capital'] ?? '')) ?>.<br>
                    Siège social : <?= e((string) ($legal['address'] ?? '')) ?>.<br>
                    RCS <?= e((string) ($legal['rcs'] ?? '')) ?>
                    <?= e((string) ($legal['siren'] ?? '')) ?> —
                    SIRET <?= e((string) ($legal['siret'] ?? '')) ?>.<br>
                    TVA intracommunautaire : <?= e((string) ($legal['vat'] ?? '')) ?>.<br>
                    Activité : <?= e((string) ($legal['ape'] ?? '')) ?>.<br>
                    Directeur de la publication : <?= e((string) ($legal['publisher'] ?? '')) ?>.<br>
                    Contact : <?= e($shop['email']) ?>
                </p>
                <p class="t-xs" style="margin-top:.75rem">
                    <?= e((string) ($legal['company'] ?? '')) ?> exploite le concept store
                    <?= e((string) ($shop['store']['name'] ?? 'BOUGE')) ?> et sa boutique en
                    ligne <?= e($shop['name']) ?>.
                </p>
            </section>

            <section>
                <h2 class="t-s" style="color:var(--ink)">Hébergement</h2>
                <p style="margin-top:.5rem">
                    <?= e((string) ($hote['name'] ?? '')) ?>,
                    <?= e((string) ($hote['address'] ?? '')) ?><?php if (($hote['phone'] ?? '') !== ''): ?>,
                        téléphone <?= e((string) $hote['phone']) ?><?php endif; ?>.
                </p>
            </section>

            <section>
                <h2 class="t-s" style="color:var(--ink)">Propriété intellectuelle</h2>
                <p style="margin-top:.5rem">
                    L'ensemble des contenus de ce site (textes, photographies, logo, identité
                    visuelle) est protégé par le droit d'auteur. Toute reproduction sans
                    autorisation écrite préalable est interdite.
                </p>
            </section>

            <section>
                <h2 class="t-s" style="color:var(--ink)">Données personnelles</h2>
                <p style="margin-top:.5rem">
                    Les informations collectées lors d'une commande (nom, adresse électronique,
                    adresse de livraison) servent uniquement au traitement de cette commande.
                    Elles ne sont ni vendues ni cédées à des tiers, à l'exception des
                    prestataires strictement nécessaires : Stripe pour le paiement et le
                    transporteur pour la livraison.
                </p>
                <p style="margin-top:.5rem">
                    Les commandes sont conservées dix ans, durée légale de conservation des
                    pièces comptables. Un compte client inactif depuis trois ans est supprimé.
                </p>
                <p style="margin-top:.5rem">
                    Conformément au règlement général sur la protection des données, vous
                    disposez d'un droit d'accès, de rectification, d'effacement et de
                    portabilité de vos données, ainsi que d'un droit d'opposition. Pour
                    l'exercer, écrivez à <?= e($shop['email']) ?>. En cas de désaccord, vous
                    pouvez saisir la CNIL (<a href="https://www.cnil.fr">cnil.fr</a>).
                </p>
            </section>

            <section>
                <h2 class="t-s" style="color:var(--ink)">Cookies</h2>
                <p style="margin-top:.5rem">
                    Ce site n'utilise pas de cookie publicitaire ni de mesure d'audience. Un
                    unique cookie technique conserve le contenu de votre panier et votre
                    session le temps de votre visite. Il est nécessaire au fonctionnement du
                    site et ne demande donc pas de consentement.
                </p>
            </section>

            <section>
                <h2 class="t-s" style="color:var(--ink)">Médiation de la consommation</h2>
                <p style="margin-top:.5rem">
                    <?php if ($mediateurManquant): ?>
                        Le médiateur de la consommation sera indiqué ici.
                    <?php else: ?>
                        En cas de litige non résolu avec notre service client, vous pouvez
                        recourir gratuitement au médiateur
                        <?= e((string) $mediateur['name']) ?><?php if (($mediateur['address'] ?? '') !== ''): ?>,
                            <?= e((string) $mediateur['address']) ?><?php endif; ?><?php if (($mediateur['url'] ?? '') !== ''): ?>
                            — <a href="<?= e((string) $mediateur['url']) ?>"><?= e((string) $mediateur['url']) ?></a><?php endif; ?>.
                    <?php endif; ?>
                    La plateforme européenne de règlement en ligne des litiges est accessible à
                    l'adresse <a href="https://ec.europa.eu/consumers/odr">ec.europa.eu/consumers/odr</a>.
                </p>
            </section>
        </div>
    </article>
</div>
