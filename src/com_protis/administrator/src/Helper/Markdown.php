<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Administrator\Helper;

\defined('_JEXEC') or die;

/**
 * Conversione Markdown → HTML minima per la Guida (titoli con {#id}, paragrafi, elenchi, tabelle,
 * grassetto, corsivo, codice, link). Il testo viene sempre protetto con htmlspecialchars.
 */
final class Markdown
{
    /** @var array<int,array{id:string,title:string}> titoli di livello 2 (indice) */
    public array $toc = [];

    public function render(string $md): string
    {
        $lines = preg_split('/\R/', $md);
        $out   = [];
        $para  = [];
        $n     = \count($lines);

        $flush = function () use (&$para, &$out) {
            if ($para) {
                $out[] = '<p>' . $this->inline(implode(' ', $para)) . '</p>';
                $para  = [];
            }
        };

        for ($i = 0; $i < $n; $i++) {
            $line = rtrim($lines[$i]);

            if (trim($line) === '') {
                $flush();
                continue;
            }

            // titoli
            if (preg_match('/^(#{2,4})\s+(.*?)(?:\s*\{#([a-z0-9-]+)\})?$/', $line, $m)) {
                $flush();
                $lvl   = \strlen($m[1]);
                $id    = ($m[3] ?? '') !== '' ? $m[3] : $this->slug($m[2]);
                $title = $m[2];

                if ($lvl === 2) {
                    $this->toc[] = ['id' => $id, 'title' => $title];
                }

                $out[] = \sprintf('<h%d id="%s">%s</h%d>', $lvl, htmlspecialchars($id), $this->inline($title), $lvl);
                continue;
            }

            // tabelle
            if (str_starts_with(ltrim($line), '|') && isset($lines[$i + 1]) && preg_match('/^\s*\|[\s:|-]+\|\s*$/', $lines[$i + 1])) {
                $flush();
                $head = $this->cells($line);
                $i++;
                $rows = [];

                while (isset($lines[$i + 1]) && str_starts_with(ltrim($lines[$i + 1]), '|')) {
                    $rows[] = $this->cells($lines[++$i]);
                }

                $html = '<div class="pt-table-wrap"><table class="pt-table pt-guide-table"><thead><tr>';

                foreach ($head as $h) {
                    $html .= '<th>' . $this->inline($h) . '</th>';
                }

                $html .= '</tr></thead><tbody>';

                foreach ($rows as $r) {
                    $html .= '<tr>';

                    foreach ($r as $k => $c) {
                        $html .= '<td data-label="' . htmlspecialchars(strip_tags($head[$k] ?? '')) . '">' . $this->inline($c) . '</td>';
                    }

                    $html .= '</tr>';
                }

                $out[] = $html . '</tbody></table></div>';
                continue;
            }

            // elenchi
            if (preg_match('/^\s*([-*]|\d+\.)\s+/', $line, $m)) {
                $flush();
                $tag   = ctype_digit($m[1][0]) ? 'ol' : 'ul';
                $items = [];
                $i--;

                while (isset($lines[$i + 1]) && preg_match('/^\s*([-*]|\d+\.)\s+(.*)$/', $lines[$i + 1], $mm)) {
                    $items[] = $mm[2];
                    $i++;
                }

                $out[] = '<' . $tag . '>' . implode('', array_map(fn ($t) => '<li>' . $this->inline($t) . '</li>', $items)) . '</' . $tag . '>';
                continue;
            }

            $para[] = trim($line);
        }

        $flush();

        return implode("\n", $out);
    }

    private function cells(string $line): array
    {
        return array_map('trim', explode('|', trim(trim($line), '|')));
    }

    private function inline(string $t): string
    {
        $t = htmlspecialchars($t, ENT_QUOTES);
        $t = preg_replace('/`([^`]+)`/', '<code>$1</code>', $t);
        $t = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $t);
        $t = preg_replace('/(?<![*\w])\*(?!\s)(.+?)(?<!\s)\*(?![*\w])/', '<em>$1</em>', $t);

        // link: solo http(s), percorsi relativi e ancore
        return preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', static function ($m) {
            $url = html_entity_decode($m[2], ENT_QUOTES);

            if (!preg_match('~^(https?://|#|/|index\.php)~', $url)) {
                return $m[1];
            }

            $ext = str_starts_with($url, 'http') ? ' target="_blank" rel="noopener"' : '';

            return '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '"' . $ext . '>' . $m[1] . '</a>';
        }, $t);
    }

    private function slug(string $s): string
    {
        $s = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', strip_tags($s)) ?: $s);

        return trim(preg_replace('/[^a-z0-9]+/', '-', $s), '-') ?: 'sezione';
    }
}
