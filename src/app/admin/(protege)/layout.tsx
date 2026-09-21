import Link from 'next/link';
import { redirect } from 'next/navigation';
import type { ReactNode } from 'react';
import { logout } from '@/app/admin/(protege)/actions';
import { AdminNav } from '@/components/admin/AdminNav';
import { Container } from '@/components/ui/Container';
import { getSession } from '@/lib/auth';

/**
 * Mise en page des écrans protégés de l'administration.
 *
 * Le middleware bloque déjà l'accès en amont ; la vérification est refaite ici
 * pour que ces pages ne puissent pas s'afficher sans session, même si la
 * configuration du middleware venait à changer.
 */
export default async function ProtectedAdminLayout({
  children,
}: {
  children: ReactNode;
}) {
  const session = await getSession();
  if (!session) redirect('/admin/connexion');

  return (
    <>
      <header className="border-b border-line">
        <Container size="wide">
          <div className="flex flex-wrap items-center justify-between gap-3 py-4">
            <div className="flex items-baseline gap-3">
              <Link href="/admin" className="font-display text-xl tracking-tight">
                BOUGE.
              </Link>
              <span className="text-xs uppercase tracking-widest text-ink-soft">
                Administration
              </span>
            </div>

            <div className="flex items-center gap-4 text-sm">
              <Link
                href="/"
                target="_blank"
                rel="noreferrer"
                className="text-ink-soft hover:text-ink"
              >
                Voir la boutique
              </Link>
              <span className="hidden text-ink-soft sm:inline">
                {session.email}
              </span>
              <form action={logout}>
                <button type="submit" className="underline underline-offset-4">
                  Se déconnecter
                </button>
              </form>
            </div>
          </div>

          <AdminNav />
        </Container>
      </header>

      <main className="flex-1 pb-24">
        <Container size="wide">
          <div className="pt-8">{children}</div>
        </Container>
      </main>
    </>
  );
}
