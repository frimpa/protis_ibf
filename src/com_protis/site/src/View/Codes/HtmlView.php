<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Site\View\Codes;

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Service\CalendarService;
use Cnr\Component\Protis\Administrator\Service\Services;
use Cnr\Component\Protis\Site\View\BaseHtmlView;
use Joomla\CMS\Factory;

class HtmlView extends BaseHtmlView
{
    public array $items = [];
    public string $search = '';
    public ?object $lastDownload = null;
    public array $categories = CalendarService::CATEGORY_LABELS;

    protected function prepare(): void
    {
        $this->active    = 'codes';
        $this->pageTitle = 'Codici di assenza';
        $this->search    = Factory::getApplication()->getInput()->getString('q', '');
        $this->items     = Services::settings()->absenceCodes($this->search);
        unset($this->categories['tempo_lavorato']);
        $this->lastDownload = Services::settings()->lastCodesDownload();
    }
}
