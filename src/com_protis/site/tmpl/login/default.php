<?php

/**
 * Pagina di accesso PROTIS IBF.
 *
 * @var \Cnr\Component\Protis\Site\View\Login\HtmlView $this
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

$app  = htmlspecialchars((string) $this->cparams->get('app_name', 'PROTIS IBF'));
$ist  = htmlspecialchars((string) $this->cparams->get('istituto', ''));
$supN = trim((string) $this->cparams->get('support_name', ''));
$supE = trim((string) $this->cparams->get('support_email', ''));
$sedi = array_map(static fn ($s) => $s->sede, array_filter(\Cnr\Component\Protis\Administrator\Service\Services::people()->instituteSites(), static fn ($s) => $s->published));
$icon = $this->appLogo();
$video = $this->presentationVideo();
?>
<div class="pt-app pt-login">
    <div class="pt-login-shell">
        <section class="pt-login-intro" aria-hidden="false">
            <div class="pt-login-brand">
                <img class="pt-login-logo" src="<?= $icon ?>" alt="<?= $app ?>" height="56">
                <div>
                    <?php [$b1, $b2] = $this->brandLines(); ?>
                    <strong><?= htmlspecialchars($b1) ?></strong>
                    <span><?= htmlspecialchars($b2) ?></span>
                </div>
            </div>
            <h1>Rendicontazione delle ore <br>e timesheet di progetto</h1>
            <ul class="pt-login-points">
                <li>Presenze e assenze aggiornate da <strong>ePAS</strong></li>
                <li>Ore per progetto e work package in pochi tocchi</li>
                <li>Timesheet pronti per la firma, in PDF ed Excel</li>
                <li>Ogni timesheet riporta anche le ore sugli altri progetti</li>
                <li>Aperto ai colleghi di altri istituti CNR che lavorano sui progetti condivisi</li>
            </ul>
            <?php if ($sedi) : ?><p class="pt-login-sedi">Sedi: <?= htmlspecialchars(implode(' · ', $sedi)) ?></p><?php endif; ?>
        </section>

        <section class="pt-login-panel">
            <?php foreach ($this->messages() as $msg) :
                $t = ['error' => 'danger', 'danger' => 'danger', 'warning' => 'warning', 'notice' => 'warning'][$msg['type'] ?? ''] ?? 'ok'; ?>
                <div class="pt-alert pt-alert-<?= $t ?> pt-login-msg" role="<?= $t === 'danger' ? 'alert' : 'status' ?>"><?= htmlspecialchars(strip_tags((string) $msg['message'])) ?></div>
            <?php endforeach; ?>
            <h2>Accedi</h2>
            <p class="pt-muted">Usa le credenziali istituzionali CNR (le stesse di SIPER).</p>
            <form class="pt-form" method="post" action="<?= Route::_('index.php?option=com_protis&task=auth.login') ?>">
                <label>Username CNR
                    <input name="username" autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false" required placeholder="nome.cognome" autofocus>
                </label>
                <label>Password
                    <span class="pt-pass">
                        <input type="password" name="password" autocomplete="current-password" required>
                        <button type="button" class="pt-pass-toggle" data-pass-toggle aria-label="Mostra la password" title="Mostra la password"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/><path class="pt-eye-off" d="M3 3l18 18"/></svg></button>
                    </span>
                </label>
                <label class="pt-check"><input type="checkbox" name="remember" value="yes"> Ricordami su questo dispositivo</label>
                <input type="hidden" name="return" value="<?= $this->return ?>">
                <input type="hidden" name="<?= $this->token() ?>" value="1">
                <button type="submit" class="pt-btn pt-btn-primary pt-btn-lg pt-btn-block">Accedi</button>
            </form>
            <?php if ($supE !== '') : ?>
                <p class="pt-help">Problemi di accesso? Scrivi a <a href="mailto:<?= htmlspecialchars($supE) ?>"><?= htmlspecialchars($supN !== '' ? $supN : $supE) ?></a>.</p>
            <?php endif; ?>
            <p class="pt-help pt-login-guide"><a href="<?= Route::_('index.php?option=com_protis&view=guide') ?>">Come funziona PROTIS IBF – Guida</a></p>
            <?php if ($video) : ?>
                <p class="pt-help pt-login-video"><button type="button" class="pt-video-open" data-video-open aria-haspopup="dialog"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="currentColor"/><path d="M10 8.2v7.6l6-3.8z" fill="#fff"/></svg> Guarda il video di presentazione <small>(1 minuto)</small></button></p>
            <?php endif; ?>
            <?php if (($priv = $this->privacyUrl()) !== '') : ?>
                <p class="pt-help pt-login-privacy">Accedendo dichiari di aver letto l'<a href="<?= htmlspecialchars($priv) ?>" target="_blank" rel="noopener">informativa sul trattamento dei dati personali</a>.</p>
            <?php endif; ?>
        </section>
    </div>
    <footer class="pt-login-foot"><?= $ist !== '' ? $app . ' · ' . $ist : '' ?>
        <span>Software PROTIS IBF © Ing. Francesco Impallari, CNR – Istituto di Biofisica · licenza EUPL-1.2</span></footer>
    <?php if ($video) : ?>
    <dialog id="pt-video" class="pt-dialog pt-dialog-wide pt-video-dialog" aria-labelledby="pt-video-title">
        <header class="pt-dialog-head">
            <h2 id="pt-video-title">PROTIS IBF in un minuto</h2>
            <button type="button" class="pt-btn pt-btn-icon" data-video-close aria-label="Chiudi">✕</button>
        </header>
        <div class="pt-video-wrap">
            <video controls playsinline controlslist="nodownload" disablepictureinpicture oncontextmenu="return false" preload="none"<?= $video['poster'] !== '' ? ' poster="' . htmlspecialchars($video['poster']) . '"' : '' ?>>
                <source src="<?= htmlspecialchars($video['src']) ?>" type="video/mp4">
            </video>
        </div>
    </dialog>
    <?php endif; ?>
    <div class="pt-toast" role="status" aria-live="polite" hidden></div>
</div>
<script>
// video di presentazione: si apre in una finestra e si ferma quando la finestra si chiude
(() => {
  const dlg = document.getElementById('pt-video');
  const open = document.querySelector('[data-video-open]');
  if (!dlg || !open) return;
  const video = dlg.querySelector('video');
  open.addEventListener('click', () => {
    if (typeof dlg.showModal === 'function') { dlg.showModal(); } else { dlg.setAttribute('open', ''); }
    video.play().catch(() => {});
  });
  dlg.querySelector('[data-video-close]').addEventListener('click', () => dlg.close());
  dlg.addEventListener('click', (e) => { if (e.target === dlg) dlg.close(); });
  dlg.addEventListener('close', () => video.pause());
})();
document.querySelector('[data-pass-toggle]')?.addEventListener('click', (e) => {
  const b = e.currentTarget;
  const i = b.parentElement.querySelector('input');
  const show = i.type === 'password';
  i.type = show ? 'text' : 'password';
  b.classList.toggle('is-on', show);
  b.setAttribute('aria-label', show ? 'Nascondi la password' : 'Mostra la password');
  b.title = b.getAttribute('aria-label');
  i.focus();
});
</script>
