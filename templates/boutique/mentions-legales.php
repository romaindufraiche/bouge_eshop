<?php
/** @var array<string, mixed> $shop */
?>
<div class="wrap wrap--narrow">
    <article class="section">
        <h1 class="t-xl">Mentions légales</h1>

        <div class="stack-l muted" style="margin-top:2.5rem">
            <p class="notice notice--accent">
                <strong>À compléter avant la mise en ligne.</strong>
                Ces mentions sont un gabarit. Remplacez les mentions entre crochets par les
                informations réelles de la société.
            </p>

            <section>
                <h2 class="t-s" style="color:var(--ink)">Éditeur du site</h2>
                <p style="margin-top:.5rem">
                    [Dénomination sociale], [forme juridique] au capital de [montant] €.<br>
                    Siège social : [adresse complète].<br>
                    RCS [ville] [numéro] — SIRET [numéro].<br>
                    TVA intracommunautaire : [numéro].<br>
                    Directeur de la publication : [nom].<br>
                    Contact : <?= e($shop['email']) ?>
                </p>
            </section>

            <section>
                <h2 class="t-s" style="color:var(--ink)">Hébergement</h2>
                <p style="margin-top:.5rem">[Nom de l'hébergeur], [adresse], [téléphone].</p>
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
                    Conformément au règlement général sur la protection des données, vous
                    disposez d'un droit d'accès, de rectification et de suppression de vos
                    données. Pour l'exercer, écrivez à <?= e($shop['email']) ?>.
                </p>
            </section>

            <section>
                <h2 class="t-s" style="color:var(--ink)">Cookies</h2>
                <p style="margin-top:.5rem">
                    Ce site n'utilise pas de cookie publicitaire ni de mesure d'audience. Un
                    unique cookie technique conserve le contenu de votre panier et votre
                    session le temps de votre visite.
                </p>
            </section>
        </div>
    </article>
</div>
