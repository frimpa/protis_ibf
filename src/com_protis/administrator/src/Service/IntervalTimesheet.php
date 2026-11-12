<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Administrator\Service;

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Helper\Time;

/**
 * Timesheet per intervallo (come "Esporta timesheet per intervallo" della dashboard V.2):
 * una riga per mese con le ore giornaliere sul progetto (somma dei WP), A3 orizzontale,
 * colonne di costo vuote da compilare e riquadro firme.
 */
final class IntervalTimesheet
{
    public const TITLE = 'SCHEMA REGISTRAZIONE PRESENZE DEL PERSONALE PARTECIPANTE AL PROGETTO';

    private const SIGNATURES = [
        'Luogo e data',
        'Firma del responsabile scientifico del progetto/coordinatore',
        'Firma e timbro del direttore amministrativo',
        "Data e firma dell'addetto al progetto",
    ];

    /**
     * @param string|null $fromYm YYYY-MM (null = intera durata del progetto)
     * @param string|null $toYm   YYYY-MM
     */
    public function build(int $personId, int $projectId, ?string $fromYm, ?string $toYm): array
    {
        $person  = Services::people()->get($personId) ?? throw new \RuntimeException('Persona non trovata.');
        $project = Services::projects()->get($projectId) ?? throw new \RuntimeException('Progetto non trovato.');

        $pStart = $project->data_inizio ? new \DateTimeImmutable($project->data_inizio) : null;
        $pEndS  = $project->data_proroga ?: $project->data_fine;
        $pEnd   = $pEndS ? new \DateTimeImmutable($pEndS) : null;

        if ($fromYm === null) {
            if (!$pStart || !$pEnd) {
                throw new \DomainException('Date del progetto mancanti: scegliere un periodo personalizzato.');
            }

            [$start, $end] = [$pStart, $pEnd];
        } else {
            if (!preg_match('/^\d{4}-\d{2}$/', $fromYm) || !preg_match('/^\d{4}-\d{2}$/', (string) $toYm)) {
                throw new \InvalidArgumentException('Periodo non valido.');
            }

            $start = new \DateTimeImmutable($fromYm . '-01');
            $end   = (new \DateTimeImmutable($toYm . '-01'))->modify('last day of this month');

            if ($end < $start) {
                throw new \InvalidArgumentException('Il mese finale precede quello iniziale.');
            }

            if ($pStart && $start < $pStart) {
                $start = $pStart;
            }

            if ($pEnd && $end > $pEnd) {
                $end = $pEnd;
            }
        }

        if ($end < $start) {
            throw new \DomainException('Il periodo scelto non rientra nelle date del progetto.');
        }

        $wpIds = array_map(static fn ($w) => (int) $w->id, $project->wps);
        $hours = Services::calendar()->hours($personId, $start->format('Y-m-d'), $end->format('Y-m-d'));

        $months = [];
        $total  = 0;

        for ($d = $start->modify('first day of this month'); $d <= $end; $d = $d->modify('+1 month')) {
            if (\count($months) >= 240) {
                throw new \DomainException('Intervallo troppo lungo (massimo 20 anni).');
            }

            $n     = (int) $d->format('t');
            $cells = [];
            $sum   = 0;

            for ($i = 1; $i <= 31; $i++) {
                if ($i > $n) {
                    $cells[$i] = null;
                    continue;
                }

                $day = $d->setDate((int) $d->format('Y'), (int) $d->format('n'), $i);

                if ($day < $start || $day > $end) {
                    $cells[$i] = null;
                    continue;
                }

                $min = 0;

                foreach ($hours[$day->format('Y-m-d')] ?? [] as $wp => $m) {
                    if (\in_array((int) $wp, $wpIds, true)) {
                        $min += (int) $m;
                    }
                }

                $cells[$i] = $min;
                $sum      += $min;
            }

            $months[] = ['label' => Time::monthName((int) $d->format('n')) . ' ' . $d->format('Y'), 'days' => $cells, 'total' => $sum];
            $total   += $sum;
        }

        $logo = null;

        if ($project->logo !== '' && is_file(ProjectService::logoDir() . '/' . $project->logo)) {
            $logo = ProjectService::logoDir() . '/' . $project->logo;
        }

        $slug = static fn (string $s) => trim(preg_replace('/[^A-Za-z0-9]+/', '_', $s), '_');
        $dmy  = static fn (?\DateTimeImmutable $x) => $x ? $x->format('d/m/Y') : 'non disponibile';

        return [
            'fields' => [
                'Avviso Progetto'                       => (string) $project->codice_avviso,
                'Titolo del Progetto'                   => (string) ($project->titolo ?: $project->acronimo),
                ($project->cup === '' && ($project->grant_agreement ?? '') !== '' ? 'Grant Agreement' : 'CUP') => (string) ($project->cup ?: ($project->grant_agreement ?? '')),
                'Acronimo'                              => (string) $project->acronimo,
                'Codice del Progetto'                   => (string) $project->codice_progetto,
                'Denominazione Soggetto (ENTE)'         => (string) $project->ente,
                'Nominativo della Risorsa Umana'        => trim($person->nome . ' ' . $person->cognome),
                'Codice Fiscale'                        => (string) $person->codice_fiscale,
                'Tipo di Contratto'                     => (string) $person->tipo_contratto,
                'Date del progetto'                     => $dmy($pStart) . ' - ' . $dmy($pEnd),
                'Periodo rendicontato'                  => $start->format('d/m/Y') . ' - ' . $end->format('d/m/Y'),
                'Totale ore rendicontate sul progetto'  => Time::format($total),
            ],
            'months'   => $months,
            'total'    => $total,
            'logo'     => $logo,
            'filename' => \sprintf('timesheet_%s_%s_%s_%s', $slug($person->uid), $slug($project->acronimo), $start->format('Ym'), $end->format('Ym')),
        ];
    }

    public function pdf(array $r): string
    {
        $e     = [LatexRenderer::class, 'escape'];
        $files = [];
        $s     = "\\documentclass[10pt,a3paper,landscape]{article}\n\\usepackage[utf8]{inputenc}\n"
            . "\\usepackage[left=10mm,right=10mm,top=10mm,bottom=12mm,footskip=6mm]{geometry}\n"
            . "\\usepackage[table]{xcolor}\n\\usepackage{longtable,array,graphicx}\n\\pagestyle{plain}\n\\begin{document}\n";

        if ($r['logo']) {
            $name         = 'logo.' . strtolower(pathinfo($r['logo'], PATHINFO_EXTENSION));
            $files[$name] = $r['logo'];
            $s           .= "\\noindent\\makebox[\\linewidth][c]{\\includegraphics[width=\\linewidth,height=28mm,keepaspectratio]{" . $name . "}}\\par\\vspace{5mm}\n";
        }

        $s .= "\\noindent\\begin{tabular}{|p{65mm}|p{\\dimexpr\\linewidth-65mm-4\\tabcolsep-3\\arrayrulewidth\\relax}|}\\hline\n";

        foreach ($r['fields'] as $label => $value) {
            $s .= '\\cellcolor{blue!8}\\textbf{' . $e($label) . '} & \\cellcolor{blue!3}' . $e($value) . " \\\\ \\hline\n";
        }

        $s .= "\\end{tabular}\n\\par\\vspace{5mm}{\\centering\\bfseries " . self::TITLE . "\\par}\\vspace{4mm}\n";
        $s .= "\\fontsize{8}{10}\\selectfont\\setlength{\\tabcolsep}{1pt}\\renewcommand{\\arraystretch}{1.6}\\setlength{\\LTpre}{0pt}\\setlength{\\LTpost}{0pt}\n";
        $s .= "\\newlength{\\tiDayWidth}\\setlength{\\tiDayWidth}{\\dimexpr\\linewidth-97mm-74\\tabcolsep-38\\arrayrulewidth\\relax}\\divide\\tiDayWidth by 31\n\\setlength{\\LTleft}{0pt}\\setlength{\\LTright}{0pt}\n";
        $s .= '\\begin{longtable}{|p{23mm}|' . str_repeat('>{\\centering\\arraybackslash}p{\\tiDayWidth}|', 31) . 'p{15mm}|p{13mm}|p{15mm}|p{15mm}|p{16mm}|}' . "\n\\hline\n";
        $s .= implode(' & ', array_merge(['Mese'], array_map('strval', range(1, 31)), ['Ore mese', 'Costo orario', 'Costo mese', 'Ore Masaf', 'Importo Masaf'])) . " \\\\ \\hline\n\\endhead\n";

        $keepFrom = max(0, \count($r['months']) - 3);

        foreach ($r['months'] as $i => $m) {
            $cells = [$e($m['label'])];

            foreach ($m['days'] as $v) {
                $cells[] = $v === null ? '\\cellcolor{gray!20}' : Time::format($v, true);
            }

            $cells = array_merge($cells, [Time::format($m['total']), '', '', '', '']);
            // le ultime righe, il totale e le firme restano sulla stessa pagina
            $s .= implode(' & ', $cells) . ($i >= $keepFrom ? " \\\\* \\hline\n" : " \\\\ \\hline\n");
        }

        $s .= '\\multicolumn{32}{|l|}{\\textbf{TOTALE ORE LAVORATE SUL PROGETTO}} & \\textbf{' . Time::format($r['total']) . "} & & & & \\\\* \\hline\n";
        $s .= "\\multicolumn{37}{@{}l@{}}{\\begin{minipage}{\\linewidth}\\vspace{6mm}\\normalsize\\renewcommand{\\arraystretch}{1}\n";
        $s .= "\\begin{tabular}{@{}p{.31\\linewidth}>{\\centering\\arraybackslash}p{.34\\linewidth}>{\\raggedleft\\arraybackslash}p{.31\\linewidth}@{}}\n";
        $s .= $e(self::SIGNATURES[0]) . ' & ' . $e(self::SIGNATURES[1]) . ' & ' . $e(self::SIGNATURES[2]) . " \\\\[9mm]\n";
        $s .= $e(self::SIGNATURES[3]) . " & & \\\\[10mm]\n\\end{tabular}\\end{minipage}} \\\\\n\\end{longtable}\n\\end{document}\n";

        return (new LatexRenderer())->compile($s, $files);
    }

    public function xlsx(array $r): string
    {
        if (!SpreadsheetRenderer::available()) {
            throw new \RuntimeException('PhpSpreadsheet non disponibile.');
        }

        $C    = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::class;
        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $ws   = $book->getActiveSheet();
        $ws->setTitle('Time-sheet');
        $book->getDefaultStyle()->getFont()->setName('Arial')->setSize(9);
        $ws->getColumnDimension('A')->setWidth(22);

        for ($i = 2; $i <= 32; $i++) {
            $ws->getColumnDimension($C::stringFromColumnIndex($i))->setWidth(7);
        }

        foreach (['AG', 'AH', 'AI', 'AJ', 'AK'] as $c) {
            $ws->getColumnDimension($c)->setWidth(13);
        }

        $text = static function (string $cell, string $v) use ($ws): void {
            $ws->setCellValueExplicit($cell, $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        };
        $fill = static fn (string $range, string $rgb) => $ws->getStyle($range)->getFill()->setFillType('solid')->getStartColor()->setARGB('FF' . $rgb);

        $row = 1;

        if ($r['logo'] && \in_array(strtolower(pathinfo($r['logo'], PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg'], true)) {
            $ws->mergeCells('A1:AK1');
            $ws->getRowDimension(1)->setRowHeight(90);
            $d = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
            $d->setPath($r['logo'])->setCoordinates('A1')->setHeight(110)->setWorksheet($ws);
            $row = 3;
        }

        foreach ($r['fields'] as $label => $value) {
            $ws->mergeCells("A$row:H$row");
            $ws->mergeCells("I$row:AK$row");
            $text("A$row", $label);
            $text("I$row", $value);
            $ws->getStyle("A$row")->getFont()->setBold(true);
            $fill("A$row:H$row", 'E5EDF8');
            $fill("I$row:AK$row", 'F2F6FC');
            $ws->getStyle("A$row:AK$row")->getBorders()->getAllBorders()->setBorderStyle('thin');
            $ws->getRowDimension($row)->setRowHeight(max(24, 18 * (int) ceil(mb_strlen($value) / 150)));
            $row++;
        }

        $title = $row + 1;
        $head  = $title + 2;
        $ws->mergeCells("A$title:AK$title");
        $text("A$title", self::TITLE);
        $ws->getStyle("A$title")->getAlignment()->setHorizontal('center');
        $ws->getStyle("A$title")->getFont()->setBold(true)->setSize(11);
        $ws->getRowDimension($title)->setRowHeight(30);
        $ws->mergeCells('AJ' . ($title + 1) . ':AK' . ($title + 1));
        $text('AJ' . ($title + 1), 'Riservato al Masaf');

        $text("A$head", 'MESE');

        for ($i = 1; $i <= 31; $i++) {
            $ws->setCellValue($C::stringFromColumnIndex($i + 1) . $head, $i);
        }

        foreach (['AG' => 'Ore totali mese', 'AH' => 'Costo orario', 'AI' => 'Costo totale mese', 'AJ' => 'Totale ore mese', 'AK' => 'Importo totale mese'] as $c => $l) {
            $text($c . $head, $l);
        }

        $ws->getStyle("A$head:AK$head")->getFont()->setBold(true);
        $fill("A$head:AK$head", 'E5E7EB');
        $ws->getStyle("A$head:AK$head")->getAlignment()->setHorizontal('center');
        $ws->getRowDimension($head)->setRowHeight(32);

        $row   = $head + 1;
        $first = $row;

        foreach ($r['months'] as $m) {
            $text("A$row", $m['label']);

            foreach ($m['days'] as $i => $v) {
                $cell = $C::stringFromColumnIndex($i + 1) . $row;

                if ($v === null) {
                    $fill($cell, 'D1D5DB');
                } elseif ($v > 0) {
                    $ws->setCellValue($cell, $v / 1440);
                }
            }

            $ws->setCellValue("AG$row", "=SUM(B$row:AF$row)");
            $ws->getRowDimension($row)->setRowHeight(23);
            $row++;
        }

        $last = $row - 1;
        $ws->mergeCells("A$row:AF$row");
        $text("A$row", 'TOTALE ORE LAVORATE SUL PROGETTO');
        $ws->setCellValue("AG$row", "=SUM(AG$first:AG$last)");
        $ws->getStyle("A$row:AK$row")->getFont()->setBold(true);
        $ws->getStyle("B$first:AG$row")->getNumberFormat()->setFormatCode('[h]:mm');
        $ws->getStyle("A$head:AK$row")->getBorders()->getAllBorders()->setBorderStyle('thin');
        $ws->getStyle("A1:AK$row")->getAlignment()->setVertical('center')->setWrapText(true);

        $row += 2;

        foreach ([['A', 'L', 0, 'left'], ['M', 'Y', 1, 'center'], ['Z', 'AK', 2, 'right']] as [$a, $b, $k, $al]) {
            $ws->mergeCells("$a$row:$b$row");
            $text($a . $row, self::SIGNATURES[$k]);
            $ws->getStyle("$a$row:$b$row")->getAlignment()->setHorizontal($al)->setVertical('top')->setWrapText(true);
        }

        $ws->getRowDimension($row)->setRowHeight(42);
        $row += 2;
        $ws->mergeCells("A$row:L$row");
        $text("A$row", self::SIGNATURES[3]);
        $ws->getStyle("A$row:L$row")->getAlignment()->setVertical('top')->setWrapText(true);
        $ws->getRowDimension($row)->setRowHeight(36);
        $row += 2;

        $ws->getPageSetup()->setOrientation('landscape')
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A3)
            ->setFitToWidth(1)->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd($head, $head)
            ->setPrintArea('A1:AK' . $row);

        $w = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book);
        $w->setPreCalculateFormulas(true);
        $tmp = tempnam(sys_get_temp_dir(), 'protis');
        $w->save($tmp);
        $data = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $data;
    }
}
