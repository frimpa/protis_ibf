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
 * Costruisce la struttura dati del timesheet mensile (indipendente dal formato di uscita).
 */
final class TimesheetService extends AbstractService
{
    public const LABELS = [
        'it' => [
            'ordinary' => 'Attività Ordinaria', 'sick' => 'Malattia', 'holiday' => 'Ferie',
            'permit' => 'Permessi', 'other' => 'Altro', 'total' => 'TOTALE ORE MESE',
            'h_desc' => 'DESCRIZIONE', 'h_desc2' => "ATTIVITA'", 'h_days' => 'GIORNI', 'h_tot' => 'TOTALE', 'h_tot2' => 'ORE',
        ],
        'en' => [
            'ordinary' => 'Institutional Activity', 'sick' => 'Sick Leave', 'holiday' => 'Annual Leave',
            'permit' => 'Other absences', 'other' => 'Other', 'total' => 'TOTAL HOURS MONTH',
            'h_desc' => 'ACTIVITY', 'h_desc2' => '', 'h_days' => 'DAYS', 'h_tot' => 'TOTAL', 'h_tot2' => '',
        ],
    ];

    public function __construct(
        private PeopleService $people,
        private ProjectService $projects,
        private CalendarService $calendar,
    ) {
        parent::__construct();
    }

    public function layoutFor(object $project, ?object $assignment): string
    {
        $l = $assignment->layout ?? '';

        return $l !== '' ? $l : ($project->layout ?: 'standard');
    }

    /**
     * @return array{
     *   layout:string, lang:string, labels:array, header:array<int,array{0:string,1:string}>,
     *   logo:?string, note:string, days:int, year:int, month:int, rows:array, project_total:int, filename:string
     * }
     */
    public function build(int $personId, int $projectId, int $year, int $month): array
    {
        $person  = $this->people->get($personId) ?? throw new \RuntimeException('Persona non trovata');
        $project = $this->projects->get($projectId) ?? throw new \RuntimeException('Progetto non trovato');

        $assignment = null;

        foreach ($this->projects->assignments($projectId, $personId) as $a) {
            $assignment = $a;
        }

        $layout = $this->layoutFor($project, $assignment);
        $lang   = \in_array($project->table_lang ?? '', ['it', 'en'], true) && $project->table_lang !== ''
            ? $project->table_lang
            : (\in_array($layout, ['horizon', 'mise'], true) ? 'en' : 'it');
        $L      = self::LABELS[$lang];

        [$from, $to] = Time::monthRange($year, $month);
        $n           = Time::daysInMonth($year, $month);
        $days        = $this->calendar->days($personId, $from, $to);
        $hours       = $this->calendar->hours($personId, $from, $to);

        // indice WP → progetto
        $projWps = [];

        foreach ($project->wps as $w) {
            $projWps[(int) $w->id] = $w;
        }

        $otherWpIds = [];

        foreach ($hours as $h) {
            foreach (array_keys($h) as $wpId) {
                if (!isset($projWps[$wpId])) {
                    $otherWpIds[$wpId] = true;
                }
            }
        }

        $otherWpProject = [];

        if ($otherWpIds) {
            $q = $this->db->createQuery()->select("w.id, p.id AS pid, p.acronimo, COALESCE(NULLIF(p.cup, ''), p.grant_agreement) AS cup")
                ->from('#__protis_workpackages AS w')->join('INNER', '#__protis_projects AS p', 'p.id = w.project_id')
                ->where('w.id IN (' . implode(',', array_map('intval', array_keys($otherWpIds))) . ')');

            foreach ($this->db->setQuery($q)->loadObjectList() as $r) {
                $otherWpProject[(int) $r->id] = $r;
            }
        }

        $empty = array_fill(0, $n, 0);
        $rows  = [];

        // 1) WP del progetto selezionato
        $projectTotal = 0;

        foreach ($project->wps as $w) {
            $vals = $empty;

            for ($i = 0; $i < $n; $i++) {
                $date      = \sprintf('%04d-%02d-%02d', $year, $month, $i + 1);
                $vals[$i]  = (int) ($hours[$date][(int) $w->id] ?? 0);
            }

            $tot = array_sum($vals);

            if ($tot === 0 && !$w->published) {
                continue;
            }

            $projectTotal += $tot;
            $rows[]        = ['kind' => 'wp', 'label' => $w->label, 'values' => $vals, 'total' => $tot, 'show_zero' => false];
        }

        // come in V.2 si mostrano solo i WP con ore; se nessun WP ha ore restano le righe vuote dei WP attivi
        if ($projectTotal > 0) {
            $rows = array_values(array_filter($rows, static fn ($r) => $r['total'] > 0));
        }

        // 2) Altri progetti (una riga per progetto)
        $others = [];

        for ($i = 0; $i < $n; $i++) {
            $date = \sprintf('%04d-%02d-%02d', $year, $month, $i + 1);

            foreach ($hours[$date] ?? [] as $wpId => $min) {
                if (isset($projWps[$wpId])) {
                    continue;
                }

                $op  = $otherWpProject[$wpId] ?? null;
                $key = $op ? 'p' . $op->pid : 'wp' . $wpId;

                $others[$key] ??= [
                    'kind'      => 'other',
                    'label'     => $op ? \sprintf('Altri progetti (%s - %s)', $op->acronimo, $op->cup) : 'Altri progetti',
                    'values'    => $empty,
                    'total'     => 0,
                    'show_zero' => false,
                ];
                $others[$key]['values'][$i] += $min;
                $others[$key]['total'] += $min;
            }
        }

        foreach ($others as $o) {
            if ($o['total'] > 0) {
                $rows[] = $o;
            }
        }

        // 3) Categorie
        $cats = [
            'ordinary' => null,
            'sick'     => 'sick_min',
            'holiday'  => 'holiday_min',
            'permit'   => 'permit_min',
            'other'    => 'other_min',
        ];

        $dayTotals = $empty;

        foreach ($cats as $key => $col) {
            $vals = $empty;
            $has  = array_fill(0, $n, false);

            for ($i = 0; $i < $n; $i++) {
                $date = \sprintf('%04d-%02d-%02d', $year, $month, $i + 1);
                $d    = $days[$date] ?? null;

                if (!$d) {
                    continue;
                }

                if ($key === 'ordinary') {
                    $cap = CalendarService::capacity($d);

                    if ($cap > 0) {
                        $vals[$i] = max(0, $cap - array_sum($hours[$date] ?? []));
                        $has[$i]  = true;
                    }

                    $dayTotals[$i] += $cap;
                } else {
                    $vals[$i] = (int) $d->$col;
                    $dayTotals[$i] += $vals[$i];
                }
            }

            $rows[] = [
                'kind'      => 'cat',
                'label'     => $L[$key],
                'values'    => $vals,
                'total'     => array_sum($vals),
                'show_zero' => $has,
            ];
        }

        $rows[] = ['kind' => 'total', 'label' => $L['total'], 'values' => $dayTotals, 'total' => array_sum($dayTotals), 'show_zero' => false];

        $monthLabel = Time::monthName($month, $lang === 'en') . ' ' . $year;
        $fullName   = trim($person->nome . ' ' . $person->cognome);
        $totFmt     = Time::format($projectTotal);

        $values = [
            'persona'         => $fullName,
            'codice_fiscale'  => (string) $person->codice_fiscale,
            'contratto'       => (string) $person->tipo_contratto,
            'matricola'       => (string) $person->matricola,
            'sede'            => (string) (($person->sede_nome ?? '') ?: ($person->sede_esterna ?? '')),
            'mese_anno'       => $monthLabel,
            'mese_anno_it'    => Time::monthName($month) . ' ' . $year,
            'mese_anno_en'    => Time::monthName($month, true) . ' ' . $year,
            'ore_progetto'    => $totFmt,
            'monte_ore'       => $assignment && $assignment->monteore_min !== null ? Time::format((int) $assignment->monteore_min) : '',
            'acronimo'        => (string) $project->acronimo,
            'titolo'          => (string) $project->titolo,
            'cup'             => (string) $project->cup,
            'grant_agreement' => (string) ($project->grant_agreement ?? ''),
            'tipo_ricerca'    => (string) ($project->tipo_ricerca ?? ''),
            'avviso'          => (string) $project->codice_avviso,
            'codice_progetto' => (string) $project->codice_progetto,
            'ente'            => (string) $project->ente,
            'partita_iva'     => (string) $project->partita_iva,
            'sal'             => (string) $project->sal_numero,
            // 1520 se assunto da meno di 3 anni alla fine del mese rendicontato, altrimenti 1506
            // monte ore annuo dell'associazione; se non indicato 1520/1506 secondo l'anzianità
            'monte_ore_annuo' => $assignment && $assignment->monte_annuo_min !== null
                ? Time::hoursNumber((int) $assignment->monte_annuo_min)
                : (string) HeaderTemplate::annualHours($person->data_assunzione ?? null, $to),
            'data_inizio'     => $project->data_inizio ? date('d/m/Y', strtotime($project->data_inizio)) : '',
            'data_fine'       => ($project->data_proroga ?: $project->data_fine) ? date('d/m/Y', strtotime($project->data_proroga ?: $project->data_fine)) : '',
        ];

        $header = HeaderTemplate::resolve(HeaderTemplate::rowsFor($project, $layout), $values);

        $logo = null;

        if ($project->logo !== '' && is_file(ProjectService::logoDir() . '/' . $project->logo)) {
            $logo = ProjectService::logoDir() . '/' . $project->logo;
        }

        $slug = static fn (string $s) => trim(preg_replace('/[^A-Za-z0-9]+/', '_', $s), '_');

        return [
            'layout'        => $layout,
            'lang'          => $lang,
            'labels'        => $L,
            'header'        => array_map(static fn ($r) => [(string) $r[0], (string) ($r[1] ?? '')], $header),
            'logo'          => $logo,
            'note'          => (string) $project->note,
            'days'          => $n,
            'year'          => $year,
            'month'         => $month,
            'rows'          => $rows,
            'project_total' => $projectTotal,
            'person'        => $fullName,
            'project'       => $project->acronimo,
            'filename'      => \sprintf('%s_%s_%04d_%02d', $slug($person->uid), $slug($project->acronimo), $year, $month),
        ];
    }

    /** Pagina di esempio per l'anteprima dell'intestazione (dati fittizi, nessuna ora). */
    public function buildSample(int $projectId, int $year, int $month): array
    {
        $project = $this->projects->get($projectId) ?? throw new \RuntimeException('Progetto non trovato');
        $layout  = $project->layout ?: 'standard';
        $lang    = \in_array($project->table_lang ?? '', ['it', 'en'], true) && $project->table_lang !== ''
            ? $project->table_lang
            : (\in_array($layout, ['horizon', 'mise'], true) ? 'en' : 'it');
        $L       = self::LABELS[$lang];
        $n       = Time::daysInMonth($year, $month);
        $zero    = array_fill(0, $n, 0);
        $values  = HeaderTemplate::sampleValues($project);
        $values['mese_anno'] = Time::monthName($month, $lang === 'en') . ' ' . $year;

        $rows = [];

        foreach ($project->wps as $w) {
            if ($w->published) {
                $rows[] = ['kind' => 'wp', 'label' => $w->label, 'values' => $zero, 'total' => 0, 'show_zero' => false];
            }
        }

        foreach (['ordinary', 'sick', 'holiday', 'permit', 'other'] as $k) {
            $rows[] = ['kind' => 'cat', 'label' => $L[$k], 'values' => $zero, 'total' => 0, 'show_zero' => false];
        }

        $rows[] = ['kind' => 'total', 'label' => $L['total'], 'values' => $zero, 'total' => 0, 'show_zero' => false];

        $logo = $project->logo !== '' && is_file(ProjectService::logoDir() . '/' . $project->logo) ? ProjectService::logoDir() . '/' . $project->logo : null;

        return [
            'layout' => $layout, 'lang' => $lang, 'labels' => $L,
            'header' => HeaderTemplate::resolve(HeaderTemplate::rowsFor($project, $layout), $values),
            'logo' => $logo, 'note' => (string) $project->note, 'days' => $n, 'year' => $year, 'month' => $month,
            'rows' => $rows, 'project_total' => 0, 'person' => 'ANTEPRIMA', 'project' => $project->acronimo,
            'filename' => 'anteprima_' . preg_replace('/[^A-Za-z0-9]+/', '_', $project->acronimo),
        ];
    }

    /**
     * Mesi da esportare per un intervallo (es. 2025-01 .. 2025-12).
     *
     * @return array<int,array{0:int,1:int}>
     */
    public static function monthsBetween(string $from, string $to): array
    {
        $a   = new \DateTimeImmutable(substr($from, 0, 7) . '-01');
        $b   = new \DateTimeImmutable(substr($to, 0, 7) . '-01');
        $out = [];

        while ($a <= $b && \count($out) < 60) {
            $out[] = [(int) $a->format('Y'), (int) $a->format('n')];
            $a     = $a->modify('+1 month');
        }

        return $out;
    }
}
