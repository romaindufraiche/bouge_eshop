import { PrismaClient } from '@prisma/client';

/**
 * Client Prisma partagé.
 *
 * En développement, Next.js recharge les modules à chaud à chaque modification.
 * Sans ce cache sur `globalThis`, chaque rechargement ouvrirait une nouvelle
 * connexion à la base et on finirait par saturer le pool.
 */
const globalForPrisma = globalThis as unknown as {
  prisma: PrismaClient | undefined;
};

export const prisma =
  globalForPrisma.prisma ??
  new PrismaClient({
    log: process.env.NODE_ENV === 'development' ? ['warn', 'error'] : ['error'],
  });

if (process.env.NODE_ENV !== 'production') {
  globalForPrisma.prisma = prisma;
}

// Ré-export des types générés : le reste de l'application importe depuis
// `@/lib/prisma` et n'a jamais besoin de connaître le dossier de génération.
export type {
  AdminUser,
  Category,
  Order,
  OrderItem,
  PickupPoint,
  Product,
  ProductImage,
  ProductVariant,
} from '@prisma/client';
