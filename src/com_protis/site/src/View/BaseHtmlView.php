<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Component\Protis\Site\View;

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Helper\Acl;
use Cnr\Component\Protis\Administrator\Service\Services;
use Cnr\Component\Protis\Site\Controller\PwaController;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

/**
 * Vista base: shell dell'app (navigazione, asset, PWA).
 */
abstract class BaseHtmlView extends HtmlView
{
    public ?object $me = null;
    public bool $canManage = false;
    public bool $isAdmin = false;
    public Registry $cparams;
    public string $active = '';
    public string $pageTitle = '';

    public function display($tpl = null)
    {
        $this->me        = Services::people()->forUser(Acl::user());
        $this->canManage = Acl::canManage();
        $this->isAdmin   = Acl::isAdmin();
        $this->cparams   = ComponentHelper::getParams('com_protis');

        // template "app" a schermo intero (installato con il pacchetto)
        if ($this->cparams->get('app_template', 1) && is_dir(JPATH_THEMES . '/protis')) {
            Factory::getApplication()->setTemplate('protis');
        }

        $this->prepare();
        $this->setupDocument();

        parent::display($tpl);
    }

    /** Caricamento dati specifici della vista. */
    abstract protected function prepare(): void;

    private function setupDocument(): void
    {
        $doc = $this->getDocument();
        $wa  = $doc->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_protis');
        $wa->useStyle('com_protis.app')->useScript('com_protis.app');

        $theme  = (string) $this->cparams->get('theme_color', '#0b4f8a');
        $accent = (string) $this->cparams->get('accent_color', '#ffc300');
        $wa->addInlineStyle(':root{--pt-primary:' . $theme . ';--pt-accent:' . $accent . ';}');

        $app  = $this->cparams->get('app_name', 'PROTIS IBF');
        $doc->setTitle(trim(($this->pageTitle ? $this->pageTitle . ' · ' : '') . $app));

        $doc->addScriptOptions('com_protis', [
            'token'   => Session::getFormToken(),
            'base'    => Uri::base(true) . '/index.php?option=com_protis',
            'root'    => Uri::root(true) . '/',
            'pwa'     => (bool) $this->cparams->get('pwa', 1),
            'sw'      => Route::_('index.php?option=com_protis&task=pwa.sw', false),
            'version' => PwaController::VERSION,
        ]);

        if ($this->cparams->get('pwa', 1)) {
            $icons = Uri::root(true) . '/media/com_protis/images/icons/';
            $doc->addHeadLink(Route::_('index.php?option=com_protis&task=pwa.manifest', false), 'manifest');
            $doc->addHeadLink($icons . 'apple-touch-icon.png', 'apple-touch-icon');
            $doc->setMetaData('theme-color', $theme);
            $doc->setMetaData('mobile-web-app-capable', 'yes');
            $doc->setMetaData('apple-mobile-web-app-capable', 'yes');
            $doc->setMetaData('apple-mobile-web-app-status-bar-style', 'default');
            $doc->setMetaData('apple-mobile-web-app-title', (string) $app);
        }
    }

    public function link(string $query): string
    {
        return Route::_('index.php?option=com_protis&' . $query);
    }

    /** Foto piccola tonda (o iniziali) di una persona, per gli elenchi. */
    public function miniAvatar(object $person): string
    {
        $url = \Cnr\Component\Protis\Administrator\Service\PeopleService::avatarUrl($person);
        $ini = mb_substr((string) ($person->nome ?? ''), 0, 1) . mb_substr((string) ($person->cognome ?? ''), 0, 1);

        return $url !== ''
            ? '<span class="pt-mini-avatar"><img src="' . htmlspecialchars($url) . '" alt="" loading="lazy"></span>'
            : '<span class="pt-mini-avatar is-empty" aria-hidden="true">' . htmlspecialchars($ini !== '' ? $ini : '?') . '</span>';
    }

    /** Scritte accanto al logo: [riga 1, riga 2]. */
    public function brandLines(): array
    {
        return [
            (string) $this->cparams->get('brand_ente', 'Consiglio Nazionale delle Ricerche'),
            (string) $this->cparams->get('brand_istituto', ''),
        ];
    }

    /** Logo dell'applicazione (Opzioni → Logo), con l'icona dell'app come riserva. */
    public function appLogo(): string
    {
        $path = trim((string) $this->cparams->get('app_logo', ''), '/ ');
        $path = preg_replace('/#.*$/', '', $path); // valori del campo media di Joomla

        if ($path !== '' && !str_contains($path, '..') && is_file(JPATH_ROOT . '/' . $path)) {
            return Uri::root(true) . '/' . implode('/', array_map('rawurlencode', explode('/', $path)));
        }

        // logo PROTIS IBF incluso (sfondo trasparente, si adatta al colore dell'intestazione)
        return Uri::root(true) . '/media/com_protis/images/logo-protisibf.png?' . PwaController::VERSION;
    }

    public function token(): string
    {
        return Session::getFormToken();
    }

    /** Percorso dei partial condivisi */
    public function partial(string $name, array $vars = []): void
    {
        extract($vars, EXTR_SKIP);
        require JPATH_SITE . '/components/com_protis/tmpl/_shared/' . $name . '.php';
    }

    public function messages(): array
    {
        return Factory::getApplication()->getMessageQueue(true);
    }
}
