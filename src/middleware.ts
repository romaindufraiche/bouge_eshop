import { NextResponse, type NextRequest } from 'next/server';
import { ADMIN_COOKIE_NAME, verifySessionToken } from '@/lib/auth';

/**
 * Protection de l'administration.
 *
 * Le middleware bloque l'accès avant même que la page ne soit rendue : une
 * page d'admin ne peut donc pas fuiter par mégarde si l'on oublie de vérifier
 * la session dans l'un de ses composants.
 *
 * Il ne fait que vérifier la signature du jeton — pas d'accès à la base ici,
 * le middleware s'exécutant dans un contexte restreint.
 */
export async function middleware(request: NextRequest) {
  const token = request.cookies.get(ADMIN_COOKIE_NAME)?.value;
  const session = token ? await verifySessionToken(token) : null;

  const isLoginPage = request.nextUrl.pathname === '/admin/connexion';

  if (!session && !isLoginPage) {
    const loginUrl = new URL('/admin/connexion', request.url);
    // On mémorise la page demandée pour y revenir après connexion.
    loginUrl.searchParams.set('suite', request.nextUrl.pathname);
    return NextResponse.redirect(loginUrl);
  }

  // Déjà connecté : inutile de réafficher le formulaire de connexion.
  if (session && isLoginPage) {
    return NextResponse.redirect(new URL('/admin', request.url));
  }

  return NextResponse.next();
}

export const config = {
  matcher: ['/admin/:path*'],
};
