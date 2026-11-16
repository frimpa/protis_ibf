<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Site\View\Person;

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Service\ProjectService;
use Cnr\Component\Protis\Administrator\Service\Services;
use Cnr\Component\Protis\Site\View\BaseHtmlView;
use Joomla\CMS\Factory;

class HtmlView extends BaseHtmlView
{
    public ?object $item = null;
    public array $assignments = [];
    public array $projects = [];
    public array $sites = [];

    public array $epasSites = [];
    /** Ruolo PROTIS IBF dell'account: user, manager, admin; null = nessun account ancora */
    public ?string $role = null;
    /** minuti già rendicontati sui progetti */
    public int $reported = 0;
    public array $contracts = [];
    public array $layouts = ProjectService::LAYOUTS;

    protected function prepare(): void
    {
        $this->active = 'people';
        $id           = Factory::getApplication()->getInput()->getInt('id', 0);
        $p            = $id ? Services::people()->get($id) : null;

        if ($p && !Services::people()->canAccess($this->me, $p)) {
            $p = null;
        }

        $this->item        = $p;
        $this->sites       = Services::people()->instituteSites();
        $this->epasSites   = Services::people()->externalEpasSites();
        $this->contracts   = Services::people()->contractTypes();
        $this->projects    = Services::projects()->list(true);
        $this->assignments = $p ? Services::projects()->assignments(null, (int) $p->id) : [];
        $this->role        = $p ? Services::roles()->roleOf($p) : null;
        $this->reported    = $p ? Services::people()->reportedMinutes((int) $p->id) : 0;
        $this->pageTitle   = $p ? trim($p->cognome . ' ' . $p->nome) : 'Nuova persona';
    }
}
