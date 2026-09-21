'use client';

import Image from 'next/image';
import { useActionState, useRef } from 'react';
import {
  deleteImage,
  moveImage,
  updateImageAlt,
  uploadImages,
  type ImageActionState,
} from '@/app/admin/(protege)/produits/image-actions';
import { Button } from '@/components/ui/Button';
import {
  IMAGE_ACCEPT_ATTRIBUTE,
  MAX_IMAGE_MEGABYTES,
} from '@/lib/upload-constants';

export type AdminImage = {
  id: string;
  url: string;
  alt: string;
};

const INITIAL_STATE: ImageActionState = {};

/**
 * Gestion des photos d'un produit : envoi multiple, ordre, texte alternatif,
 * suppression.
 *
 * L'ordre se règle avec des boutons « monter » et « descendre » plutôt qu'en
 * glisser-déposer : c'est utilisable au clavier, sur mobile, et sans
 * apprentissage.
 */
export function ProductImages({
  productId,
  images,
}: {
  productId: string;
  images: AdminImage[];
}) {
  const [state, formAction, isPending] = useActionState(
    uploadImages,
    INITIAL_STATE,
  );
  const fileInputRef = useRef<HTMLInputElement>(null);

  return (
    <div className="space-y-6">
      {/* --- Envoi ------------------------------------------------------- */}
      <form action={formAction} className="space-y-3">
        <input type="hidden" name="productId" value={productId} />

        <label htmlFor="photos" className="block text-sm font-medium">
          Ajouter des photos
        </label>
        <input
          ref={fileInputRef}
          id="photos"
          type="file"
          name="files"
          multiple
          accept={IMAGE_ACCEPT_ATTRIBUTE}
          className="block w-full text-sm file:mr-4 file:rounded-sm file:border file:border-ink file:bg-transparent file:px-4 file:py-2 file:text-sm"
        />
        <p className="text-sm text-ink-soft">
          JPEG, PNG, WebP ou AVIF. {MAX_IMAGE_MEGABYTES} Mo maximum par photo.
          La première de la liste sert de photo principale.
        </p>

        <Button type="submit" variant="secondary" disabled={isPending}>
          {isPending ? 'Envoi…' : 'Envoyer'}
        </Button>

        {state.error && (
          <p role="alert" className="text-sm text-accent">
            {state.error}
          </p>
        )}
        {state.message && (
          <p role="status" className="text-sm">
            {state.message}
          </p>
        )}
      </form>

      {/* --- Galerie ------------------------------------------------------ */}
      {images.length === 0 ? (
        <p className="text-sm text-ink-soft">
          Aucune photo pour l&apos;instant.
        </p>
      ) : (
        <ul className="space-y-3">
          {images.map((image, index) => (
            <li
              key={image.id}
              className="flex flex-wrap items-start gap-4 border border-line p-3"
            >
              <span className="relative aspect-square w-20 shrink-0 overflow-hidden bg-sand">
                <Image
                  src={image.url}
                  alt=""
                  fill
                  sizes="80px"
                  className="object-cover"
                />
              </span>

              <div className="min-w-0 flex-1 space-y-2">
                <p className="text-xs uppercase tracking-widest text-ink-soft">
                  {index === 0 ? 'Photo principale' : `Photo ${index + 1}`}
                </p>

                {/* Texte alternatif : lu par les lecteurs d'écran et pris en
                    compte par Google. */}
                <form action={updateImageAlt} className="flex flex-wrap gap-2">
                  <input type="hidden" name="imageId" value={image.id} />
                  <label className="min-w-0 flex-1">
                    <span className="sr-only">
                      Description de la photo {index + 1}
                    </span>
                    <input
                      type="text"
                      name="alt"
                      defaultValue={image.alt}
                      maxLength={200}
                      placeholder="Ce que montre la photo"
                      className="w-full rounded-sm border border-line bg-cream px-3 py-2 text-sm"
                    />
                  </label>
                  <button
                    type="submit"
                    className="rounded-sm border border-line px-3 py-2 text-sm hover:border-ink"
                  >
                    Enregistrer
                  </button>
                </form>
              </div>

              <div className="flex shrink-0 items-center gap-2">
                <MoveButton
                  imageId={image.id}
                  direction="up"
                  disabled={index === 0}
                  label={`Monter la photo ${index + 1}`}
                />
                <MoveButton
                  imageId={image.id}
                  direction="down"
                  disabled={index === images.length - 1}
                  label={`Descendre la photo ${index + 1}`}
                />

                <form action={deleteImage}>
                  <input type="hidden" name="imageId" value={image.id} />
                  <button
                    type="submit"
                    className="px-2 py-1 text-sm text-accent underline underline-offset-4"
                  >
                    Supprimer
                  </button>
                </form>
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

function MoveButton({
  imageId,
  direction,
  disabled,
  label,
}: {
  imageId: string;
  direction: 'up' | 'down';
  disabled: boolean;
  label: string;
}) {
  return (
    <form action={moveImage}>
      <input type="hidden" name="imageId" value={imageId} />
      <input type="hidden" name="direction" value={direction} />
      <button
        type="submit"
        disabled={disabled}
        className="rounded-sm border border-line px-2.5 py-1.5 text-sm disabled:opacity-30"
      >
        <span aria-hidden="true">{direction === 'up' ? '↑' : '↓'}</span>
        <span className="sr-only">{label}</span>
      </button>
    </form>
  );
}
