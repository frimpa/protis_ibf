<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Site\Controller;

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Helper\Acl;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

class DisplayController extends BaseController
{
    protected $default_view = 'home';

    /** Viste riservate ai responsabili / amministratori */
    private const MANAGER_VIEWS = ['projects', 'project', 'people', 'person', 'report', 'codes'];
    private const ADMIN_VIEWS   = ['settings', 'log']; // log: vista rimossa nella 3.22.1, i file restano nelle installazioni aggiornate

    public function display($cachable = false, $urlparams = [])
    {
        $user = Acl::user();

        $view = $this->input->getCmd('view', $this->default_view);

        // utenti non autenticati: pagina di accesso PROTIS IBF (stesso stile dell'app / PWA)
        if ($user->guest) {
            // la Guida si legge anche prima di accedere (parte per gli utenti)
            if ($view !== 'guide') {
                $this->input->set('view', 'login');
            }

            return parent::display(false, $urlparams);
        }

        // persona disattivata in PROTIS IBF: si chiude la sessione (vale anche per gli account senza LDAP)
        $me = \Cnr\Component\Protis\Administrator\Service\Services::people()->forUser($user);

        if ($me && isset($me->active) && !(int) $me->active && !Acl::isSuperUser($user)) {
            $app = \Joomla\CMS\Factory::getApplication();
            $app->logout();
            $this->setRedirect(Route::_('index.php?option=com_protis&view=login', false), 'Il tuo profilo in PROTIS IBF è disattivato. Rivolgiti al referente di PROTIS IBF.', 'warning');

            return $this;
        }

        if ($view === 'login') {
            $this->setRedirect(Route::_('index.php?option=com_protis&view=home', false));

            return $this;
        }

        if ((\in_array($view, self::MANAGER_VIEWS, true) && !Acl::canManage()) || (\in_array($view, self::ADMIN_VIEWS, true) && !Acl::isAdmin())) {
            $this->setRedirect(Route::_('index.php?option=com_protis&view=home', false), 'Funzione non disponibile per il tuo profilo.', 'warning');

            return $this;
        }

        return parent::display(false, $urlparams);
    }
}
