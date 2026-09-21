'use client';

import { useActionState } from 'react';
import { login, type LoginState } from '@/app/admin/connexion/actions';
import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';

const INITIAL_STATE: LoginState = {};

export function LoginForm({ next }: { next?: string }) {
  const [state, formAction, isPending] = useActionState(login, INITIAL_STATE);

  return (
    <form action={formAction} className="space-y-5">
      <input type="hidden" name="suite" value={next ?? '/admin'} />

      {state.error && (
        <p role="alert" className="border-l-2 border-accent bg-sand px-4 py-3 text-sm">
          {state.error}
        </p>
      )}

      <Field label="Adresse électronique" name="email" required>
        {(props) => (
          <input
            type="email"
            autoComplete="username"
            autoFocus
            defaultValue={state.email ?? ''}
            {...props}
          />
        )}
      </Field>

      <Field label="Mot de passe" name="password" required>
        {(props) => (
          <input type="password" autoComplete="current-password" {...props} />
        )}
      </Field>

      <Button type="submit" size="lg" className="w-full" disabled={isPending}>
        {isPending ? 'Connexion…' : 'Se connecter'}
      </Button>
    </form>
  );
}
