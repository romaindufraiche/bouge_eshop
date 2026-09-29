<?php

declare(strict_types=1);

namespace Bouge\Support;

use RuntimeException;
use ZipArchive;

/**
 * Écriture d'un classeur Excel, sans bibliothèque.
 *
 * Un fichier .xlsx est une archive ZIP contenant quelques documents XML. Les
 * écrire à la main tient en deux cents lignes ; ajouter PhpSpreadsheet pour
 * cela chargerait une centaine de fichiers dans `vendor/` sur un hébergement
 * mutualisé, pour une poignée de colonnes.
 *
 * Le choix du vrai classeur plutôt que d'un CSV n'est pas cosmétique : dans un
 * CSV, un stock et un prix arrivent en texte, et Excel francophone se trompe
 * régulièrement sur le séparateur décimal. Un état des stocks dont on ne peut
 * pas faire la somme ne sert à rien.
 *
 * Volontairement limité : une feuille, une ligne d'en-tête, des nombres et du
 * texte. Ni formule, ni couleur, ni date.
 */
final class Xlsx
{
    /**
     * @param list<string>            $entetes
     * @param list<list<string|int|float|null>> $lignes
     * @return string Le contenu binaire du fichier .xlsx
     */
    public static function build(array $entetes, array $lignes, string $feuille = 'Feuille 1'): string
    {
        if (!extension_loaded('zip')) {
            throw new RuntimeException(
                "L'extension PHP « zip » est nécessaire pour produire un fichier Excel. "
                . 'Demandez son activation à votre hébergeur.'
            );
        }

        $fichier = tempnam(sys_get_temp_dir(), 'xlsx');

        if ($fichier === false) {
            throw new RuntimeException("Impossible de créer le fichier temporaire du classeur.");
        }

        $zip = new ZipArchive();

        if ($zip->open($fichier, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Impossible d'ouvrir l'archive du classeur.");
        }

        $zip->addFromString('[Content_Types].xml', self::contentTypes());
        $zip->addFromString('_rels/.rels', self::relsRacine());
        $zip->addFromString('xl/workbook.xml', self::classeur($feuille));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::relsClasseur());
        $zip->addFromString('xl/styles.xml', self::styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', self::feuille($entetes, $lignes));
        $zip->close();

        $contenu = (string) file_get_contents($fichier);
        unlink($fichier);

        return $contenu;
    }

    // --- Les documents de l'archive -----------------------------------------

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private static function relsRacine(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private static function classeur(string $feuille): string
    {
        // Excel refuse les noms de feuille de plus de 31 caractères, et
        // certains caractères y sont interdits.
        $nom = mb_substr(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $feuille), 0, 31);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . self::echappe($nom) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private static function relsClasseur(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    /**
     * Deux styles : le texte ordinaire, et l'en-tête en gras. Excel exige les
     * tables de polices, remplissages et bordures même vides.
     */
    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
            . '<cellXfs count="2"><xf xfId="0"/><xf xfId="0" fontId="1" applyFont="1"/></cellXfs>'
            // Le style « Normal » est réclamé par les lecteurs stricts : sans
            // lui, ils se plaignent d'un classeur sans style par défaut.
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    /**
     * @param list<string> $entetes
     * @param list<list<string|int|float|null>> $lignes
     */
    private static function feuille(array $entetes, array $lignes): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            // Les volets figés : l'en-tête reste visible en faisant défiler.
            . '<sheetViews><sheetView workbookViewId="0">'
            . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
            . '</sheetView></sheetViews>'
            . '<sheetData>';

        $xml .= self::ligne(1, $entetes, true);

        foreach ($lignes as $index => $cellules) {
            $xml .= self::ligne($index + 2, $cellules, false);
        }

        $xml .= '</sheetData>'
            // Le filtre automatique sur l'en-tête : de quoi trier par stock en
            // deux clics, ce qu'on fait toujours devant un état des stocks.
            . '<autoFilter ref="A1:' . self::colonne(count($entetes)) . (count($lignes) + 1) . '"/>'
            . '</worksheet>';

        return $xml;
    }

    /** @param list<string|int|float|null> $cellules */
    private static function ligne(int $numero, array $cellules, bool $entete): string
    {
        $xml = '<row r="' . $numero . '">';

        foreach (array_values($cellules) as $index => $valeur) {
            $reference = self::colonne($index + 1) . $numero;
            $style = $entete ? ' s="1"' : '';

            if ($valeur === null || $valeur === '') {
                $xml .= '<c r="' . $reference . '"' . $style . '/>';
                continue;
            }

            if (!$entete && is_numeric($valeur) && !is_string($valeur)) {
                // Un nombre reste un nombre : c'est tout l'intérêt d'un vrai
                // classeur par rapport à un CSV.
                $xml .= '<c r="' . $reference . '"' . $style . '><v>' . $valeur . '</v></c>';
                continue;
            }

            $xml .= '<c r="' . $reference . '"' . $style . ' t="inlineStr"><is><t xml:space="preserve">'
                . self::echappe((string) $valeur) . '</t></is></c>';
        }

        return $xml . '</row>';
    }

    /** 1 → A, 26 → Z, 27 → AA. */
    private static function colonne(int $numero): string
    {
        $nom = '';

        while ($numero > 0) {
            $reste = ($numero - 1) % 26;
            $nom = chr(65 + $reste) . $nom;
            $numero = intdiv($numero - 1 - $reste, 26);
        }

        return $nom;
    }

    private static function echappe(string $texte): string
    {
        // Les caractères de contrôle font refuser le fichier par Excel, sans
        // message utile : autant les retirer ici.
        $texte = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $texte);

        return htmlspecialchars($texte, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
