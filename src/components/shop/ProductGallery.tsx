'use client';

import Image from 'next/image';
import { useState } from 'react';

export type GalleryImage = { id: string; url: string; alt: string };

/**
 * Galerie photo de la fiche produit.
 * Une image principale, et des vignettes en dessous dès qu'il y en a plusieurs.
 */
export function ProductGallery({
  images,
  productName,
}: {
  images: GalleryImage[];
  productName: string;
}) {
  const [activeIndex, setActiveIndex] = useState(0);

  if (images.length === 0) {
    return (
      <div className="flex aspect-4/5 items-center justify-center rounded-surface bg-sand text-sm text-ink-soft">
        Photo à venir
      </div>
    );
  }

  const active = images[activeIndex] ?? images[0];

  return (
    <div>
      <div className="relative aspect-4/5 overflow-hidden rounded-surface bg-sand">
        <Image
          src={active.url}
          alt={active.alt}
          fill
          priority
          sizes="(max-width: 1024px) 100vw, 50vw"
          className="object-cover"
        />
      </div>

      {images.length > 1 && (
        <ul className="mt-3 grid grid-cols-5 gap-3">
          {images.map((image, index) => (
            <li key={image.id}>
              <button
                type="button"
                onClick={() => setActiveIndex(index)}
                aria-label={`Voir la photo ${index + 1} de ${productName}`}
                aria-pressed={index === activeIndex}
                className={`relative block aspect-square w-full overflow-hidden rounded-sm bg-sand ring-offset-2 transition-opacity ${
                  index === activeIndex
                    ? 'ring-1 ring-ink'
                    : 'opacity-70 hover:opacity-100'
                }`}
              >
                <Image
                  src={image.url}
                  alt=""
                  fill
                  sizes="20vw"
                  className="object-cover"
                />
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
