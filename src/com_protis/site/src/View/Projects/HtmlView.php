<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Site\View\Projects;

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Service\ProjectService;
use Cnr\Component\Protis\Administrator\Service\Services;
use Cnr\Component\Protis\Site\View\BaseHtmlView;
use Joomla\CMS\Factory;

class HtmlView extends BaseHtmlView
{
    public array $items = [];
    public string $search = '';
    public array $layouts = ProjectService::LAYOUTS;
    public bool $archived = false;
    public int $countActive = 0;
    public int $countArchived = 0;

    protected function prepare(): void
    {
        $this->active    = 'projects';
        $this->pageTitle = 'Progetti';
        $this->search    = Factory::getApplication()->getInput()->getString('q', '');
        $all                 = Services::projects()->list(false, $this->search);
        $this->archived      = Factory::getApplication()->getInput()->getBool('archived', false);
        $this->countActive   = \count(array_filter($all, static fn ($p) => (int) $p->published === 1));
        $this->countArchived = \count($all) - $this->countActive;
        // interruttore Attivi / Archiviati
        $this->items = array_values(array_filter($all, fn ($p) => (int) $p->published === ($this->archived ? 0 : 1)));
    }
}
