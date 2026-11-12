<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Administrator\Service;

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Helper\Time;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;

/**
 * Genera il PDF del timesheet con pdflatex (layout identico alla V.2).
 * Ogni elemento di $sheets diventa una pagina A4 orizzontale.
 *
 * I file vengono creati in una cartella temporanea privata (non più nella webroot)
 * e rimossi dopo la compilazione.
 */
final class LatexRenderer
{
    public static function escape(?string $text): string
    {
        $text = (string) $text;
        $map  = [
            '\\' => '\textbackslash{}', '&' => '\&', '%' => '\%', '$' => '\$', '#' => '\#',
            '_' => '\_', '{' => '\{', '}' => '\}', '~' => '\textasciitilde{}', '^' => '\textasciicircum{}',
            '<' => '\textless{}', '>' => '\textgreater{}',
        ];

        $text = strtr($text, $map);
        // virgolette tipografiche/apostrofi Word → ASCII sicuri
        $text = str_replace(['“', '”', '’', '‘', '–', '—', "\r"], ['``', "''", "'", '`', '--', '---', ''], $text);

        return preg_replace("/\n+/", ' ', $text);
    }

    public function tex(array $sheets): string
    {
        $doc = <<<'TEX'
\documentclass{article}
\usepackage[utf8]{inputenc}
\usepackage{graphicx}
\usepackage{booktabs}
\usepackage[a4paper,margin=1cm]{geometry}
\usepackage{tabularx}
\usepackage[table]{xcolor}
\usepackage{array}
\usepackage{ragged2e}
\usepackage{pdflscape}
\usepackage{relsize}
\pagenumbering{gobble}
\begin{document}

TEX;

        foreach ($sheets as $i => $s) {
            if ($i > 0) {
                $doc .= "\\clearpage\n";
            }

            $doc .= $this->page($s, $i);
        }

        return $doc . "\\end{document}\n";
    }

    private function page(array $s, int $index): string
    {
        $out = "\\begin{landscape}\n";

        if ($s['logo']) {
            $out .= "\\begin{center}\n\\includegraphics[width=1.46\\textwidth,height=3cm,keepaspectratio]{logo{$index}." . strtolower(pathinfo($s['logo'], PATHINFO_EXTENSION)) . "}\n\\end{center}\n";
        }

        if (trim($s['note']) !== '') {
            $out .= "\\begin{flushleft}\n\\scriptsize " . self::escape($s['note']) . "\n\\end{flushleft}\n";
        }

        // tabella anagrafica
        $pal  = HeaderTemplate::palette();
        $cL   = HeaderTemplate::latexRgb($pal['label']);
        $cV   = HeaderTemplate::latexRgb($pal['value']);
        $out .= "\\vspace{0.1cm}\n\\noindent\n\\begin{tabularx}{\\linewidth}{|>{\\columncolor[rgb]{" . $cL . "}}p{5cm}|>{\\columncolor[rgb]{" . $cV . "}}X|}\n    \\hline\n";

        foreach ($s['header'] as [$label, $value]) {
            $out .= "    {\\scriptsize " . self::escape($label) . "} & {\\scriptsize " . self::escape($value) . "} \\\\\n    \\hline\n";
        }

        $out .= "\\end{tabularx}\n\\vspace{0.3cm}\n\n";

        // tabella giorni
        $n      = $s['days'];
        $L      = $s['labels'];
        // larghezze calibrate nella V.2 (tengono conto del padding delle colonne)
        $avail  = [31 => 13.306, 30 => 13.74, 29 => 14.18, 28 => 14.61][$n] ?? 14.5;
        $descW  = 4.5;
        $totW   = 1.0;
        $colW   = round(($avail - $descW - $totW) / $n, 4);

        $out .= "\\vspace{0.1cm}\n\\noindent\n\\renewcommand{\\arraystretch}{0.9}\n\\hspace*{-0.2cm}\n";
        $out .= '\\begin{tabularx}{\\linewidth}{|p{' . $descW . 'cm}';

        for ($i = 0; $i < $n; $i++) {
            $out .= '|>{\\centering \\arraybackslash}p{' . $colW . 'cm}';
        }

        $out .= '|>{\\centering \\arraybackslash}p{' . $totW . "cm}|}\n\\hline\n";

        $hdr  = '\\cellcolor[rgb]{' . HeaderTemplate::latexRgb($pal['head']) . '}';
        $out .= "$hdr \\scriptsize \\bfseries {$L['h_desc']} & \\multicolumn{{$n}}{c|}{{$hdr} \\scriptsize \\bfseries {$L['h_days']}} & $hdr \\makebox[{$totW}cm][c]{\\scriptsize \\bfseries {$L['h_tot']}} \\\\\n";
        $cells = [];

        for ($i = 1; $i <= $n; $i++) {
            $cells[] = "$hdr \\makebox[{$colW}cm][c]{\\scriptsize $i}";
        }

        $out .= "$hdr \\scriptsize \\bfseries {$L['h_desc2']} & " . implode(' & ', $cells) . " & $hdr \\makebox[{$totW}cm][c]{\\scriptsize \\bfseries {$L['h_tot2']}} \\\\\n\\hline\n";

        foreach ($s['rows'] as $r) {
            $isTotal = $r['kind'] === 'total';
            $label   = self::escape($r['label']);
            $line    = $isTotal ? "{\\scriptsize \\textbf{{$label}}}" : "{\\scriptsize {$label}}";

            foreach ($r['values'] as $i => $v) {
                $show  = $v > 0 || (\is_array($r['show_zero']) && ($r['show_zero'][$i] ?? false));
                $line .= ' & ' . ($show ? '\\makebox[\\linewidth][c]{\\scriptsize ' . Time::format($v) . '}' : '');
            }

            $showTot = $r['total'] > 0 || $isTotal || $r['kind'] === 'wp';
            $line   .= ' & ' . ($showTot ? '\\makebox[\\linewidth][c]{\\scriptsize ' . Time::format($r['total']) . '}' : '');
            $out    .= $line . " \\\\ \\hline\n";
        }

        $out .= "\\end{tabularx}\n\\vspace{0.3cm}\\par\n{\\scriptsize\n";
        $out .= "Si autorizza il trattamento dei dati forniti ai sensi del D.Lgs. n. 196/2003 e s.m.i.\\\\\n\\vspace{0.5cm}\\par\n";
        $out .= "\\begin{tabular}{p{1.0\\textwidth} p{0.30\\textwidth}}\nData e firma della risorsa umana & Firma Responsabile Scientifico/Struttura \\\\\n\\end{tabular}\n}\n";

        return $out . "\\end{landscape}\n";
    }

    /** Compila e restituisce il contenuto binario del PDF. */
    public function pdf(array $sheets): string
    {
        $files = [];

        foreach ($sheets as $i => $s) {
            if ($s['logo']) {
                $files['logo' . $i . '.' . strtolower(pathinfo($s['logo'], PATHINFO_EXTENSION))] = $s['logo'];
            }
        }

        return $this->compile($this->tex($sheets), $files);
    }

    /**
     * Compila un sorgente LaTeX in una cartella temporanea privata.
     *
     * @param array<string,string> $files nome nella cartella => file sorgente da copiare
     */
    public function compile(string $tex, array $files = []): string
    {
        $bin = (string) ComponentHelper::getParams('com_protis')->get('pdflatex_path', '/usr/bin/pdflatex');

        if (!is_executable($bin)) {
            throw new \RuntimeException('pdflatex non trovato (' . $bin . '). Installare texlive-latex-extra o configurare il percorso nelle Opzioni PROTIS IBF.');
        }

        $base = rtrim((string) Factory::getApplication()->get('tmp_path', sys_get_temp_dir()), '/');
        $dir  = $base . '/protis_' . bin2hex(random_bytes(8));

        if (!mkdir($dir, 0700, true)) {
            throw new \RuntimeException('Impossibile creare la cartella temporanea ' . $dir);
        }

        try {
            foreach ($files as $name => $src) {
                copy($src, $dir . '/' . basename($name));
            }

            file_put_contents($dir . '/timesheet.tex', $tex);

            // HOME scrivibile: evita errori di mktexpk/luatex con l'utente del web server
            $cmd = 'cd ' . escapeshellarg($dir) . ' && HOME=' . escapeshellarg($dir) . ' ' . escapeshellarg($bin)
                . ' -interaction=nonstopmode -halt-on-error -no-shell-escape timesheet.tex > compile.log 2>&1';
            exec($cmd, $o, $rc);

            // longtable può richiedere una seconda passata per allineare le colonne
            if ($rc === 0 && str_contains($tex, '{longtable}')) {
                exec($cmd, $o, $rc);
            }

            if ($rc !== 0 || !is_file($dir . '/timesheet.pdf')) {
                $log = is_file($dir . '/timesheet.log') ? (string) file_get_contents($dir . '/timesheet.log') : (string) @file_get_contents($dir . '/compile.log');
                preg_match_all('/^!.*(?:\n(?!!).*){0,3}/m', $log, $m);
                \Joomla\CMS\Log\Log::add("pdflatex:\n" . substr($log, -4000), \Joomla\CMS\Log\Log::ERROR, 'com_protis');
                throw new \RuntimeException('Errore nella generazione del PDF (LaTeX): ' . (trim(implode(' ', \array_slice($m[0] ?? [], 0, 2))) ?: 'vedere il log di Joomla.'));
            }

            return (string) file_get_contents($dir . '/timesheet.pdf');
        } finally {
            foreach (glob($dir . '/*') ?: [] as $f) {
                @unlink($f);
            }

            @rmdir($dir);
        }
    }
}
