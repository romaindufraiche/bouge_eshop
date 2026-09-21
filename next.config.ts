import type { NextConfig } from 'next';

const nextConfig: NextConfig = {
  /**
   * Prisma embarque un moteur de requête natif et lit des fichiers à
   * l'exécution. Le laisser hors du bundle évite que Next.js trace — et
   * déploie — l'intégralité du projet, dossier public compris.
   */
  serverExternalPackages: ['@prisma/client', 'prisma'],

  images: {
    /**
     * Domaines autorisés pour <Image>. En développement, les photos produits
     * vivent dans /public/uploads et ne passent pas par ici.
     * Décommenter la ligne correspondant au service choisi le jour où
     * UPLOAD_DRIVER bascule sur un stockage externe.
     */
    remotePatterns: [
      // { protocol: 'https', hostname: 'res.cloudinary.com' },
    ],
  },
};

export default nextConfig;
