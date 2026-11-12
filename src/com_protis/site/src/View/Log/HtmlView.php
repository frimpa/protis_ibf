<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Site\View\Log;

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Service\Services;
use Cnr\Component\Protis\Site\View\BaseHtmlView;
use Joomla\CMS\Factory;

class HtmlView extends BaseHtmlView
{
    public array $items = [];

    protected function prepare(): void
    {
        $this->active    = 'log';
        $this->pageTitle = 'Registro attività';
        $pid             = Factory::getApplication()->getInput()->getInt('person', 0) ?: null;
        $this->items     = Services::settings()->recentLog(300, $pid);
    }
}
