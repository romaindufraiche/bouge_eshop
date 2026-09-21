'use client';

import { useEffect, useRef, useState } from 'react';
import { useFormStatus } from 'react-dom';

/**
 * Bouton de suppression avec confirmation.
 *
 * On demande systématiquement une confirmation explicite, en rappelant le nom
 * exact de l'élément concerné : supprimer un produit d'un catalogue n'est pas
 * rattrapable depuis l'interface.
 */
export function ConfirmButton({
  label,
  title,
  message,
  confirmLabel = 'Supprimer',
}: {
  /** Libellé du bouton qui ouvre la confirmation. */
  label: string;
  /** Titre de la fenêtre de confirmation. */
  title: string;
  /** Conséquence exacte de l'action, en une phrase. */
  message: string;
  confirmLabel?: string;
}) {
  const dialogRef = useRef<HTMLDialogElement>(null);
  const [isOpen, setIsOpen] = useState(false);

  function open() {
    setIsOpen(true);
    dialogRef.current?.showModal();
  }

  function close() {
    dialogRef.current?.close();
    setIsOpen(false);
  }

  return (
    <>
      <button
        type="button"
        onClick={open}
        className="text-sm text-accent underline underline-offset-4"
      >
        {label}
      </button>

      <dialog
        ref={dialogRef}
        onClose={() => setIsOpen(false)}
        className="m-auto w-[min(28rem,calc(100vw-2rem))] border border-ink bg-cream p-6 text-ink backdrop:bg-ink/40"
      >
        {/* Le contenu n'est monté que fenêtre ouverte : le bouton de
            confirmation ne peut donc pas être déclenché depuis la page. */}
        {isOpen && (
          <>
            <h2 className="text-xl">{title}</h2>
            <p className="mt-3 text-sm text-ink-soft">{message}</p>

            <div className="mt-6 flex justify-end gap-3">
              <button
                type="button"
                onClick={close}
                className="rounded-sm border border-ink px-4 py-2 text-sm"
              >
                Annuler
              </button>
              <SubmitButton label={confirmLabel} onFinished={close} />
            </div>
          </>
        )}
      </dialog>
    </>
  );
}

function SubmitButton({
  label,
  onFinished,
}: {
  label: string;
  onFinished: () => void;
}) {
  const { pending } = useFormStatus();
  const wasPending = useRef(false);

  // La fenêtre se referme dès que l'action serveur a répondu. Sans cela elle
  // resterait ouverte au-dessus d'une liste déjà mise à jour, donnant
  // l'impression que rien ne s'est passé.
  useEffect(() => {
    if (wasPending.current && !pending) onFinished();
    wasPending.current = pending;
  }, [pending, onFinished]);

  return (
    <button
      type="submit"
      disabled={pending}
      className="rounded-sm border border-accent bg-accent px-4 py-2 text-sm text-cream disabled:opacity-50"
    >
      {pending ? 'Suppression…' : label}
    </button>
  );
}
