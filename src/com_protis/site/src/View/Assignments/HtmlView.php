<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Site\View\Assignments;

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Service\ProjectService;
use Cnr\Component\Protis\Administrator\Service\Services;
use Cnr\Component\Protis\Site\View\BaseHtmlView;
use Joomla\CMS\Factory;

class HtmlView extends BaseHtmlView
{
    public array $items = [];
    public array $people = [];
    public array $projects = [];
    public int $projectFilter = 0;
    public array $layouts = ProjectService::LAYOUTS;
    /** @var array<int,array<int,?int>> progetto => persona => monte ore (minuti) */
    public array $assignedMap = [];
    /** @var array<int,array> persona => dati + progetti associati */
    public array $groups = [];

    protected function prepare(): void
    {
        $this->active        = 'assignments';
        $this->pageTitle     = 'Associazioni';
        $this->projectFilter = Factory::getApplication()->getInput()->getInt('project', 0);

        // utente (non responsabile): solo le proprie associazioni, con la possibilità di aggiungerne
        if (!$this->canManage) {
            $this->setLayout('mine');
            $this->pageTitle = 'Le mie associazioni';
            $this->projects  = Services::projects()->list(true);
            $this->items     = $this->me ? Services::projects()->assignments(null, (int) $this->me->id) : [];
            $done            = $this->me ? $this->reportedMinutes([(int) $this->me->id]) : [];

            foreach ($this->items as $a) {
                $a->reported = $done[(int) $this->me->id][(int) $a->project_id] ?? 0;
            }

            return;
        }

        $this->people        = Services::people()->listVisible($this->me, '', true);
        $this->projects      = Services::projects()->list(true);
        $visible             = array_map(static fn ($p) => (int) $p->id, $this->people);
        $all                 = Services::projects()->assignments(null, null, $visible);
        $this->items         = $this->projectFilter
            ? array_values(array_filter($all, fn ($a) => (int) $a->project_id === $this->projectFilter))
            : $all;

        foreach ($all as $a) {
            $this->assignedMap[(int) $a->project_id][(int) $a->person_id] = $a->monteore_min !== null ? (int) $a->monteore_min : null;
        }

        // elenco raggruppato per persona: tutti i progetti di chi compare nel filtro
        $sede = [];

        foreach ($this->people as $pp) {
            $sede[(int) $pp->id] = (string) ($pp->sede_nome ?? '');
        }

        $inFilter = array_flip(array_map(static fn ($a) => (int) $a->person_id, $this->items));
        $done     = $this->reportedMinutes(array_keys($inFilter));

        foreach ($all as $a) {
            $pid = (int) $a->person_id;

            if (!isset($inFilter[$pid])) {
                continue;
            }

            $this->groups[$pid] ??= [
                'id'        => $pid,
                'name'      => trim($a->cognome . ' ' . $a->nome) ?: $a->uid,
                'matricola' => (string) $a->matricola,
                'sede'      => $sede[$pid] ?? '',
                'external'  => !empty($a->external),
                'person'    => (object) ['nome' => $a->nome, 'cognome' => $a->cognome, 'avatar' => $a->avatar ?? ''],
                'items'     => [],
                'monteore'  => 0,
            ];
            $a->reported = $done[$pid][(int) $a->project_id] ?? 0;
            $this->groups[$pid]['items'][]   = $a;
            $this->groups[$pid]['monteore'] += (int) $a->monteore_min;
        }

        uasort($this->groups, static fn ($x, $y) => strcasecmp($x['name'], $y['name']));
    }

    /** @return array<int,array<int,int>> persona => progetto => minuti rendicontati */
    private function reportedMinutes(array $personIds): array
    {
        if (!$personIds) {
            return [];
        }

        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $q  = $db->createQuery()
            ->select('h.person_id, w.project_id, SUM(h.minutes) AS m')
            ->from('#__protis_hours AS h')
            ->join('INNER', '#__protis_workpackages AS w', 'w.id = h.wp_id')
            ->where('h.person_id IN (' . implode(',', array_map('intval', $personIds)) . ')')
            ->group('h.person_id, w.project_id');
        $out = [];

        foreach ($db->setQuery($q)->loadObjectList() as $r) {
            $out[(int) $r->person_id][(int) $r->project_id] = (int) $r->m;
        }

        return $out;
    }
}
