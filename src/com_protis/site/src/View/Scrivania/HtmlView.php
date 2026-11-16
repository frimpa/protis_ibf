<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Site\View\Scrivania;

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Site\Controller\ToolsController;
use Cnr\Component\Protis\Site\View\BaseHtmlView;

/** Scrivania PDF dentro PROTIS IBF (in un riquadro dello stesso sito). */
class HtmlView extends BaseHtmlView
{
    public bool $available = false;

    protected function prepare(): void
    {
        $this->active    = 'scrivania';
        $this->pageTitle = 'Scrivania PDF';
        $this->available = ToolsController::scrivaniaFile() !== null;
    }
}
