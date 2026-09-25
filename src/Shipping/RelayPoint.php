<?php

declare(strict_types=1);

namespace Bouge\Shipping;

/**
 * Un point relais, tel que le transporteur le décrit.
 *
 * Objet de lecture seule : il est construit à partir de la réponse du
 * transporteur, montré au client, puis recopié sur la commande. Aucune table
 * ne le stocke — il appartient au transporteur, pas à la boutique, et son
 * existence n'est garantie que le jour de la commande.
 */
final class RelayPoint
{
    public function __construct(
        /** Identifiant chez le transporteur, à lui redonner pour l'étiquette. */
        public readonly string $code,
        /** Mondial Relay, Relais Colis, Chronopost… */
        public readonly string $operator,
        public readonly string $name,
        public readonly string $address,
        public readonly string $postalCode,
        public readonly string $city,
        /** Horaires en clair, ligne par ligne. Peut être vide. */
        public readonly array $schedule = [],
        /** Distance depuis l'adresse cherchée, en mètres. Null si inconnue. */
        public readonly ?int $distanceMeters = null,
    ) {
    }

    /** Adresse sur une ligne, pour un récapitulatif ou un courriel. */
    public function oneLine(): string
    {
        return sprintf(
            '%s, %s, %s %s',
            $this->name,
            $this->address,
            $this->postalCode,
            $this->city
        );
    }

    /** « 450 m » ou « 1,2 km », selon ce qui se lit le mieux. */
    public function distance(): ?string
    {
        if ($this->distanceMeters === null) {
            return null;
        }

        if ($this->distanceMeters < 1000) {
            return $this->distanceMeters . ' m';
        }

        return str_replace('.', ',', (string) round($this->distanceMeters / 1000, 1)) . ' km';
    }

    /** Les champs recopiés sur la commande. @return array<string, string> */
    public function toOrderColumns(): array
    {
        return [
            'relay_code'        => $this->code,
            'relay_operator'    => $this->operator,
            'relay_name'        => $this->name,
            'relay_address'     => $this->address,
            'relay_postal_code' => $this->postalCode,
            'relay_city'        => $this->city,
        ];
    }
}
