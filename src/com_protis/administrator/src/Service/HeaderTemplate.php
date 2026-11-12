<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * Intestazione del timesheet configurabile per progetto.
 *
 * Ogni progetto ha un elenco ordinato di righe {label, value}. Il valore può contenere testo fisso
 * e/o campi automatici tra parentesi graffe, sostituiti per ogni persona e mese, es.:
 *   "Nominativo della Risorsa Umana" → "{persona}"
 *   "Timesheet di (mese e anno):"    → "{mese_anno}"
 *   "SAL n.:"                         → "2"
 */
final class HeaderTemplate
{
    /** campo => [descrizione, valore di esempio per l'anteprima] */
    public const FIELDS = [
        'persona'          => ['Nome e cognome della persona', 'MARIO ROSSI'],
        'codice_fiscale'   => ['Codice fiscale della persona', 'RSSMRA80A01G273X'],
        'contratto'        => ['Tipo di contratto della persona', 'Ricercatore, III Livello'],
        'matricola'        => ['Matricola CNR', '12345'],
        'sede'             => ['Sede della persona', 'Palermo'],
        'mese_anno'        => ['Mese e anno del timesheet (lingua della tabella)', 'Settembre 2026'],
        'mese_anno_it'     => ['Mese e anno in italiano', 'Settembre 2026'],
        'mese_anno_en'     => ['Mese e anno in inglese', 'September 2026'],
        'ore_progetto'     => ['Ore del mese rendicontate sul progetto', '41:24'],
        'monte_ore'        => ['Monte ore della persona sul progetto', '1700:00'],
        'acronimo'         => ['Acronimo del progetto', 'ACRONIMO'],
        'titolo'           => ['Titolo del progetto', 'Titolo del progetto'],
        'cup'              => ['CUP', 'B53C00000000001'],
        'grant_agreement'  => ['Grant Agreement', '101000000'],
        'tipo_ricerca'     => ['Tipo di Ricerca (RI, SS…)', 'RI - Ricerca Industriale'],
        'avviso'           => ['Avviso / Call', 'Avviso pubblico …'],
        'codice_progetto'  => ['Codice del progetto', 'CODICE-123'],
        'ente'             => ['Ente / Beneficiario', ''],
        'partita_iva'      => ['Partita IVA / C.F. ente', '02118311006'],
        'sal'              => ['SAL n.', '1'],
        'monte_ore_annuo'  => ['Monte ore annuo associato alla persona', '1506'],
        'data_inizio'      => ['Data inizio progetto', '01/01/2026'],
        'data_fine'        => ['Data fine (o proroga) progetto', '31/12/2027'],
    ];

    /** Modelli di partenza (gli stessi della V.2). */
    public static function presets(): array
    {
        $common = static fn (string $name, string $tax, string $job, string $month, string $tot) => [
            [$name, '{persona}'], [$tax, '{codice_fiscale}'], [$job, '{contratto}'], [$month, '{mese_anno}'], [$tot, '{ore_progetto}'],
        ];

        return [
            'standard' => ['Standard (italiano)', 'it', array_merge([
                ['Avviso Progetto:', '{avviso}'],
                ['Titolo del Progetto:', '{titolo}'],
                ['?CUP:', '{cup}'],
                ['?Grant Agreement:', '{grant_agreement}'],
                ['Acronimo:', '{acronimo}'],
                ['Codice del Progetto:', '{codice_progetto}'],
                ['Denominazione Soggetto (ENTE):', '{ente}'],
            ], $common('Nominativo della Risorsa Umana', 'Codice Fiscale:', 'Tipo di Contratto:', 'Timesheet di (mese e anno):', 'Totale ore mensile rendicontate su questo progetto:'))],
            'horizon' => ['Horizon / Grant Agreement (inglese)', 'en', array_merge([
                ['Call:', '{avviso}'],
                ['Title of the action:', '{titolo}'],
                ['Grant Agreement nr.:', '{grant_agreement}'],
                ['Acronym:', '{acronimo}'],
                ['?Project Code:', '{codice_progetto}'],
                ["Beneficiary's name:", '{ente}'],
            ], $common('Name of the person working on the action/type of personnel:', 'Tax ID Code:', 'Job Title:', 'Time recording for Action: Month - Year', 'Total hours reported on this project:'))],
            'mise' => ['Decreto ministeriale / MISE (inglese)', 'en', array_merge([
                ['Notice:', '{avviso}'],
                ['Title of the action:', '{titolo}'],
                ['CUP:', '{cup}'],
                ['Acronym:', '{acronimo}'],
                ['?Project Code:', '{codice_progetto}'],
                ["Beneficiary's name:", '{ente}'],
            ], $common('Name of the person working on the action/type of personnel:', 'Tax ID Code:', 'Job Title:', 'Time recording for Action: Month - Year', 'Total hours reported on this project:'))],
            'sal' => ['SAL con P.IVA e monte ore annuo (italiano)', 'it', [
                ['Avviso Progetto:', '{avviso}'],
                ['Titolo del Progetto:', '{titolo}'],
                ['Denominazione Soggetto (ENTE):', '{ente}'],
                ['Codice Fiscale/Partita IVA:', '{partita_iva}'],
                ['CUP:', '{cup}'],
                ['Timesheet di (mese e anno):', '{mese_anno}'],
                ['SAL n.:', '{sal}'],
                ['Nominativo della Risorsa Umana:', '{persona}'],
                ['Codice fiscale:', '{codice_fiscale}'],
                ['Tipo di contratto:', '{contratto}'],
                ['Monte ore lavorativo annuo previsto:', '{monte_ore_annuo}'],
                ['Totale ore mensile rendicontate su questo progetto:', '{ore_progetto}'],
            ]],
        ];
    }

    /**
     * Colori delle tabelle del timesheet (Opzioni → "Colori del timesheet").
     *  - blu:  blu istituzionale chiaro (predefinito)
     *  - v2:   lilla della versione 2
     *
     * @return array{label:string,value:string,head:string} colori esadecimali senza #
     */
    public static function palette(): array
    {
        $p = (string) \Joomla\CMS\Component\ComponentHelper::getParams('com_protis')->get('table_palette', 'blu');

        return $p === 'v2'
            ? ['label' => 'E6E6FF', 'value' => 'E6E6FF', 'head' => 'E6E6FF']
            : ['label' => 'DCE6F0', 'value' => 'F1F5FA', 'head' => 'DCE6F0'];
    }

    public static function latexRgb(string $hex): string
    {
        return implode(',', array_map(static fn ($c) => round(hexdec($c) / 255, 3), str_split($hex, 2)));
    }

    /** Monte ore lavorativo annuo previsto: 1520 se assunto da meno di 3 anni alla data, altrimenti 1506. */
    public const ORE_ANNUE_NUOVI    = 1520;
    public const ORE_ANNUE_STANDARD = 1506;

    public static function annualHours(?string $hireDate, string $atDate): int
    {
        if (!$hireDate || $hireDate === '0000-00-00') {
            return self::ORE_ANNUE_STANDARD;
        }

        $limit = (new \DateTimeImmutable($hireDate))->modify('+3 years');

        return new \DateTimeImmutable($atDate) < $limit ? self::ORE_ANNUE_NUOVI : self::ORE_ANNUE_STANDARD;
    }

    /** @return array<int,array{label:string,value:string}> */
    public static function decode(?string $json): array
    {
        $rows = json_decode((string) $json, true);

        if (!\is_array($rows)) {
            return [];
        }

        $out = [];

        foreach ($rows as $r) {
            $label = trim((string) ($r['label'] ?? ''));
            $value = trim((string) ($r['value'] ?? ''));

            if ($label !== '' || $value !== '') {
                $out[] = ['label' => mb_substr($label, 0, 300), 'value' => mb_substr($value, 0, 4000), 'print' => (int) ($r['print'] ?? 1) ? 1 : 0];
            }
        }

        return $out;
    }

    /** Righe del progetto: quelle personalizzate oppure il modello del layout. */
    public static function rowsFor(object $project, string $layout): array
    {
        $rows = self::decode($project->header_fields ?? null);

        if ($rows) {
            return $rows;
        }

        $preset = self::presets()[$layout] ?? self::presets()['standard'];

        return array_map(static fn ($r) => ['label' => $r[0], 'value' => $r[1], 'print' => 1], $preset[2]);
    }

    /**
     * Sostituisce i campi {xxx}. Le righe con un solo campo automatico che risulta vuoto
     * e marcato come facoltativo (prefisso "?" nell'etichetta) vengono omesse.
     *
     * @return array<int,array{0:string,1:string}>
     */
    public static function resolve(array $rows, array $values): array
    {
        $out = [];

        foreach ($rows as $r) {
            // riga non spuntata "Stampa" nell'editor del progetto
            if (isset($r['print']) && !(int) $r['print']) {
                continue;
            }

            $label    = (string) $r['label'];
            $optional = str_starts_with($label, '?');
            $label    = ltrim($label, '?');

            $value = preg_replace_callback('/\{([a-z_]+)\}/', static fn ($m) => \array_key_exists($m[1], $values) ? (string) $values[$m[1]] : $m[0], (string) $r['value']);

            if ($optional && trim($value) === '') {
                continue;
            }

            $out[] = [trim($label), $value];
        }

        return $out;
    }

    /** Campi che dipendono solo dal progetto (per sapere se una riga è valorizzata). */
    public const PROJECT_FIELDS = [
        'acronimo' => 'acronimo', 'titolo' => 'titolo', 'cup' => 'cup', 'grant_agreement' => 'grant_agreement', 'tipo_ricerca' => 'tipo_ricerca', 'avviso' => 'codice_avviso',
        'codice_progetto' => 'codice_progetto', 'ente' => 'ente', 'partita_iva' => 'partita_iva', 'sal' => 'sal_numero',
    ];

    /** @return array<string,string> valori reali dei campi di progetto ('' se non compilati) */
    public static function projectValues(object $project): array
    {
        $out = [];

        foreach (self::PROJECT_FIELDS as $k => $col) {
            $out[$k] = trim((string) ($project->$col ?? ''));
        }

        return $out;
    }

    public static function sampleValues(object $project): array
    {
        $v = array_map(static fn ($f) => $f[1], self::FIELDS);

        foreach (['acronimo' => 'acronimo', 'titolo' => 'titolo', 'cup' => 'cup', 'grant_agreement' => 'grant_agreement', 'tipo_ricerca' => 'tipo_ricerca', 'avviso' => 'codice_avviso', 'codice_progetto' => 'codice_progetto',
                  'ente' => 'ente', 'partita_iva' => 'partita_iva', 'sal' => 'sal_numero'] as $k => $col) {
            if (isset($project->$col) && (string) $project->$col !== '') {
                $v[$k] = (string) $project->$col;
            }
        }

        return $v;
    }
}
