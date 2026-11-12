<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Site\View\Report;

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Service\Services;
use Cnr\Component\Protis\Site\View\BaseHtmlView;
use Joomla\CMS\Factory;

class HtmlView extends BaseHtmlView
{
    public array $items = [];
    public array $projects = [];
    public int $projectFilter = 0;
    public string $from = '';
    public string $to = '';
    public bool $showArchived = false;

    protected function prepare(): void
    {
        $this->active        = 'report';
        $this->pageTitle     = 'Dashboard progetti';
        $in                  = Factory::getApplication()->getInput();
        $this->projectFilter = $in->getInt('project', 0);
        $this->from          = preg_match('/^\d{4}-\d{2}-\d{2}$/', $in->getString('from', '')) ? $in->getString('from') : '';
        $this->to            = preg_match('/^\d{4}-\d{2}-\d{2}$/', $in->getString('to', '')) ? $in->getString('to') : '';
        $this->showArchived  = $in->getBool('archived', false);
        $this->projects      = Services::projects()->list();

        $visible = \Cnr\Component\Protis\Administrator\Helper\Acl::canManageAllSites()
            ? null
            : array_map(static fn ($p) => (int) $p->id, Services::people()->listVisible($this->me));

        $items = Services::report()->projects($visible, $this->projectFilter ?: null, $this->from ?: null, $this->to ?: null);

        // interruttore Progetti attivi / Progetti archiviati (un progetto scelto dal menu si vede sempre)
        if (!$this->projectFilter) {
            $want  = $this->showArchived ? 0 : 1;
            $items = array_values(array_filter($items, static fn ($p) => (int) $p['published'] === $want));
        }

        // nascondi progetti senza persone visibili (responsabili di sede)
        $this->items = array_values(array_filter($items, static fn ($p) => $visible === null || $p['people']));
    }
}
