'use server';

import { redirect } from 'next/navigation';
import { createSession, verifyCredentials } from '@/lib/auth';

export type LoginState = {
  error?: string;
  /**
   * Adresse saisie, renvoyée telle quelle au formulaire.
   * React réinitialise les champs non contrôlés à la fin d'une action : sans
   * cela, l'utilisateur devrait retaper son adresse à chaque erreur.
   */
  email?: string;
};

/**
 * Connexion à l'administration.
 *
 * Le message d'erreur ne distingue jamais « courriel inconnu » de « mot de
 * passe incorrect » : indiquer lequel des deux est faux renseignerait un
 * visiteur mal intentionné sur l'existence du compte.
 */
export async function login(
  _previousState: LoginState,
  formData: FormData,
): Promise<LoginState> {
  const email = String(formData.get('email') ?? '');
  const password = String(formData.get('password') ?? '');
  const next = String(formData.get('suite') ?? '/admin');

  if (!email.trim() || !password) {
    return {
      error: 'Renseignez votre adresse et votre mot de passe.',
      email,
    };
  }

  const session = await verifyCredentials(email, password);
  if (!session) {
    return { error: 'Adresse ou mot de passe incorrect.', email };
  }

  await createSession(session);

  // On ne redirige que vers une page interne : un paramètre « suite » forgé ne
  // peut pas servir à renvoyer vers un site extérieur après connexion.
  redirect(next.startsWith('/admin') ? next : '/admin');
}
