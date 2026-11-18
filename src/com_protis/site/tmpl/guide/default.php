<?php

/**
 * Guida d'uso di PROTIS IBF (testo in site/guide/*.md).
 *
 * @var \Cnr\Component\Protis\Site\View\Guide\HtmlView $this
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;

$video = $this->presentationVideo();
$toc   = $video ? array_merge([['id' => 'video', 'title' => 'Video di presentazione']], $this->toc) : $this->toc;

if ($this->guest) : ?>
<div class="pt-app pt-guide-guest">
    <header class="pt-topbar">
        <a class="pt-brand" href="<?= Route::_('index.php?option=com_protis&view=login') ?>">
            <img class="pt-brand-logo" src="<?= $this->appLogo() ?>" alt="PROTIS IBF" height="36">
            <?php [$b1, $b2] = $this->brandLines(); ?>
            <span><strong><?= htmlspecialchars($b1) ?></strong><small><?= htmlspecialchars($b2) ?></small></span>
        </a>
        <a class="pt-btn pt-btn-ghost pt-btn-sm pt-guide-login" href="<?= Route::_('index.php?option=com_protis&view=login') ?>">Accedi</a>
    </header>
    <main class="pt-main">
<?php else :
    $this->partial('header');
endif; ?>

<div class="pt-page-head pt-guide-head">
    <h1>Guida di PROTIS IBF</h1>
    <button type="button" class="pt-btn pt-btn-ghost pt-btn-sm pt-guide-print" onclick="window.print()">Stampa o salva in PDF</button>
</div>

<label class="pt-guide-jump">
    <span>Vai alla sezione</span>
    <select class="pt-input" onchange="if (this.value) { location.hash = this.value; }">
        <option value="">Indice…</option>
        <?php foreach ($toc as $t) : ?><option value="<?= htmlspecialchars($t['id']) ?>"><?= htmlspecialchars($t['title']) ?></option><?php endforeach; ?>
    </select>
</label>

<div class="pt-guide">
    <nav class="pt-guide-toc pt-card" aria-label="Indice della guida">
        <strong>Indice</strong>
        <ol>
            <?php foreach ($toc as $t) : ?><li><a href="#<?= htmlspecialchars($t['id']) ?>"><?= htmlspecialchars($t['title']) ?></a></li><?php endforeach; ?>
        </ol>
        <?php if ($this->guest) : ?>
            <p class="pt-help">Dopo l'accesso, responsabili e amministratori trovano qui anche le loro sezioni.</p>
        <?php endif; ?>
    </nav>
    <article class="pt-guide-body pt-card">
        <?php if ($video) : ?>
            <section class="pt-guide-video">
                <h2 id="video">Video di presentazione</h2>
                <p>In un minuto: come si crea un progetto, come ci si associa, come arrivano le presenze e come si ottiene il timesheet.</p>
                <div class="pt-video-wrap">
                    <video controls playsinline controlslist="nodownload" disablepictureinpicture oncontextmenu="return false" preload="metadata"<?= $video['poster'] !== '' ? ' poster="' . htmlspecialchars($video['poster']) . '"' : '' ?>>
                        <source src="<?= htmlspecialchars($video['src']) ?>" type="video/mp4">
                            </video>
                </div>
            </section>
        <?php endif; ?>
        <?= $this->html ?>
    </article>
</div>

<script>
// evidenzia nell'indice la sezione visibile
(() => {
  const links = [...document.querySelectorAll('.pt-guide-toc a')];
  const map = new Map(links.map((a) => [a.getAttribute('href').slice(1), a]));
  if (!('IntersectionObserver' in window) || !map.size) return;
  const io = new IntersectionObserver((entries) => {
    entries.forEach((e) => {
      if (!e.isIntersecting) return;
      links.forEach((a) => a.classList.remove('is-current'));
      map.get(e.target.id)?.classList.add('is-current');
    });
  }, { rootMargin: '-20% 0px -70% 0px' });
  document.querySelectorAll('.pt-guide-body h2[id]').forEach((h) => io.observe(h));
})();
</script>

<?php if ($this->guest) : ?>
    </main>
    <?php $this->partial('footer_text'); ?>
</div>
<?php else :
    $this->partial('footer');
endif;
