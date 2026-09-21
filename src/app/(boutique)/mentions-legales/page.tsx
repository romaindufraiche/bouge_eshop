import type { Metadata } from 'next';
import { LegalPage, ToComplete } from '@/components/ui/LegalPage';
import { buildMetadata } from '@/lib/seo';
import { SHOP } from '@/lib/shop-config';

export const metadata: Metadata = buildMetadata({
  title: 'Mentions légales',
  description: `Mentions légales de la boutique ${SHOP.name}.`,
  path: '/mentions-legales',
  noIndex: true,
});

export default function MentionsLegalesPage() {
  return (
    <LegalPage title="Mentions légales">
      <ToComplete>
        Ces mentions sont un gabarit. Remplacez les mentions entre crochets par
        les informations réelles de la société avant d&apos;ouvrir la boutique
        au public.
      </ToComplete>

      <section>
        <h2>Éditeur du site</h2>
        <p>
          [Dénomination sociale], [forme juridique] au capital de [montant] €.
          <br />
          Siège social : [adresse complète].
          <br />
          RCS [ville] [numéro] — SIRET [numéro].
          <br />
          TVA intracommunautaire : [numéro].
          <br />
          Directeur de la publication : [nom].
          <br />
          Contact : {SHOP.email}
        </p>
      </section>

      <section>
        <h2>Hébergement</h2>
        <p>
          Vercel Inc., 440 N Barranca Ave #4133, Covina, CA 91723, États-Unis.
        </p>
      </section>

      <section>
        <h2>Propriété intellectuelle</h2>
        <p>
          L&apos;ensemble des contenus de ce site (textes, photographies, logo,
          identité visuelle) est protégé par le droit d&apos;auteur. Toute
          reproduction sans autorisation écrite préalable est interdite.
        </p>
      </section>

      <section>
        <h2>Données personnelles</h2>
        <p>
          Les informations collectées lors d&apos;une commande (nom, adresse
          électronique, adresse de livraison) servent uniquement au traitement de
          cette commande. Elles ne sont ni vendues ni cédées à des tiers, à
          l&apos;exception des prestataires strictement nécessaires : Stripe pour
          le paiement et le transporteur pour la livraison.
        </p>
        <p>
          Conformément au règlement général sur la protection des données, vous
          disposez d&apos;un droit d&apos;accès, de rectification et de
          suppression de vos données. Pour l&apos;exercer, écrivez à{' '}
          {SHOP.email}.
        </p>
      </section>

      <section>
        <h2>Cookies</h2>
        <p>
          Ce site n&apos;utilise pas de cookie publicitaire ni de mesure
          d&apos;audience. Le contenu de votre panier est conservé dans le
          stockage local de votre navigateur et n&apos;est transmis à nos
          serveurs qu&apos;au moment de la commande.
        </p>
      </section>
    </LegalPage>
  );
}
