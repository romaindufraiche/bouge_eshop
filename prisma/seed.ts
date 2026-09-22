/**
 * Données de démonstration.
 *
 *   npm run db:seed
 *
 * Le script est ré-exécutable : il vide le catalogue et le recrée à
 * l'identique. Les COMMANDES ne sont jamais touchées.
 * Le compte admin est créé à partir de ADMIN_EMAIL / ADMIN_PASSWORD (.env) ;
 * s'il existe déjà, seul son mot de passe est remis à jour.
 */
import { hash } from 'bcryptjs';
import { PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();

/** Transforme "Bonnet silicone uni" en "bonnet-silicone-uni". */
function slugify(input: string): string {
  return input
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '') // retire les accents
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

type SeedVariant = { size?: string; color?: string; stock: number };

type SeedProduct = {
  name: string;
  description: string;
  priceCents: number;
  salePriceCents?: number;
  saleEndsAt?: Date;
  stock?: number;
  metaDescription: string;
  image: string;
  variants?: SeedVariant[];
  /** Mis en avant en haut de la page d'accueil. */
  featured?: boolean;
  /** Vendu par un tiers : la fiche renvoie vers ce lien. */
  externalUrl?: string;
  externalLabel?: string;
  /** Également disponible à la boutique. */
  availableInStore?: boolean;
};

const CATEGORIES: {
  name: string;
  description: string;
  image: string;
  products: SeedProduct[];
}[] = [
  {
    name: 'Bonnets',
    description:
      "Silicone ou tissu, pour l'entraînement comme pour la compétition.",
    image: '/images/demo/bonnets.svg',
    products: [
      {
        name: 'Bonnet silicone uni',
        description:
          "Silicone épais, sans couture. Il tient en place sur les virages et ne tire pas les cheveux à l'enfilage. Taille unique adulte.",
        priceCents: 1490,
        availableInStore: true,
        salePriceCents: 1190,
        saleEndsAt: new Date(Date.now() + 1000 * 60 * 60 * 24 * 21),
        metaDescription:
          'Bonnet de bain en silicone sans couture, taille unique adulte. Tient en place à chaque virage.',
        image: '/images/demo/bonnets.svg',
        variants: [
          { color: 'Noir', stock: 40 },
          { color: 'Blanc', stock: 25 },
          { color: 'Bleu', stock: 18 },
          { color: 'Rouge', stock: 12 },
        ],
      },
      {
        name: 'Bonnet tissu maille',
        description:
          "Polyester maillé, plus souple que le silicone. Moins étanche, mais confortable sur les longues séances. Se sèche en quelques minutes.",
        priceCents: 1990,
        metaDescription:
          'Bonnet de bain en tissu polyester maillé, souple et respirant pour les longues séances.',
        image: '/images/demo/bonnets.svg',
        variants: [
          { color: 'Noir', stock: 22 },
          { color: 'Marine', stock: 15 },
        ],
      },
      {
        name: 'Bonnet longue chevelure',
        description:
          'Volume intérieur augmenté pour les cheveux longs ou attachés. Silicone souple, bords renforcés.',
        priceCents: 1790,
        metaDescription:
          'Bonnet de bain silicone à volume augmenté, conçu pour les cheveux longs.',
        image: '/images/demo/bonnets.svg',
        variants: [
          { color: 'Noir', stock: 20 },
          { color: 'Violet', stock: 14 },
        ],
      },
    ],
  },
  {
    name: 'Lunettes',
    description: "Du créneau quotidien au départ plongé.",
    image: '/images/demo/lunettes.svg',
    products: [
      {
        name: "Lunettes d'entraînement",
        description:
          "Joints en silicone souple, champ de vision large, traitement anti-buée. Le modèle à prendre si vous nagez plusieurs fois par semaine. Pont nasal interchangeable, trois tailles fournies.",
        priceCents: 2490,
        availableInStore: true,
        metaDescription:
          "Lunettes de natation d'entraînement, joints silicone souple et traitement anti-buée. Pont nasal ajustable.",
        image: '/images/demo/lunettes.svg',
        variants: [
          { color: 'Transparent', stock: 30 },
          { color: 'Fumé', stock: 26 },
          { color: 'Bleu', stock: 19 },
        ],
      },
      {
        name: 'Lunettes miroir compétition',
        description:
          "Profil bas, joints fins, verres miroir pour le bassin extérieur. Elles marquent le contour des yeux : à réserver aux séries et aux courses, pas aux deux heures d'entraînement.",
        priceCents: 3990,
        metaDescription:
          'Lunettes de natation compétition à verres miroir et profil bas, pour bassin extérieur.',
        image: '/images/demo/lunettes.svg',
        variants: [
          { color: 'Argent', stock: 12 },
          { color: 'Or', stock: 8 },
        ],
      },
      {
        name: 'Lunettes junior',
        description:
          "Format réduit pour les 6-12 ans. Sangle double, boucles à réglage rapide que l'enfant manipule seul.",
        priceCents: 1890,
        metaDescription:
          'Lunettes de natation junior 6-12 ans, sangle double et réglage rapide.',
        image: '/images/demo/lunettes.svg',
        variants: [
          { color: 'Bleu', stock: 24 },
          { color: 'Rose', stock: 21 },
        ],
      },
    ],
  },
  {
    name: 'Accessoires',
    description: 'Le matériel qui structure une séance.',
    image: '/images/demo/accessoires.svg',
    products: [
      {
        name: 'Pull-buoy',
        description:
          "Mousse EVA haute densité, forme sablier. Bloque les jambes et reporte le travail sur les bras. Se coince entre les cuisses ou les chevilles selon l'exercice.",
        priceCents: 2190,
        stock: 35,
        metaDescription:
          'Pull-buoy en mousse EVA haute densité pour le travail des bras en natation.',
        image: '/images/demo/accessoires.svg',
      },
      {
        name: 'Plaquettes de traction',
        description:
          "Surface perforée pour sentir l'appui sans forcer sur l'épaule. Sangles silicone amovibles. Commencez par la taille en dessous de votre intuition.",
        priceCents: 2690,
        metaDescription:
          'Plaquettes de natation perforées avec sangles silicone, pour le travail de traction.',
        image: '/images/demo/accessoires.svg',
        variants: [
          { size: 'S', stock: 14 },
          { size: 'M', stock: 20 },
          { size: 'L', stock: 11 },
        ],
      },
      {
        name: 'Pince-nez',
        description:
          'Silicone souple sur armature métal, se déforme puis reprend sa forme. Indispensable en dos et en travail de coulée.',
        priceCents: 690,
        stock: 60,
        metaDescription:
          'Pince-nez de natation en silicone souple sur armature métal.',
        image: '/images/demo/accessoires.svg',
      },
      {
        name: 'Sac filet',
        description:
          "Maille large : le matériel sèche dedans, l'eau s'évacue. Contient une paire de palmes, un pull-buoy et des plaquettes.",
        priceCents: 1690,
        stock: 28,
        metaDescription:
          'Sac filet à maille large pour transporter et faire sécher le matériel de natation.',
        image: '/images/demo/accessoires.svg',
      },
    ],
  },
  {
    name: 'Vêtements',
    description: 'Maillots et textile résistants au chlore.',
    image: '/images/demo/vetements.svg',
    products: [
      {
        name: "Maillot d'entraînement femme",
        description:
          "Polyester résistant au chlore, dos nageur. Il garde sa tenue après des centaines de séances là où un maillot classique se détend en un trimestre.",
        priceCents: 4990,
        metaDescription:
          "Maillot de bain une pièce femme en polyester résistant au chlore, dos nageur.",
        image: '/images/demo/vetements.svg',
        variants: [
          { size: '36', stock: 8 },
          { size: '38', stock: 12 },
          { size: '40', stock: 10 },
          { size: '42', stock: 7 },
          { size: '44', stock: 5 },
        ],
      },
      {
        name: 'Jammer homme',
        description:
          "Coupe mi-cuisse, taille élastiquée avec cordon. Polyester résistant au chlore, coutures plates.",
        priceCents: 4490,
        salePriceCents: 3590,
        saleEndsAt: new Date(Date.now() + 1000 * 60 * 60 * 24 * 14),
        metaDescription:
          'Jammer de natation homme en polyester résistant au chlore, coupe mi-cuisse.',
        image: '/images/demo/vetements.svg',
        variants: [
          { size: '1 (28)', stock: 6 },
          { size: '2 (30)', stock: 11 },
          { size: '3 (32)', stock: 9 },
          { size: '4 (34)', stock: 4 },
        ],
      },
      {
        name: 'Serviette microfibre',
        description:
          "Absorbe trois fois son poids, sèche en une heure et tient dans une poche de sac. 80 × 130 cm, étui fourni.",
        priceCents: 2990,
        stock: 30,
        metaDescription:
          'Serviette de natation en microfibre 80 × 130 cm, séchage rapide, étui fourni.',
        image: '/images/demo/vetements.svg',
      },
    ],
  },
  {
    name: 'Livre',
    description: "Le livre de Melvin Maillot, fondateur de la marque.",
    image: '/images/demo/livre.svg',
    products: [
      {
        // Seul article réel du catalogue : il n'est pas vendu ici mais par la
        // Fnac, et disponible à la boutique.
        //
        // La description reste à compléter : la fiche Fnac refuse la lecture
        // automatisée (HTTP 403) et la page de l'éditeur ne publie ni résumé
        // ni prix. Rien n'a donc été repris, plutôt que d'inventer.
        name: 'Corps et esprit',
        description:
          "Le livre de Melvin Maillot, fondateur de BOUGE.\n\nRésumé à compléter depuis l'administration : ni la fiche du revendeur ni celle de l'éditeur ne le publient.",
        // Prix non affiché pour un produit vendu ailleurs : celui du
        // revendeur fait foi et peut changer sans que nous le sachions.
        priceCents: 0,
        metaDescription:
          'Corps et esprit, le livre de Melvin Maillot. Vendu par la Fnac et disponible en magasin.',
        image: '/images/demo/livre.svg',
        featured: true,
        externalUrl:
          'https://www.fnac.com/a23070255/Melvin-Maillot-Corps-et-esprit',
        externalLabel: 'la Fnac',
        availableInStore: true,
      },
    ],
  },
];

async function main() {
  // --- Compte administrateur ------------------------------------------------
  const adminEmail = process.env.ADMIN_EMAIL;
  const adminPassword = process.env.ADMIN_PASSWORD;

  if (!adminEmail || !adminPassword) {
    throw new Error(
      'ADMIN_EMAIL et ADMIN_PASSWORD doivent être définis dans .env pour créer le compte administrateur.',
    );
  }

  const passwordHash = await hash(adminPassword, 12);
  await prisma.adminUser.upsert({
    where: { email: adminEmail },
    update: { passwordHash },
    create: { email: adminEmail, passwordHash, name: 'Administration BOUGE.' },
  });
  console.log(`✓ Compte admin : ${adminEmail}`);

  // --- Catalogue ------------------------------------------------------------
  // On repart d'un catalogue vide. Les commandes existantes gardent leurs
  // libellés recopiés, elles restent donc parfaitement lisibles.
  await prisma.productVariant.deleteMany();
  await prisma.productImage.deleteMany();
  await prisma.product.deleteMany();
  await prisma.category.deleteMany();

  let productCount = 0;

  for (const [categoryIndex, category] of CATEGORIES.entries()) {
    const createdCategory = await prisma.category.create({
      data: {
        name: category.name,
        slug: slugify(category.name),
        description: category.description,
        position: categoryIndex,
      },
    });

    for (const [productIndex, product] of category.products.entries()) {
      await prisma.product.create({
        data: {
          name: product.name,
          slug: slugify(product.name),
          description: product.description,
          categoryId: createdCategory.id,
          priceCents: product.priceCents,
          salePriceCents: product.salePriceCents ?? null,
          saleEndsAt: product.saleEndsAt ?? null,
          status: 'PUBLISHED',
          stock: product.stock ?? 0,
          featured: product.featured ?? false,
          externalUrl: product.externalUrl ?? null,
          externalLabel: product.externalLabel ?? null,
          availableInStore: product.availableInStore ?? false,
          metaDescription: product.metaDescription,
          images: {
            create: [
              {
                url: product.image,
                alt: `${product.name} — ${category.name} BOUGE.`,
                position: 0,
              },
            ],
          },
          variants: product.variants
            ? {
                create: product.variants.map((variant, index) => ({
                  size: variant.size ?? null,
                  color: variant.color ?? null,
                  stock: variant.stock,
                  position: index,
                })),
              }
            : undefined,
        },
      });
      productCount += 1;
      void productIndex;
    }
  }

  console.log(
    `✓ Catalogue : ${CATEGORIES.length} catégories, ${productCount} produits`,
  );

  // --- Point de retrait -----------------------------------------------------
  const pickupCount = await prisma.pickupPoint.count();
  if (pickupCount === 0) {
    await prisma.pickupPoint.create({
      data: {
        name: 'Boutique BOUGE.',
        addressLine1: '12 rue de la Piscine',
        postalCode: '33000',
        city: 'Bordeaux',
        hours: 'Du mardi au samedi, 10h – 19h',
        position: 0,
      },
    });
    console.log('✓ Point de retrait de démonstration créé');
  }
}

main()
  .catch((error) => {
    console.error(error);
    process.exitCode = 1;
  })
  .finally(async () => {
    await prisma.$disconnect();
  });
