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
    public string $search = '';

    protected function prepare(): void
    {
        $this->active    = 'people';
        $this->pageTitle = 'Persone';
        $this->search    = Factory::getApplication()->getInput()->getString('q', '');
        $this->items     = Services::people()->listVisible($this->me, $this->search);
        $this->sites     = Services::people()->instituteSites();
        $this->contracts = Services::people()->contractTypes();
    }
}
