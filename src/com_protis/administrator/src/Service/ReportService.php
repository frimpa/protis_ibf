<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * Dashboard rendicontazione: ore per progetto / persona / WP e residuo rispetto al monte ore.
 */
final class ReportService extends AbstractService
{
    /**
     * @param  int[]|null  $personIds  limita alle persone visibili (null = tutte)
     *
     * @return array<int,array>
     */
    public function projects(?array $personIds, ?int $projectId = null, ?string $from = null, ?string $to = null): array
    {
        $q = $this->db->createQuery()->select('*')->from('#__protis_projects')->order('published DESC, acronimo');

        if ($projectId) {
            $q->where('id = ' . (int) $projectId);
        }

        $projects = $this->db->setQuery($q)->loadObjectList('id');

        if (!$projects) {
            return [];
        }

        $ids = implode(',', array_map('intval', array_keys($projects)));

        // ore aggregate per progetto/persona/WP
        $hq = $this->db->createQuery()
            ->select('w.project_id, h.person_id, h.wp_id, SUM(h.minutes) AS minutes, MIN(h.day) AS first_day, MAX(h.day) AS last_day')
            ->from('#__protis_hours AS h')
            ->join('INNER', '#__protis_workpackages AS w', 'w.id = h.wp_id')
            ->join('INNER', '#__protis_projects AS p', 'p.id = w.project_id')
            ->where('w.project_id IN (' . $ids . ')')
            ->where('h.day >= COALESCE(p.data_inizio, \'1970-01-01\')')
            ->group('w.project_id, h.person_id, h.wp_id');

        if ($from) {
            $hq->where('h.day >= ' . $this->db->quote($from));
        }

        if ($to) {
            $hq->where('h.day <= ' . $this->db->quote($to));
        }

        if ($personIds !== null) {
            $hq->where($personIds ? 'h.person_id IN (' . implode(',', array_map('intval', $personIds)) . ')' : '0');
        }

        $agg = [];

        foreach ($this->db->setQuery($hq)->loadObjectList() as $r) {
            $agg[(int) $r->project_id][(int) $r->person_id][(int) $r->wp_id] = (int) $r->minutes;
        }

        $wpq   = $this->db->createQuery()->select('id, project_id, label, published')->from('#__protis_workpackages')
            ->where('project_id IN (' . $ids . ')')->order('ordering, id');
        $wpAll = [];

        foreach ($this->db->setQuery($wpq)->loadObjectList() as $w) {
            $wpAll[(int) $w->project_id][(int) $w->id] = $w;
        }

        $aq = $this->db->createQuery()
            ->select('a.project_id, a.person_id, a.monteore_min, pe.nome, pe.cognome, pe.uid, s.sede')
            ->from('#__protis_assignments AS a')
            ->join('INNER', '#__protis_people AS pe', 'pe.id = a.person_id')
            ->join('LEFT', '#__protis_sites AS s', 's.id = pe.site_id')
            ->where('a.project_id IN (' . $ids . ')');

        if ($personIds !== null) {
            $aq->where($personIds ? 'a.person_id IN (' . implode(',', array_map('intval', $personIds)) . ')' : '0');
        }

        $assign = [];

        foreach ($this->db->setQuery($aq)->loadObjectList() as $a) {
            $assign[(int) $a->project_id][(int) $a->person_id] = $a;
        }

        // persone con ore ma non più associate
        $missing = [];

        foreach ($agg as $pid => $byPerson) {
            foreach (array_keys($byPerson) as $personId) {
                if (!isset($assign[$pid][$personId])) {
                    $missing[$personId] = true;
                }
            }
        }

        $names = [];

        if ($missing) {
            $nq = $this->db->createQuery()->select('p.id, p.nome, p.cognome, p.uid, s.sede')->from('#__protis_people AS p')
                ->join('LEFT', '#__protis_sites AS s', 's.id = p.site_id')
                ->where('p.id IN (' . implode(',', array_keys($missing)) . ')');
            $names = $this->db->setQuery($nq)->loadObjectList('id');
        }

        $out = [];

        foreach ($projects as $pid => $p) {
            $people = [];
            $keys   = array_unique(array_merge(array_keys($assign[$pid] ?? []), array_keys($agg[$pid] ?? [])));

            foreach ($keys as $personId) {
                $a     = $assign[$pid][$personId] ?? null;
                $n     = $a ?? ($names[$personId] ?? null);
                $byWp  = $agg[$pid][$personId] ?? [];
                $total = array_sum($byWp);
                $monte = $a && $a->monteore_min !== null ? (int) $a->monteore_min : null;

                $people[] = [
                    'person_id' => $personId,
                    'name'      => $n ? trim($n->cognome . ' ' . $n->nome) : '#' . $personId,
                    'uid'       => $n->uid ?? '',
                    'sede'      => $n->sede ?? '',
                    'assigned'  => (bool) $a,
                    'total'     => $total,
                    'monteore'  => $monte,
                    'residuo'   => $monte !== null ? $monte - $total : null,
                    'percent'   => $monte ? (int) round($total * 100 / $monte) : null,
                    'wps'       => array_map(
                        static fn ($wpId, $min) => ['id' => $wpId, 'label' => $wpAll[$pid][$wpId]->label ?? ('WP ' . $wpId), 'total' => $min],
                        array_keys($byWp),
                        $byWp
                    ),
                ];
            }

            usort($people, static fn ($x, $y) => strcasecmp($x['name'], $y['name']));

            $total = array_sum(array_column($people, 'total'));
            $monte = array_sum(array_map(static fn ($x) => $x['monteore'] ?? 0, $people));

            $wpTot = [];

            foreach ($people as $pp) {
                foreach ($pp['wps'] as $w) {
                    $wpTot[$w['id']] = ($wpTot[$w['id']] ?? 0) + $w['total'];
                }
            }

            $out[] = [
                'id'         => (int) $pid,
                'acronimo'   => $p->acronimo,
                'cup'        => $p->cup ?: ($p->grant_agreement ?? ''),
                'titolo'     => $p->titolo,
                'published'  => (bool) $p->published,
                'start'      => $p->data_inizio,
                'end'        => $p->data_proroga ?: $p->data_fine,
                'logo'       => ProjectService::logoUrl((string) $p->logo),
                'total'      => $total,
                'monteore'   => $monte ?: null,
                'percent'    => $monte ? (int) round($total * 100 / $monte) : null,
                'people'     => $people,
                'wps'        => array_map(
                    static fn ($w) => ['id' => (int) $w->id, 'label' => $w->label, 'total' => $wpTot[(int) $w->id] ?? 0],
                    array_values($wpAll[$pid] ?? [])
                ),
            ];
        }

        return $out;
    }
}
