<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Site\View\People;

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Service\Services;
use Cnr\Component\Protis\Site\View\BaseHtmlView;
use Joomla\CMS\Factory;

class HtmlView extends BaseHtmlView
{
    public array $items = [];
    public array $sites = [];
    public array $contracts = [];
    /** @var array<int,string> person_id => manager|admin */
    public array $roles = [];
    public string $search = '';
    /** scheda mostrata: ist = personale dell'istituto, ext = altri istituti, tech = account tecnici */
    public string $tab = 'ist';
    /** @var array<string,int> */
    public array $counts = ['ist' => 0, 'ext' => 0, 'tech' => 0];
    public string $sigla = '';

    protected function prepare(): void
    {
        $this->active    = 'people';
        $this->pageTitle = 'Persone';
        $this->search    = Factory::getApplication()->getInput()->getString('q', '');
        $all             = Services::people()->listVisible($this->me, $this->search);
        $this->roles     = Services::roles()->rolesFor($all);
        $this->sites     = Services::people()->instituteSites();
        $this->sigla     = trim((string) ($this->sites[0]->sigla ?? ''));

        // account tecnici: amministratori senza matricola (es. l'utente amministratore di Joomla), non rendicontano
        $groups = ['ist' => [], 'ext' => [], 'tech' => []];

        foreach ($all as $p) {
            $key = (($this->roles[(int) $p->id] ?? '') === 'admin' && empty($p->matricola) && !(int) $p->n_progetti)
                ? 'tech' : (!empty($p->external) ? 'ext' : 'ist');
            $groups[$key][] = $p;
        }

        $this->counts = array_map('count', $groups);
        $tab          = Factory::getApplication()->getInput()->getCmd('gruppo', 'ist');
        $this->tab    = \in_array($tab, ['ist', 'ext'], true) ? $tab : 'ist';
        $this->items  = $groups[$this->tab];
        $this->contracts = Services::people()->contractTypes();
    }
}
