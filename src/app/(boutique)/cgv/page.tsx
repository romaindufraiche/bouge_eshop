import type { Metadata } from 'next';
import { LegalPage, ToComplete } from '@/components/ui/LegalPage';
import { formatPrice } from '@/lib/money';
import { buildMetadata } from '@/lib/seo';
import { SHIPPING, SHOP } from '@/lib/shop-config';

export const metadata: Metadata = buildMetadata({
  title: 'Conditions générales de vente',
  description: `Conditions générales de vente de la boutique ${SHOP.name}.`,
  path: '/cgv',
  noIndex: true,
});

export default function CgvPage() {
  return (
    <LegalPage title="Conditions générales de vente">
      <ToComplete>
        Ce texte est un gabarit de travail, pas un document juridique validé.
        Faites-le relire avant l&apos;ouverture de la boutique.
      </ToComplete>

      <section>
        <h2>1. Objet</h2>
        <p>
          Les présentes conditions régissent les ventes conclues sur le site{' '}
          {SHOP.name} entre [dénomination sociale] et toute personne physique
          non commerçante effectuant un achat.
        </p>
      </section>

      <section>
        <h2>2. Prix</h2>
        <p>
          Les prix sont indiqués en euros, toutes taxes comprises, hors frais de
          livraison. Ils peuvent être modifiés à tout moment ; le prix applicable
          est celui affiché au moment de la validation de la commande.
        </p>
      </section>

      <section>
        <h2>3. Commande et paiement</h2>
        <p>
          Le paiement s&apos;effectue par carte bancaire via Stripe. Aucune donnée
          de carte ne transite par nos serveurs ni n&apos;y est conservée. La
          commande est considérée comme ferme à réception de la confirmation de
          paiement.
        </p>
      </section>

      <section>
        <h2>4. Livraison et retrait</h2>
        <p>
          Deux modes sont proposés lors de la commande :
        </p>
        <ul>
          <li>
            Livraison en France métropolitaine :{' '}
            {formatPrice(SHIPPING.flatRateCents)}
            {SHIPPING.freeAboveCents !== null &&
              `, offerte à partir de ${formatPrice(SHIPPING.freeAboveCents)} d'achat`}
            . Délai indicatif : [X] jours ouvrés.
          </li>
          <li>
            Retrait sur place, sans frais. Vous êtes prévenu par courriel dès que
            la commande est prête.
          </li>
        </ul>
      </section>

      <section>
        <h2>5. Droit de rétractation</h2>
        <p>
          Vous disposez de quatorze jours à compter de la réception pour
          retourner un article, sans avoir à motiver votre décision. L&apos;article
          doit être neuf, non porté et dans son emballage d&apos;origine. Pour des
          raisons d&apos;hygiène, les maillots de bain ne sont repris que si leur
          bande de protection est intacte. Les frais de retour restent à votre
          charge.
        </p>
      </section>

      <section>
        <h2>6. Garanties</h2>
        <p>
          Tous les produits bénéficient de la garantie légale de conformité et de
          la garantie contre les vices cachés, dans les conditions prévues par le
          code de la consommation et le code civil.
        </p>
      </section>

      <section>
        <h2>7. Réclamations et litiges</h2>
        <p>
          Pour toute réclamation, écrivez à {SHOP.email}. À défaut d&apos;accord,
          vous pouvez recourir gratuitement à un médiateur de la consommation :
          [nom et coordonnées du médiateur].
        </p>
      </section>
    </LegalPage>
  );
}
