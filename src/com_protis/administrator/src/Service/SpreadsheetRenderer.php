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
 * Esportazione timesheet in XLSX / ODS (PhpSpreadsheet, incluso nel pacchetto) e CSV.
 */
final class SpreadsheetRenderer
{
    public static function available(): bool
    {
        $autoload = JPATH_ADMINISTRATOR . '/components/com_protis/vendor/autoload.php';

        if (is_file($autoload)) {
            require_once $autoload;
        }

        return class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class);
    }

    public function csv(array $sheets): string
    {
        $fh = fopen('php://temp', 'w+');
        fwrite($fh, "\xEF\xBB\xBF"); // BOM per Excel

        foreach ($sheets as $s) {
            foreach ($s['header'] as [$l, $v]) {
                fputcsv($fh, [$l, $v], ';', '"', '\\');
            }

            fputcsv($fh, [], ';', '"', '\\');
            fputcsv($fh, array_merge([$s['labels']['h_desc']], range(1, $s['days']), [$s['labels']['h_tot']]), ';', '"', '\\');

            foreach ($s['rows'] as $r) {
                fputcsv($fh, $this->rowValues($r), ';', '"', '\\');
            }

            fputcsv($fh, [], ';', '"', '\\');
        }

        rewind($fh);

        return (string) stream_get_contents($fh);
    }

    private function rowValues(array $r): array
    {
        $out = [$r['label']];

        foreach ($r['values'] as $i => $v) {
            $show  = $v > 0 || (\is_array($r['show_zero']) && ($r['show_zero'][$i] ?? false));
            $out[] = $show ? Time::format($v) : '';
        }

        $out[] = ($r['total'] > 0 || $r['kind'] !== 'other') ? Time::format($r['total']) : '';

        return $out;
    }

    /** @param string $format xlsx|ods */
    public function spreadsheet(array $sheets, string $format): string
    {
        if (!self::available()) {
            throw new \RuntimeException('PhpSpreadsheet non disponibile: eseguire "composer install" nella cartella del componente.');
        }

        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $book->removeSheetByIndex(0);

        $Coord  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::class;
        $Fill   = \PhpOffice\PhpSpreadsheet\Style\Fill::class;
        $Border = \PhpOffice\PhpSpreadsheet\Style\Border::class;
        $Align  = \PhpOffice\PhpSpreadsheet\Style\Alignment::class;

        foreach ($sheets as $idx => $s) {
            $ws = $book->createSheet();
            $ws->setTitle(substr(\sprintf('%02d-%04d %s', $s['month'], $s['year'], preg_replace('/[^A-Za-z0-9 ]/', '', $s['project'])), 0, 31));
            $ws->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
                ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
                ->setFitToWidth(1)->setFitToHeight(0);

            $lastCol = $Coord::stringFromColumnIndex($s['days'] + 2);
            $row     = 1;

            if ($s['logo'] && \in_array(strtolower(pathinfo($s['logo'], PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg'], true)) {
                $d = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
                $d->setPath($s['logo'])->setCoordinates('A1')->setHeight(60)->setWorksheet($ws);
                $ws->getRowDimension(1)->setRowHeight(48);
                $row = 3;
            }

            $pal = HeaderTemplate::palette();

            foreach ($s['header'] as [$l, $v]) {
                $ws->setCellValue('A' . $row, $l);
                $ws->setCellValue('B' . $row, $v);
                $ws->mergeCells("B$row:$lastCol$row");
                $ws->getStyle("A$row:$lastCol$row")->applyFromArray([
                    'fill'      => ['fillType' => $Fill::FILL_SOLID, 'startColor' => ['rgb' => $pal['label']]],
                    'borders'   => ['allBorders' => ['borderStyle' => $Border::BORDER_THIN]],
                    'alignment' => ['wrapText' => true, 'vertical' => $Align::VERTICAL_TOP, 'horizontal' => $Align::HORIZONTAL_LEFT],
                    'font'      => ['size' => 9],
                ]);
                $ws->getStyle("B$row:$lastCol$row")->getFill()->setFillType($Fill::FILL_SOLID)->getStartColor()->setRGB($pal['value']);
                $row++;
            }

            $row++;
            $headRow = $row;
            $ws->fromArray(array_merge([$s['labels']['h_desc']], range(1, $s['days']), [$s['labels']['h_tot']]), null, 'A' . $row);
            $row++;

            foreach ($s['rows'] as $r) {
                $ws->fromArray($this->rowValues($r), null, 'A' . $row, true);

                if ($r['kind'] === 'total') {
                    $ws->getStyle("A$row:$lastCol$row")->getFont()->setBold(true);
                }

                $row++;
            }

            $ws->getStyle("A$headRow:$lastCol$headRow")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => $Fill::FILL_SOLID, 'startColor' => ['rgb' => $pal['head']]],
            ]);
            $ws->getStyle('A' . $headRow . ':' . $lastCol . ($row - 1))->applyFromArray([
                'borders'   => ['allBorders' => ['borderStyle' => $Border::BORDER_THIN]],
                'font'      => ['size' => 8],
                'alignment' => ['horizontal' => $Align::HORIZONTAL_CENTER, 'vertical' => $Align::VERTICAL_CENTER],
            ]);
            $ws->getStyle('A' . $headRow . ':A' . ($row - 1))->getAlignment()->setHorizontal($Align::HORIZONTAL_LEFT)->setWrapText(true);
            $ws->getColumnDimension('A')->setWidth(38);

            for ($c = 2; $c <= $s['days'] + 2; $c++) {
                $ws->getColumnDimension($Coord::stringFromColumnIndex($c))->setWidth($c === $s['days'] + 2 ? 9 : 5.5);
            }

            $row += 2;
            $ws->setCellValue('A' . $row, 'Si autorizza il trattamento dei dati forniti ai sensi del D.Lgs. n. 196/2003 e s.m.i.');
            $row += 2;
            $ws->setCellValue('A' . $row, 'Data e firma della risorsa umana');
            $ws->setCellValue($Coord::stringFromColumnIndex(max(2, $s['days'] - 8)) . $row, 'Firma Responsabile Scientifico/Struttura');
        }

        $book->setActiveSheetIndex(0);
        $writer = $format === 'ods'
            ? new \PhpOffice\PhpSpreadsheet\Writer\Ods($book)
            : new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book);

        $tmp = tempnam(sys_get_temp_dir(), 'protis');
        $writer->save($tmp);
        $data = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $data;
    }
}
