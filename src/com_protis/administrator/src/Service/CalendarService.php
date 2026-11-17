<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Administrator\Service;

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Helper\Holidays;
use Cnr\Component\Protis\Administrator\Helper\Time;
use Joomla\Database\ParameterType;

/**
 * Logica del calendario mensile: presenze, ore rendicontabili e ore per WP.
 *
 * Regole riprese dalla V.2:
 *  - ore rendicontabili del giorno (capacità) = tempo lavorato + lavoro agile + telelavoro + missione
 *  - la somma delle ore sui WP non può superare la capacità del giorno
 *  - "Attività ordinaria" = capacità − ore su tutti i progetti
 *  - riempimento/distribuzione automatica saltano i giorni con assenze "bloccanti"
 *    (malattia, ferie, permessi, altro, codici 91/661G...) configurabili nei codici di assenza
 */
final class CalendarService extends AbstractService
{
    public const CATEGORIES = [
        'smartworking' => 'smart_min',
        'telelavoro'   => 'tele_min',
        'missione'     => 'mission_min',
        'malattia'     => 'sick_min',
        'ferie'        => 'holiday_min',
        'permessi'     => 'permit_min',
        'altro'        => 'other_min',
    ];

    public const CATEGORY_LABELS = [
        'tempo_lavorato' => 'Presenza',
        'smartworking'   => 'Lavoro Agile',
        'telelavoro'     => 'Telelavoro',
        'missione'       => 'Missione',
        'malattia'       => 'Malattia',
        'ferie'          => 'Ferie',
        'permessi'       => 'Permessi',
        'altro'          => 'Altro',
    ];

    public static function capacity(object|array $day): int
    {
        $d = (object) $day;

        return (int) ($d->worked_min ?? 0) + (int) ($d->smart_min ?? 0) + (int) ($d->tele_min ?? 0) + (int) ($d->mission_min ?? 0);
    }

    /** @return array<string,object> Y-m-d => riga #__protis_days */
    public function days(int $personId, string $from, string $to): array
    {
        $q = $this->db->createQuery()
            ->select('*')->from('#__protis_days')
            ->where('person_id = :p')->where('day BETWEEN :a AND :b')
            ->bind(':p', $personId, ParameterType::INTEGER)->bind(':a', $from)->bind(':b', $to)
            ->order('day');

        $out = [];

        foreach ($this->db->setQuery($q)->loadObjectList() as $r) {
            $out[$r->day] = $r;
        }

        return $out;
    }

    /** @return array<string,array<int,int>> Y-m-d => [wp_id => minuti] */
    public function hours(int $personId, string $from, string $to): array
    {
        $q = $this->db->createQuery()
            ->select('day, wp_id, minutes')->from('#__protis_hours')
            ->where('person_id = :p')->where('day BETWEEN :a AND :b')->where('minutes > 0')
            ->bind(':p', $personId, ParameterType::INTEGER)->bind(':a', $from)->bind(':b', $to);

        $out = [];

        foreach ($this->db->setQuery($q)->loadObjectList() as $r) {
            $out[$r->day][(int) $r->wp_id] = (int) $r->minutes;
        }

        return $out;
    }

    /** @return array<string,object> codice (upper) => {category, minutes, description, blocks_fill} */
    public function absenceCodes(): array
    {
        static $cache = null;

        if ($cache === null) {
            $cache = [];
            $rows  = $this->db->setQuery($this->db->createQuery()->select('*')->from('#__protis_absence_codes'))->loadObjectList();

            foreach ($rows as $r) {
                $cache[strtoupper($r->code)] = $r;
            }
        }

        return $cache;
    }

    /**
     * Dati completi del mese per la UI.
     */
    public function month(object $person, int $year, int $month, ProjectService $projects): array
    {
        [$from, $to] = Time::monthRange($year, $month);
        $days        = $this->days((int) $person->id, $from, $to);
        $hours       = $this->hours((int) $person->id, $from, $to);
        $codes       = $this->absenceCodes();
        $holidays    = Holidays::forYear($year, $person->sede_nome ?? null);
        $assigned    = $projects->forPerson((int) $person->id);

        // WP noti (anche di progetti non più assegnati ma con ore nel mese)
        $wpIndex = [];

        foreach ($assigned as $p) {
            foreach ($p->wps as $w) {
                $wpIndex[$w->id] = ['project_id' => (int) $p->id, 'acronimo' => $p->acronimo, 'cup' => $p->cup, 'label' => $w->label];
            }
        }

        $orphanWp = [];

        foreach ($hours as $byWp) {
            foreach (array_keys($byWp) as $wpId) {
                if (!isset($wpIndex[$wpId])) {
                    $orphanWp[$wpId] = true;
                }
            }
        }

        if ($orphanWp) {
            $q = $this->db->createQuery()
                ->select("w.id, w.label, p.id AS project_id, p.acronimo, COALESCE(NULLIF(p.cup, ''), p.grant_agreement) AS cup")
                ->from('#__protis_workpackages AS w')->join('INNER', '#__protis_projects AS p', 'p.id = w.project_id')
                ->where('w.id IN (' . implode(',', array_map('intval', array_keys($orphanWp))) . ')');

            foreach ($this->db->setQuery($q)->loadObjectList() as $w) {
                $wpIndex[(int) $w->id] = ['project_id' => (int) $w->project_id, 'acronimo' => $w->acronimo, 'cup' => $w->cup, 'label' => $w->label, 'orphan' => true];
            }
        }

        $outDays  = [];
        $totCap   = 0;
        $totRep   = 0;
        $byWp     = [];
        $catTotal = array_fill_keys(array_keys(self::CATEGORY_LABELS), 0);
        $n        = Time::daysInMonth($year, $month);

        for ($i = 1; $i <= $n; $i++) {
            $date = \sprintf('%04d-%02d-%02d', $year, $month, $i);
            $dow  = (int) date('N', strtotime($date));
            $d    = $days[$date] ?? null;
            $h    = $hours[$date] ?? [];
            $cap  = $d ? self::capacity($d) : 0;
            $rep  = array_sum($h);

            $codeList = [];

            if ($d && $d->absence_codes !== '') {
                foreach (explode(',', $d->absence_codes) as $c) {
                    $c          = trim($c);
                    $info       = $codes[strtoupper($c)] ?? null;
                    $codeList[] = [
                        'code'     => $c,
                        'category' => $info->category ?? null,
                        'label'    => $info ? (self::CATEGORY_LABELS[$info->category] ?? $info->category) : 'Codice non censito',
                        'desc'     => $info->description ?? '',
                    ];
                }
            }

            $row = [
                'date'     => $date,
                'day'      => $i,
                'dow'      => $dow,
                'weekend'  => $dow >= 6,
                'holiday'  => $holidays[$date] ?? null,
                'source'   => $d->source ?? null,
                'blocked'  => (bool) ($d->blocked ?? false),
                'worked'   => (int) ($d->worked_min ?? 0),
                'cat'      => [],
                'codes'    => $codeList,
                'capacity' => $cap,
                'reported' => $rep,
                'hours'    => (object) $h,
            ];

            foreach (self::CATEGORIES as $cat => $col) {
                $row['cat'][$cat] = (int) ($d->$col ?? 0);
                $catTotal[$cat] += $row['cat'][$cat];
            }

            $catTotal['tempo_lavorato'] += $row['worked'];
            $totCap += $cap;
            $totRep += $rep;

            foreach ($h as $wpId => $min) {
                $byWp[$wpId] = ($byWp[$wpId] ?? 0) + $min;
            }

            $outDays[] = $row;
        }

        // Totali per progetto / WP
        $byProject = [];

        foreach ($byWp as $wpId => $min) {
            $w   = $wpIndex[$wpId] ?? ['project_id' => 0, 'acronimo' => '?', 'cup' => '', 'label' => 'WP ' . $wpId];
            $pid = $w['project_id'];

            $byProject[$pid] ??= ['id' => $pid, 'acronimo' => $w['acronimo'], 'cup' => $w['cup'], 'total' => 0, 'wps' => []];
            $byProject[$pid]['total'] += $min;
            $byProject[$pid]['wps'][]  = ['id' => $wpId, 'label' => $w['label'], 'total' => $min];
        }

        usort($byProject, static fn ($a, $b) => strcasecmp($a['acronimo'], $b['acronimo']));

        return [
            'person' => [
                'id'        => (int) $person->id,
                'uid'       => $person->uid,
                'nome'      => trim($person->nome . ' ' . $person->cognome),
                'matricola' => $person->matricola,
                'epas'      => PeopleService::usesEpas($person),
                'external'  => !empty($person->external),
                'istituto'  => (string) ($person->ldap_gruppo ?? ''),
                'sede'      => $person->sede_nome ?? '',
            ],
            'year'     => $year,
            'month'    => $month,
            'monthName' => Time::monthName($month),
            'projects' => array_map(static fn ($p) => [
                'id'       => (int) $p->id,
                'acronimo' => $p->acronimo,
                'cup'      => $p->cup,
                'wps'      => $p->wps,
            ], $assigned),
            'wpIndex'  => (object) $wpIndex,
            'days'     => $outDays,
            'totals'   => [
                'capacity'   => $totCap,
                'reported'   => $totRep,
                'ordinary'   => max(0, $totCap - $totRep),
                'categories' => $catTotal,
                'projects'   => array_values($byProject),
            ],
        ];
    }

    /** WP su cui la persona può rendicontare. @return int[] */
    public function allowedWps(int $personId, ProjectService $projects): array
    {
        $ids = [];

        foreach ($projects->forPerson($personId) as $p) {
            foreach ($p->wps as $w) {
                $ids[] = (int) $w->id;
            }
        }

        return $ids;
    }

    /**
     * Salva le ore di un giorno.
     *
     * @param  array<int,int>  $minutesByWp  wp_id => minuti (0 = cancella)
     *
     * @return array{reported:int, capacity:int}
     */
    public function saveDay(int $personId, string $date, array $minutesByWp, ProjectService $projects, int $userId): array
    {
        $this->assertDate($date);
        $allowed = $this->allowedWps($personId, $projects);
        $day     = $this->days($personId, $date, $date)[$date] ?? null;
        $cap     = $day ? self::capacity($day) : 0;
        $current = $this->hours($personId, $date, $date)[$date] ?? [];

        $new = $current;

        foreach ($minutesByWp as $wpId => $min) {
            $wpId = (int) $wpId;
            $min  = max(0, (int) $min);

            if (!\in_array($wpId, $allowed, true) && !isset($current[$wpId])) {
                throw new \InvalidArgumentException('Work package non associato alla persona.');
            }

            $new[$wpId] = $min;
        }

        $total = array_sum($new);

        if ($total > $cap) {
            throw new \DomainException(\sprintf(
                'Le ore inserite (%s) superano le ore rendicontabili del giorno (%s).',
                Time::format($total),
                Time::format($cap)
            ));
        }

        foreach ($new as $wpId => $min) {
            if (($current[$wpId] ?? 0) === $min) {
                continue;
            }

            $this->writeHour($personId, $date, (int) $wpId, $min, $userId);
        }

        return ['reported' => $total, 'capacity' => $cap];
    }

    private function writeHour(int $personId, string $date, int $wpId, int $minutes, int $userId): void
    {
        if ($minutes <= 0) {
            $q = $this->db->createQuery()->delete('#__protis_hours')
                ->where('person_id = ' . $personId)->where('wp_id = ' . $wpId)
                ->where('day = ' . $this->db->quote($date));
            $this->db->setQuery($q)->execute();

            return;
        }

        $this->upsert('#__protis_hours', [
            'person_id'   => $personId,
            'day'         => $date,
            'wp_id'       => $wpId,
            'minutes'     => $minutes,
            'modified'    => $this->now(),
            'modified_by' => $userId,
        ], ['minutes', 'modified', 'modified_by']);
    }

    /**
     * Inserimento manuale presenze (personale senza matricola / timbrature ePAS).
     *
     * @param  array  $values  ['worked'=>min, 'smartworking'=>min, ...]
     */
    public function saveManualDay(int $personId, string $date, array $values): void
    {
        $this->assertDate($date);

        $data = [
            'person_id'  => $personId,
            'day'        => $date,
            'worked_min' => max(0, (int) ($values['worked'] ?? 0)),
            'source'     => 'manual',
            'modified'   => $this->now(),
            'blocked'    => 0,
        ];

        foreach (self::CATEGORIES as $cat => $col) {
            $data[$col] = max(0, (int) ($values[$cat] ?? 0));
        }

        // giornata bloccata per il riempimento automatico se c'è un'assenza "piena"
        $data['blocked'] = ($data['sick_min'] + $data['holiday_min'] + $data['permit_min'] + $data['other_min']) > 0 ? 1 : 0;

        $empty = array_sum(array_intersect_key($data, array_flip(array_merge(['worked_min'], array_values(self::CATEGORIES))))) === 0;

        if ($empty) {
            $this->db->setQuery(
                $this->db->createQuery()->delete('#__protis_days')
                    ->where('person_id = ' . $personId)->where('day = ' . $this->db->quote($date))
            )->execute();

            return;
        }

        $this->upsert('#__protis_days', $data, array_values(array_diff(array_keys($data), ['person_id', 'day'])));
    }

    /** Cancella ore WP oltre la capacità di un giorno (dopo modifica presenze). Ritorna i minuti tagliati. */
    public function trimToCapacity(int $personId, string $date, int $userId): int
    {
        $day = $this->days($personId, $date, $date)[$date] ?? null;
        $cap = $day ? self::capacity($day) : 0;
        $h   = $this->hours($personId, $date, $date)[$date] ?? [];
        $tot = array_sum($h);

        if ($tot <= $cap) {
            return 0;
        }

        $excess = $tot - $cap;
        arsort($h);

        foreach ($h as $wpId => $min) {
            if ($excess <= 0) {
                break;
            }

            $cut = min($min, $excess);
            $this->writeHour($personId, $date, (int) $wpId, $min - $cut, $userId);
            $excess -= $cut;
        }

        return $tot - $cap;
    }

    /**
     * "Riempi mese di un progetto": assegna a un WP le ore rendicontabili di ogni giorno utile.
     *
     * @param  string  $mode  'replace' = comportamento V.2 (azzera gli altri WP del giorno),
     *                        'residual' = usa solo le ore ancora libere
     */
    public function fillMonth(int $personId, int $year, int $month, int $wpId, string $mode, ProjectService $projects, int $userId): array
    {
        if (!\in_array($wpId, $this->allowedWps($personId, $projects), true)) {
            throw new \InvalidArgumentException('Work package non associato alla persona.');
        }

        [$from, $to] = Time::monthRange($year, $month);
        $days        = $this->days($personId, $from, $to);
        $hours       = $this->hours($personId, $from, $to);
        $assigned    = 0;
        $touched     = 0;

        foreach ($days as $date => $d) {
            $cap = self::capacity($d);

            if ($d->blocked || $cap <= 0) {
                continue;
            }

            $h = $hours[$date] ?? [];

            if ($mode === 'replace') {
                foreach ($h as $otherWp => $min) {
                    if ((int) $otherWp !== $wpId) {
                        $this->writeHour($personId, $date, (int) $otherWp, 0, $userId);
                    }
                }

                $value = $cap;
            } else {
                $others = array_sum($h) - ($h[$wpId] ?? 0);
                $value  = max(0, $cap - $others);
            }

            if ($value !== ($h[$wpId] ?? 0)) {
                $this->writeHour($personId, $date, $wpId, $value, $userId);
                $touched++;
            }

            $assigned += $value;
        }

        $this->log('calendar.fill', "Riempimento mese $month/$year su WP $wpId ($mode): " . Time::format($assigned), $personId);

        return ['assigned' => $assigned, 'days' => $touched];
    }

    /**
     * Distribuisce un monte ore mensile per WP sui giorni utili.
     *
     * @param  array<int,int>  $targets      wp_id => minuti richiesti nel mese (i WP indicati vengono prima azzerati)
     * @param  string          $mode         'uniform' | 'random'
     * @param  int             $granularity  minuti (1, 5, 15, 30, 60)
     */
    public function distribute(int $personId, int $year, int $month, array $targets, string $mode, int $granularity, ProjectService $projects, int $userId): array
    {
        $allowed = $this->allowedWps($personId, $projects);
        $targets = array_filter(array_map('intval', $targets), static fn ($m) => $m >= 0);

        foreach (array_keys($targets) as $wpId) {
            if (!\in_array((int) $wpId, $allowed, true)) {
                throw new \InvalidArgumentException('Work package non associato alla persona.');
            }
        }

        [$from, $to] = Time::monthRange($year, $month);
        $days        = $this->days($personId, $from, $to);
        $hours       = $this->hours($personId, $from, $to);

        // capacità libera giornaliera escludendo i WP che stiamo ridistribuendo
        $caps = [];

        foreach ($days as $date => $d) {
            if ($d->blocked) {
                continue;
            }

            $h      = $hours[$date] ?? [];
            $others = 0;

            foreach ($h as $wpId => $min) {
                if (!\array_key_exists($wpId, $targets)) {
                    $others += $min;
                }
            }

            $free = self::capacity($d) - $others;

            if ($free > 0) {
                $caps[$date] = $free;
            }
        }

        $requested = array_sum($targets);
        $available = array_sum($caps);

        if ($requested > $available) {
            throw new \DomainException(\sprintf(
                'Ore richieste (%s) superiori alle ore rendicontabili libere del mese (%s).',
                Time::format($requested),
                Time::format($available)
            ));
        }

        // azzera i WP selezionati nel mese
        foreach ($hours as $date => $h) {
            foreach ($h as $wpId => $min) {
                if (\array_key_exists($wpId, $targets)) {
                    $this->writeHour($personId, $date, (int) $wpId, 0, $userId);
                }
            }
        }

        $granularity = \in_array($granularity, [1, 5, 10, 15, 30, 60], true) ? $granularity : 1;
        $result      = [];

        // WP più grandi per primi: lasciano ai piccoli lo "spazio" più regolare
        arsort($targets);

        foreach ($targets as $wpId => $req) {
            if ($req <= 0) {
                continue;
            }

            $weights = [];

            foreach ($caps as $date => $c) {
                $weights[$date] = $mode === 'random' ? $c * (0.4 + mt_rand() / mt_getrandmax() * 1.2) : $c;
            }

            $alloc = self::allocate($req, $caps, $weights, $granularity);

            foreach ($alloc as $date => $min) {
                if ($min > 0) {
                    $this->writeHour($personId, $date, (int) $wpId, $min, $userId);
                    $caps[$date] -= $min;
                }
            }

            $result[$wpId] = array_sum($alloc);
        }

        $this->log('calendar.distribute', "Distribuzione $mode mese $month/$year: " . json_encode(array_map([Time::class, 'format'], $result)), $personId);

        return ['assigned' => $result, 'requested' => $requested];
    }

    /**
     * Ripartisce $req minuti sui giorni in proporzione ai pesi, senza superare le capacità.
     *
     * @param  array<string,int>    $caps
     * @param  array<string,float>  $weights
     *
     * @return array<string,int>
     */
    public static function allocate(int $req, array $caps, array $weights, int $granularity = 1): array
    {
        $g     = max(1, $granularity);
        $alloc = array_fill_keys(array_keys($caps), 0);

        if ($req <= 0 || !$caps) {
            return $alloc;
        }

        // si lavora a blocchi interi: ogni giorno riceve un multiplo del blocco, senza superare la sua capacità
        $units = intdiv($req, $g);
        $rest  = $req - $units * $g;
        $room  = [];
        $given = [];

        foreach ($caps as $d => $c) {
            $room[$d]  = intdiv(max(0, (int) $c), $g);
            $given[$d] = 0;
        }

        $left = $units;

        for ($iter = 0; $iter < 200 && $left > 0; $iter++) {
            $open = array_values(array_filter(array_keys($caps), static fn ($d) => $given[$d] < $room[$d]));

            if (!$open) {
                break;
            }

            $wSum = 0.0;

            foreach ($open as $d) {
                $wSum += max(0.0001, $weights[$d] ?? 1);
            }

            $frac   = [];
            $placed = 0;

            foreach ($open as $d) {
                $share     = $left * max(0.0001, $weights[$d] ?? 1) / $wSum;
                $give      = min((int) floor($share), $room[$d] - $given[$d], $left - $placed);
                $given[$d] += $give;
                $placed    += $give;
                $frac[$d]  = $share - floor($share);
            }

            if ($placed === 0) {
                // meno blocchi che giorni: un blocco ciascuno ai giorni con la quota più alta
                arsort($frac);

                foreach (array_keys($frac) as $d) {
                    if ($placed >= $left) {
                        break;
                    }

                    if ($given[$d] < $room[$d]) {
                        $given[$d]++;
                        $placed++;
                    }
                }
            }

            $left -= $placed;
        }

        foreach ($given as $d => $u) {
            $alloc[$d] = $u * $g;
        }

        // minuti che non formano un blocco intero (o blocchi che non entrano interi): sui giorni con più spazio
        $extra = $rest + $left * $g;

        if ($extra > 0) {
            $free = [];

            foreach ($caps as $d => $c) {
                $free[$d] = (int) $c - $alloc[$d];
            }

            arsort($free);

            foreach ($free as $d => $f) {
                if ($extra <= 0) {
                    break;
                }

                $give = min($f, $extra);

                if ($give > 0) {
                    $alloc[$d] += $give;
                    $extra     -= $give;
                }
            }
        }

        return $alloc;
    }

    /** Svuota le ore WP del mese (tutte o solo di un progetto). */
    public function clearMonth(int $personId, int $year, int $month, ?int $projectId, int $userId, ?int $wpId = null): int
    {
        [$from, $to] = Time::monthRange($year, $month);
        $q           = $this->db->createQuery()->delete('#__protis_hours')
            ->where('person_id = ' . $personId)
            ->where('day BETWEEN ' . $this->db->quote($from) . ' AND ' . $this->db->quote($to));

        if ($wpId) {
            $q->where('wp_id = ' . (int) $wpId);
        } elseif ($projectId) {
            $q->where('wp_id IN (SELECT id FROM #__protis_workpackages WHERE project_id = ' . (int) $projectId . ')');
        }

        $this->db->setQuery($q)->execute();
        $n = $this->db->getAffectedRows();
        $this->log('calendar.clear', "Svuotate $n righe ore mese $month/$year" . ($wpId ? " WP $wpId" : ($projectId ? " progetto $projectId" : '')), $personId);

        return $n;
    }

    private function assertDate(string $date): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
            throw new \InvalidArgumentException('Data non valida.');
        }
    }
}
