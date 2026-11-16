<?php

/**
 * Shell dell'app: barra superiore + navigazione (desktop: schede; mobile: barra in basso).
 *
 * @var \Cnr\Component\Protis\Site\View\BaseHtmlView $this
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

$items = [
    ['home', 'Home', 'index.php?option=com_protis&view=home', 'home', true],
    ['calendar', 'Calendario', 'index.php?option=com_protis&view=calendar', 'calendar', true],
];

if ($this->canManage) {
    $items[] = ['report', 'Dashboard', 'index.php?option=com_protis&view=report', 'chart', true];
    $items[] = ['projects', 'Progetti', 'index.php?option=com_protis&view=projects', 'folder', false];
    $items[] = ['assignments', 'Associazioni', 'index.php?option=com_protis&view=assignments', 'link', false];
    $items[] = ['people', 'Persone', 'index.php?option=com_protis&view=people', 'users', false];
    $items[] = ['codes', 'Codici assenza', 'index.php?option=com_protis&view=codes', 'tag', false];
} else {
    // gli utenti si associano da soli ai progetti
    $items[] = ['assignments', 'Associazioni', 'index.php?option=com_protis&view=assignments', 'link', true];
}

if ($this->isAdmin) {
    $items[] = ['settings', 'Impostazioni', 'index.php?option=com_protis&view=settings', 'gear', false];
}

if (\Cnr\Component\Protis\Site\Controller\ToolsController::scrivaniaFile() !== null) {
    $items[] = ['scrivania', 'Scrivania PDF', 'index.php?option=com_protis&view=scrivania', 'pdf', false];
}

$items[] = ['profile', 'Profilo', 'index.php?option=com_protis&view=profile', 'user', true];
$items[] = ['guide', 'Guida', 'index.php?option=com_protis&view=guide', 'help', false];

// "?" in alto: apre la Guida sulla sezione della pagina corrente
$helpAnchor = [
    'home' => 'home', 'calendar' => 'calendario', 'profile' => 'profilo',
    'assignments' => $this->canManage ? 'associazioni-responsabili' : 'associazioni',
    'projects' => 'progetti', 'project' => 'progetti', 'people' => 'persone', 'person' => 'persone',
    'report' => 'dashboard', 'codes' => 'codici', 'settings' => 'impostazioni', 'scrivania' => 'scrivania',
][$this->active] ?? '';

$icon = static function (string $name): string {
    $p = [
        'home'     => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'chart'    => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'folder'   => '<path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'link'     => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
        'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6 6 0 0 1 3.5 6"/>',
        'tag'      => '<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="8.5" r="1.5"/>',
        'gear'     => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'list'     => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'help'     => '<circle cx="12" cy="12" r="9.5"/><path d="M9.3 9.2a2.8 2.8 0 0 1 5.4 1c0 1.9-2.7 2.4-2.7 4"/><path d="M12 17.3h.01"/>',
        'pdf'      => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 14l2 2 4-4"/>',
        'more'     => '<circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/>',
    ][$name] ?? '';

    return '<svg class="pt-ico" viewBox="0 0 24 24" aria-hidden="true">' . $p . '</svg>';
};

$name  = $this->me ? trim(ucwords(mb_strtolower($this->me->nome . ' ' . $this->me->cognome))) : '';
$sede  = $this->me->sede_nome ?? '';
$role  = $this->isAdmin ? 'Amministratore' : ($this->canManage ? 'Responsabile' : 'Utente');
$appNm = htmlspecialchars((string) $this->cparams->get('app_name', 'PROTIS IBF'));
$logoutReturn = base64_encode(Route::_('index.php?option=com_protis&view=home', false, 0, true));
?>
<div class="pt-app" data-active="<?= htmlspecialchars($this->active) ?>">
    <header class="pt-topbar">
        <a class="pt-brand" href="<?= Route::_('index.php?option=com_protis&view=home') ?>">
            <img class="pt-brand-logo" src="<?= $this->appLogo() ?>" alt="<?= $appNm ?>" height="36">
            <?php [$b1, $b2] = $this->brandLines(); ?>
            <span><strong><?= htmlspecialchars($b1) ?></strong><small><?= htmlspecialchars($b2) ?></small></span>
        </a>
        <div class="pt-userchip">
            <?php $av = \Cnr\Component\Protis\Administrator\Service\PeopleService::avatarUrl($this->me); ?>
            <a class="pt-avatar<?= $av ? ' has-photo' : '' ?>" href="<?= Route::_('index.php?option=com_protis&view=profile') ?>" title="Il mio profilo"><?php if ($av) : ?><img src="<?= $av ?>" alt=""><?php else : ?><span aria-hidden="true"><?= htmlspecialchars(mb_substr($this->me->nome ?? '?', 0, 1) . mb_substr($this->me->cognome ?? '', 0, 1)) ?></span><?php endif; ?></a>
            <span class="pt-userchip-text"><strong><?= htmlspecialchars($name) ?></strong><small><?= htmlspecialchars($role . ($sede ? ' · ' . $sede : '')) ?></small></span>
            <a class="pt-help-btn" href="<?= Route::_('index.php?option=com_protis&view=guide') . ($helpAnchor ? '#' . $helpAnchor : '') ?>" title="Guida<?= $helpAnchor ? ' di questa pagina' : '' ?>" aria-label="Guida"><?= $icon('help') ?></a>
            <form action="<?= Route::_('index.php?option=com_users&task=user.logout') ?>" method="post" class="pt-logout">
                <input type="hidden" name="return" value="<?= $logoutReturn ?>">
                <input type="hidden" name="<?= Session::getFormToken() ?>" value="1">
                <button type="submit" class="pt-btn pt-btn-ghost pt-btn-sm" title="Esci">Esci</button>
            </form>
        </div>
    </header>

    <nav class="pt-tabs" aria-label="Navigazione PROTIS IBF">
        <?php foreach ($items as [$key, $label, $url, $ico]) : ?>
            <a href="<?= Route::_($url) ?>" class="<?= $this->active === $key ? 'is-active' : '' ?>" <?= $this->active === $key ? 'aria-current="page"' : '' ?>><?= $icon($ico) ?><span><?= $label ?></span></a>
        <?php endforeach; ?>
    </nav>

    <nav class="pt-bottombar" aria-label="Navigazione rapida">
        <?php
        $primary = array_values(array_filter($items, static fn ($i) => $i[4]));
        $others  = array_values(array_filter($items, static fn ($i) => !$i[4]));
        foreach ($primary as [$key, $label, $url, $ico]) : ?>
            <a href="<?= Route::_($url) ?>" class="<?= $this->active === $key ? 'is-active' : '' ?>"><?= $icon($ico) ?><span><?= $label ?></span></a>
        <?php endforeach; ?>
        <?php if ($others) : ?>
            <button type="button" class="<?= \in_array($this->active, array_column($others, 0), true) ? 'is-active' : '' ?>" data-pt-open="pt-more-sheet"><?= $icon('more') ?><span>Altro</span></button>
        <?php endif; ?>
    </nav>

    <?php if ($others) : ?>
        <dialog id="pt-more-sheet" class="pt-sheet">
            <div class="pt-sheet-head"><strong>Altre funzioni</strong><button type="button" class="pt-btn pt-btn-ghost pt-btn-sm" data-pt-close>Chiudi</button></div>
            <div class="pt-sheet-grid">
                <?php foreach ($others as [$key, $label, $url, $ico]) : ?>
                    <a href="<?= Route::_($url) ?>" class="<?= $this->active === $key ? 'is-active' : '' ?>"><?= $icon($ico) ?><span><?= $label ?></span></a>
                <?php endforeach; ?>
            </div>
        </dialog>
    <?php endif; ?>

    <main class="pt-main">
    <?php foreach ($this->messages() as $msg) :
        $t = ['error' => 'danger', 'danger' => 'danger', 'warning' => 'warning', 'notice' => 'warning'][$msg['type'] ?? ''] ?? 'ok'; ?>
        <div class="pt-alert pt-alert-<?= $t ?> pt-flash" role="<?= $t === 'danger' ? 'alert' : 'status' ?>"><?= htmlspecialchars(strip_tags((string) $msg['message'])) ?></div>
    <?php endforeach; ?>
