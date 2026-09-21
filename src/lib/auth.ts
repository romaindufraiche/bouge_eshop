import 'server-only';
import { compare } from 'bcryptjs';
import { SignJWT, jwtVerify } from 'jose';
import { cookies } from 'next/headers';
import { requireEnv } from '@/lib/env';
import { prisma } from '@/lib/prisma';

/**
 * Authentification de l'administration.
 *
 * Volontairement minimale : un compte, un mot de passe, un cookie de session
 * signé. Pas de fournisseur externe ni de dépendance supplémentaire à tenir à
 * jour pour un besoin qui se résume à « une seule personne accède à /admin ».
 *
 * Le cookie contient un jeton signé (JWT, HS256) et non un identifiant en
 * clair : il ne peut donc pas être fabriqué sans connaître AUTH_SECRET.
 */

const COOKIE_NAME = 'bouge_admin';
const SESSION_DURATION_HOURS = 12;

export type AdminSession = {
  userId: string;
  email: string;
};

function getSecret(): Uint8Array {
  const secret = requireEnv('AUTH_SECRET');

  if (secret.length < 32) {
    throw new Error(
      'AUTH_SECRET doit faire au moins 32 caractères. Générez-en un avec : openssl rand -base64 32',
    );
  }

  return new TextEncoder().encode(secret);
}

/** Vérifie les identifiants. Renvoie null si le couple est incorrect. */
export async function verifyCredentials(
  email: string,
  password: string,
): Promise<AdminSession | null> {
  const user = await prisma.adminUser.findUnique({
    where: { email: email.trim().toLowerCase() },
  });

  if (!user) {
    // On compare quand même contre un hachage factice : sans cela, le temps de
    // réponse révélerait quels courriels existent.
    await compare(password, '$2b$12$invalidinvalidinvalidinvalidinvalidinvalidinvalidinv');
    return null;
  }

  const isValid = await compare(password, user.passwordHash);
  if (!isValid) return null;

  return { userId: user.id, email: user.email };
}

/** Ouvre une session : pose le cookie signé. */
export async function createSession(session: AdminSession): Promise<void> {
  const token = await new SignJWT({ email: session.email })
    .setProtectedHeader({ alg: 'HS256' })
    .setSubject(session.userId)
    .setIssuedAt()
    .setExpirationTime(`${SESSION_DURATION_HOURS}h`)
    .sign(getSecret());

  const store = await cookies();
  store.set(COOKIE_NAME, token, {
    httpOnly: true, // inaccessible au JavaScript de la page
    secure: process.env.NODE_ENV === 'production',
    sameSite: 'lax',
    path: '/',
    maxAge: SESSION_DURATION_HOURS * 60 * 60,
  });
}

export async function destroySession(): Promise<void> {
  const store = await cookies();
  store.delete(COOKIE_NAME);
}

/** Session en cours, ou null si le jeton est absent, expiré ou invalide. */
export async function getSession(): Promise<AdminSession | null> {
  const store = await cookies();
  const token = store.get(COOKIE_NAME)?.value;
  if (!token) return null;

  return verifySessionToken(token);
}

/**
 * Vérifie un jeton de session.
 * Isolée de `cookies()` pour être également utilisable depuis le middleware,
 * qui s'exécute hors du contexte de rendu.
 */
export async function verifySessionToken(
  token: string,
): Promise<AdminSession | null> {
  try {
    const { payload } = await jwtVerify(token, getSecret(), {
      algorithms: ['HS256'],
    });

    if (!payload.sub || typeof payload.email !== 'string') return null;

    return { userId: payload.sub, email: payload.email };
  } catch {
    // Jeton expiré, signature invalide, secret changé : session refusée.
    return null;
  }
}

export const ADMIN_COOKIE_NAME = COOKIE_NAME;
