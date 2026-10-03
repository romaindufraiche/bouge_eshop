<?php

declare(strict_types=1);

namespace Bouge\Support;

/**
 * Un écrivain de PDF minimal.
 *
 * Écrit à la main, comme le classeur Excel de `Xlsx.php`, et pour la même
 * raison : une facture tient en une page de texte, de filets et de rectangles.
 * Les bibliothèques du marché — FPDF, Dompdf, TCPDF — chargent de quelques
 * dizaines à plusieurs centaines de fichiers pour fabriquer des documents
 * autrement plus ambitieux. Ici, trois primitives suffisent.
 *
 * Un PDF est une suite d'objets numérotés, un flux de commandes de dessin, et
 * une table (le « xref ») qui donne la position de chaque objet en octets
 * depuis le début du fichier. C'est cette table qui impose d'assembler le
 * document en une fois, à la fin.
 *
 * Les polices employées sont deux des quatorze polices de base, présentes
 * dans tout lecteur : rien à incorporer. Elles s'écrivent en WinAnsi, qui
 * couvre le français — l'encodage se fait donc au dernier moment.
 */
final class Pdf
{
    /** A4 en points typographiques (1 pt = 1/72 pouce). */
    public const LARGEUR = 595.28;
    public const HAUTEUR = 841.89;

    public const REGULIER = 'F1';
    public const GRAS = 'F2';

    /** @var list<string> Les pages déjà closes. */
    private array $pages = [];

    /** Le flux de dessin de la page en cours. */
    private string $flux = '';


    /**
     * Chasses d'Helvetica, en millièmes de cadratin (fichiers AFM d'Adobe).
     *
     * @var array<string, int>
     */
    private const CHASSES = [
        ' ' => 278, '!' => 278, '"' => 355, '#' => 556, '$' => 556, '%' => 889, '&' => 667, "'" => 191,
        '(' => 333, ')' => 333, '*' => 389, '+' => 584, ',' => 278, '-' => 333, '.' => 278, '/' => 278,
        '0' => 556, '1' => 556, '2' => 556, '3' => 556, '4' => 556, '5' => 556, '6' => 556, '7' => 556,
        '8' => 556, '9' => 556, ':' => 278, ';' => 278, '<' => 584, '=' => 584, '>' => 584, '?' => 556,
        '@' => 1015,
        'A' => 667, 'B' => 667, 'C' => 722, 'D' => 722, 'E' => 667, 'F' => 611, 'G' => 778, 'H' => 722,
        'I' => 278, 'J' => 500, 'K' => 667, 'L' => 556, 'M' => 833, 'N' => 722, 'O' => 778, 'P' => 667,
        'Q' => 778, 'R' => 722, 'S' => 667, 'T' => 611, 'U' => 722, 'V' => 667, 'W' => 944, 'X' => 667,
        'Y' => 667, 'Z' => 611,
        '[' => 278, '\\' => 278, ']' => 278, '^' => 469, '_' => 556, '`' => 333,
        'a' => 556, 'b' => 556, 'c' => 500, 'd' => 556, 'e' => 556, 'f' => 278, 'g' => 556, 'h' => 556,
        'i' => 222, 'j' => 222, 'k' => 500, 'l' => 222, 'm' => 833, 'n' => 556, 'o' => 556, 'p' => 556,
        'q' => 556, 'r' => 333, 's' => 500, 't' => 278, 'u' => 556, 'v' => 500, 'w' => 722, 'x' => 500,
        'y' => 500, 'z' => 500,
        '{' => 334, '|' => 260, '}' => 334, '~' => 584,
        '€' => 556, '—' => 1000, '–' => 556, '·' => 278, '…' => 1000, '«' => 556, '»' => 556,
        '’' => 191, '‘' => 191, '“' => 333, '”' => 333,
    ];

    /** @var array<string, int> */
    private const CHASSES_GRAS = [
        ' ' => 278, '!' => 333, '"' => 474, '#' => 556, '$' => 556, '%' => 889, '&' => 722, "'" => 238,
        '(' => 333, ')' => 333, '*' => 389, '+' => 584, ',' => 278, '-' => 333, '.' => 278, '/' => 278,
        '0' => 556, '1' => 556, '2' => 556, '3' => 556, '4' => 556, '5' => 556, '6' => 556, '7' => 556,
        '8' => 556, '9' => 556, ':' => 333, ';' => 333, '<' => 584, '=' => 584, '>' => 584, '?' => 611,
        '@' => 975,
        'A' => 722, 'B' => 722, 'C' => 722, 'D' => 722, 'E' => 667, 'F' => 611, 'G' => 778, 'H' => 722,
        'I' => 278, 'J' => 556, 'K' => 722, 'L' => 611, 'M' => 833, 'N' => 722, 'O' => 778, 'P' => 667,
        'Q' => 778, 'R' => 722, 'S' => 667, 'T' => 611, 'U' => 722, 'V' => 667, 'W' => 944, 'X' => 667,
        'Y' => 667, 'Z' => 611,
        '[' => 333, '\\' => 278, ']' => 333, '^' => 584, '_' => 556, '`' => 333,
        'a' => 556, 'b' => 611, 'c' => 556, 'd' => 611, 'e' => 556, 'f' => 333, 'g' => 611, 'h' => 611,
        'i' => 278, 'j' => 278, 'k' => 556, 'l' => 278, 'm' => 889, 'n' => 611, 'o' => 611, 'p' => 611,
        'q' => 611, 'r' => 389, 's' => 556, 't' => 333, 'u' => 611, 'v' => 556, 'w' => 778, 'x' => 556,
        'y' => 556, 'z' => 500,
        '{' => 389, '|' => 280, '}' => 389, '~' => 584,
        '€' => 556, '—' => 1000, '–' => 556, '·' => 278, '…' => 1000, '«' => 556, '»' => 556,
        '’' => 238, '‘' => 238, '“' => 500, '”' => 500,
    ];

    /**
     * Les lettres accentuées prennent la chasse de leur lettre de base.
     *
     * @var array<string, string>
     */
    private const SANS_ACCENT = [
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n', 'ÿ' => 'y',
        'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Á' => 'A',
        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'Î' => 'I', 'Ï' => 'I', 'Í' => 'I',
        'Ô' => 'O', 'Ö' => 'O', 'Ó' => 'O',
        'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'Ç' => 'C', 'Ñ' => 'N',
    ];

    public function __construct()
    {
    }

    /**
     * Écrit du texte. L'origine d'un PDF est en bas à gauche ; `$y` se compte
     * donc depuis le bas de la page.
     */
    public function texte(float $x, float $y, string $texte, float $corps = 10, string $police = self::REGULIER, string $couleur = '0 0 0'): void
    {
        $this->flux .= sprintf(
            "BT /%s %.2F Tf %s rg %.2F %.2F Td (%s) Tj ET\n",
            $police,
            $corps,
            $couleur,
            $x,
            $y,
            $this->echapper($texte)
        );
    }

    /** Écrit du texte calé à droite de l'abscisse donnée. */
    public function texteDroite(float $droite, float $y, string $texte, float $corps = 10, string $police = self::REGULIER, string $couleur = '0 0 0'): void
    {
        $this->texte($droite - $this->largeurTexte($texte, $corps, $police), $y, $texte, $corps, $police, $couleur);
    }

    public function filet(float $x1, float $y1, float $x2, float $y2, float $epaisseur = 0.5, string $couleur = '0.8 0.78 0.72'): void
    {
        $this->flux .= sprintf(
            "%.2F w %s RG %.2F %.2F m %.2F %.2F l S\n",
            $epaisseur,
            $couleur,
            $x1,
            $y1,
            $x2,
            $y2
        );
    }

    public function rectangle(float $x, float $y, float $largeur, float $hauteur, string $couleur = '0.95 0.93 0.86'): void
    {
        $this->flux .= sprintf("%s rg %.2F %.2F %.2F %.2F re f\n", $couleur, $x, $y, $largeur, $hauteur);
    }

    /**
     * Largeur exacte d'une chaîne, en points.
     *
     * Les chasses sont celles des fichiers de métriques d'Helvetica, en
     * millièmes de cadratin. Une première version multipliait le nombre de
     * caractères par un facteur moyen : « BOUGE » en capitales grasses y
     * mesurait 55 points au lieu de 72, et le mot « Club » lui rentrait
     * dedans. Dans un document où les colonnes doivent s'aligner, une
     * approximation ne tient pas.
     *
     * Les lettres accentuées ont la chasse de leur lettre de base — c'est
     * vrai dans Helvetica, l'accent ne déborde pas.
     */
    public function largeurTexte(string $texte, float $corps, string $police = self::REGULIER): float
    {
        $chasses = $police === self::GRAS ? self::CHASSES_GRAS : self::CHASSES;
        $total = 0;

        foreach (preg_split('//u', $texte, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $caractere) {
            $base = self::SANS_ACCENT[$caractere] ?? $caractere;
            // 556 : la chasse d'un caractère courant, pour ce que la table
            // ignore. Mieux vaut une colonne large d'un cheveu qu'un calcul
            // qui s'effondre sur un symbole inattendu.
            $total += $chasses[$base] ?? 556;
        }

        return $total * $corps / 1000;
    }

    /** Clôt la page en cours et en ouvre une nouvelle. */
    public function nouvellePage(): void
    {
        $this->pages[] = $this->flux;
        $this->flux = '';
    }

    /** Assemble le document complet. */
    public function rendu(): string
    {
        $pages = $this->pages;
        $pages[] = $this->flux;

        $objets = [];
        // 1 : catalogue, 2 : arbre des pages, 3 et 4 : les polices.
        // Les pages et leurs flux suivent, deux objets par page.
        $premierePage = 5;
        $refsPages = [];

        foreach (array_keys($pages) as $i) {
            $refsPages[] = ($premierePage + $i * 2) . ' 0 R';
        }

        $objets[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objets[2] = '<< /Type /Pages /Kids [' . implode(' ', $refsPages) . '] /Count ' . count($pages) . ' >>';
        $objets[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objets[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        foreach ($pages as $i => $flux) {
            $numeroPage = $premierePage + $i * 2;
            $numeroFlux = $numeroPage + 1;

            $objets[$numeroPage] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] '
                . '/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                self::LARGEUR,
                self::HAUTEUR,
                $numeroFlux
            );

            $objets[$numeroFlux] = '<< /Length ' . strlen($flux) . " >>\nstream\n" . $flux . "endstream";
        }

        ksort($objets);

        $pdf = "%PDF-1.4\n";
        $positions = [];

        foreach ($objets as $numero => $contenu) {
            $positions[$numero] = strlen($pdf);
            $pdf .= $numero . " 0 obj\n" . $contenu . "\nendobj\n";
        }

        $depart = strlen($pdf);
        $total = count($objets) + 1;

        $pdf .= "xref\n0 {$total}\n0000000000 65535 f \n";

        for ($i = 1; $i < $total; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $positions[$i]);
        }

        $pdf .= "trailer\n<< /Size {$total} /Root 1 0 R >>\nstartxref\n{$depart}\n%%EOF\n";

        return $pdf;
    }

    /**
     * Prépare une chaîne pour un flux PDF : passage en WinAnsi, puis
     * échappement des trois caractères qui ont un sens dans la syntaxe —
     * la barre oblique inverse et les deux parenthèses.
     */
    private function echapper(string $texte): string
    {
        $winAnsi = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $texte);

        if ($winAnsi === false) {
            // Plutôt une facture sans accents qu'une facture illisible.
            $winAnsi = (string) @iconv('UTF-8', 'ASCII//TRANSLIT', $texte);
        }

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $winAnsi);
    }
}
