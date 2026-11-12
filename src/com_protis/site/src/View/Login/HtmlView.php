<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Site\View\Login;

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Site\View\BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

class HtmlView extends BaseHtmlView
{
    public string $return = '';

    protected function prepare(): void
    {
        $this->pageTitle = 'Accesso';
        $uri             = Uri::getInstance();
        $target          = $uri->getVar('view') && $uri->getVar('view') !== 'login'
            ? $uri->toString()
            : Route::_('index.php?option=com_protis&view=home', false, 0, true);
        $this->return    = base64_encode($target);
    }
}
