import { redirect } from 'next/navigation';
import { getSession, type AdminSession } from '@/lib/auth';

/**
 * Exige une session administrateur.
 *
 * Appelée au début de chaque action serveur : le middleware protège la
 * navigation, mais une action serveur est une requête HTTP à part entière, qui
 * peut être déclenchée directement. Elle doit donc vérifier la session de son
 * côté.
 */
export async function requireAdmin(): Promise<AdminSession> {
  const session = await getSession();
  if (!session) redirect('/admin/connexion');
  return session;
}
