<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * Accesso dalla pagina di login PROTIS IBF (in caso di errore resta nell'app invece di passare a com_users).
 */
class AuthController extends BaseController
{
    public function login(): void
    {
        $this->checkToken('post');

        $return = base64_decode($this->input->post->get('return', '', 'BASE64'));

        if (!$return || !Uri::isInternal($return)) {
            $return = Route::_('index.php?option=com_protis&view=home', false);
        }

        $credentials = [
            'username'  => $this->input->post->get('username', '', 'USERNAME'),
            'password'  => $this->input->post->get('password', '', 'RAW'),
            'secretkey' => $this->input->post->get('secretkey', '', 'RAW'),
        ];

        $options = ['remember' => $this->input->post->getBool('remember', false), 'return' => $return, 'silent' => false];

        if (true !== $this->app->login($credentials, $options)) {
            $this->setRedirect(Route::_('index.php?option=com_protis&view=login', false));

            return;
        }

        if ($options['remember']) {
            $this->app->setUserState('rememberLogin', true);
        }

        $this->setRedirect($return);
    }
}
