<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Site\View\Project;

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Service\ProjectService;
use Cnr\Component\Protis\Administrator\Service\Services;
use Cnr\Component\Protis\Site\View\BaseHtmlView;
use Joomla\CMS\Factory;

class HtmlView extends BaseHtmlView
{
    public ?object $item = null;
    public array $assignments = [];
    public array $people = [];
    public array $sites = [];
    public array $layouts = ProjectService::LAYOUTS;

    protected function prepare(): void
    {
        $this->active = 'projects';
        $id           = Factory::getApplication()->getInput()->getInt('id', 0);
        $this->item   = $id ? Services::projects()->get($id) : null;
        $this->sites  = Services::people()->instituteSites();

        if ($this->item) {
            $visible           = array_map(static fn ($p) => (int) $p->id, Services::people()->listVisible($this->me));
            $this->assignments = Services::projects()->assignments($id, null, $visible);
            $this->people      = Services::people()->listVisible($this->me, '', true);
        }

        $this->pageTitle = $this->item ? $this->item->acronimo : 'Nuovo progetto';
    }
}
