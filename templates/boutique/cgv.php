<?php
/** @var array<string, mixed> $shop */

use Bouge\Support\Money;

$flatRate = (int) $shop['shipping']['flat_rate_cents'];
$freeAbove = $shop['shipping']['free_above_cents'];
?>
<div class="wrap wrap--narrow">
    <article class="section">
        <h1 class="t-xl">Conditions générales de vente</h1>

        <div class="stack-l muted" style="margin-top:2.5rem">
            <p class="notice notice--accent">
                <strong>À compléter avant la mise en ligne.</strong>
                Ce texte est un gabarit de travail, pas un document juridique validé.
                Faites-le relire avant l'ouverture de la boutique.
            </p>

            <section>
                <h2 class="t-s" style="color:var(--ink)">1. Objet</h2>
                <p style="margin-top:.5rem">
                    Les présentes conditions régissent les ventes conclues sur le site
                    <?= e($shop['name']) ?> entre [dénomination sociale] et toute personne
                    physique non commerçante effectuant un achat.
                </p>
            </section>

            <section>
                <h2 class="t-s" style="color:var(--ink)">2. Prix</h2>
                <p style="margin-top:.5rem">
                    Les prix sont indiqués en euros, toutes taxes comprises, hors frais de
                    livraison. Le prix applicable est celui affiché au moment de la validation
                    de la commande.
                </p>
            </section>

            <section>
                <h2 class="t-s" style="color:var(--ink)">3. Commande et paiement</h2>
                <p style="margin-top:.5rem">
                    Le paiement s'effectue par carte bancaire via Stripe. Aucune donnée de
                    carte ne transite par nos serveurs ni n'y est conservée. La commande est
                    ferme à réception de la confirmation de paiement.
                </p>
            </section>

            <section>
                <h2 class="t-s" style="color:var(--ink)">4. Livraison et retrait</h2>
                <ul style="margin-top:.5rem;padding-left:1.1rem">
                    <li>
                        Livraison en France métropolitaine : <?= e(Money::format($flatRate)) ?><?php if ($freeAbove !== null): ?>,
                        offerte à partir de <?= e(Money::format((int) $freeAbove)) ?> d'achat<?php endif; ?>.
                        Délai indicatif : [X] jours ouvrés.
                    </li>
                    <li>
                        Retrait sur place, sans frais. Vous êtes prévenu par courriel dès que
                        la commande est prête.
                    </li>
                </ul>
            </section>

            <section>
                <h2 class="t-s" style="color:var(--ink)">5. Droit de rétractation</h2>
                <p style="margin-top:.5rem">
                    Vous disposez de quatorze jours à compter de la réception pour retourner un
                    article, sans avoir à motiver votre décision. L'article doit être neuf, non
                    porté et dans son emballage d'origine. Pour des raisons d'hygiène, les
                    maillots de bain ne sont repris que si leur bande de protection est
                    intacte. Les frais de retour restent à votre charge.
                </p>
            </section>

            <section>
                <h2 class="t-s" style="color:var(--ink)">6. Garanties</h2>
                <p style="margin-top:.5rem">
                    Tous les produits bénéficient de la garantie légale de conformité et de la
                    garantie contre les vices cachés, dans les conditions prévues par le code
                    de la consommation et le code civil.
                </p>
            </section>

            <section>
                <h2 class="t-s" style="color:var(--ink)">7. Réclamations et litiges</h2>
                <p style="margin-top:.5rem">
                    Pour toute réclamation, écrivez à <?= e($shop['email']) ?>. À défaut
                    d'accord, vous pouvez recourir gratuitement à un médiateur de la
                    consommation : [nom et coordonnées du médiateur].
                </p>
            </section>
        </div>
    </article>
</div>
