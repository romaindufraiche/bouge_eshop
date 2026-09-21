import { randomInt } from 'node:crypto';

// Alphabet sans I, O, 0 ni 1 : une référence lue au téléphone ou recopiée à la
// main ne prête pas à confusion.
const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

/** Référence lisible communiquée au client, du type « BG-7F3K2A ». */
export function generateOrderReference(): string {
  let suffix = '';
  for (let index = 0; index < 6; index += 1) {
    suffix += ALPHABET[randomInt(ALPHABET.length)];
  }
  return `BG-${suffix}`;
}
