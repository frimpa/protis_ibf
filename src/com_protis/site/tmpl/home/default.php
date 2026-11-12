<?php

/**
 * @var \Cnr\Component\Protis\Site\View\Home\HtmlView $this
 */

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Helper\Time;

$this->partial('header');
$t     = $this->month;
$cap   = (int) ($t['capacity'] ?? 0);
$rep   = (int) ($t['reported'] ?? 0);
$pct   = $cap > 0 ? min(100, (int) round($rep * 100 / $cap)) : 0;
?>
<section class="pt-hero">
    <div>
        <h1>Ciao <?= htmlspecialchars(ucwords(mb_strtolower($this->me->nome ?? ''))) ?></h1>
        <p class="pt-muted"><?= $this->canManage ? 'Hai accesso alle funzioni di gestione dei progetti.' : 'Rendiconta le tue ore sui progetti e scarica i timesheet.' ?></p>
    </div>
</section>

<?php if ($this->missing) : ?>
    <div class="pt-alert pt-alert-warning">
        <strong>Profilo incompleto.</strong> Per generare i timesheet servono: <?= htmlspecialchars(implode(', ', $this->missing)) ?>.
        <a href="<?= $this->link('view=profile') ?>">Completa il profilo</a>
    </div>
<?php endif; ?>

<?php
$catLabels = ['tempo_lavorato' => 'Presenza', 'smartworking' => 'Lavoro Agile', 'telelavoro' => 'Telelavoro', 'missione' => 'Missione'];
$absLabels = ['malattia' => 'Malattia', 'ferie' => 'Ferie', 'permessi' => 'Permessi', 'altro' => 'Altro'];
$cat       = $t['categories'] ?? [];
$ord       = (int) ($t['ordinary'] ?? 0);
?>
<section class="pt-card pt-home-month">
    <?php
    $prevTs = mktime(0, 0, 0, $this->monthNum - 1, 1, $this->year);
    $nextTs = mktime(0, 0, 0, $this->monthNum + 1, 1, $this->year);
    $arch   = $this->showArchived ? '&archivati=1' : '';
    ?>
    <header class="pt-home-month-head">
        <div class="pt-home-monthnav">
            <a class="pt-btn pt-btn-icon pt-round" href="<?= $this->link('view=home&ym=' . date('Y-m', $prevTs) . $arch) ?>" aria-label="Mese precedente" title="Mese precedente">‹</a>
            <div>
                <span class="pt-cal-kicker"><?= htmlspecialchars($this->kicker) ?></span>
                <h2><?= htmlspecialchars($t['label'] ?? '') ?></h2>
            </div>
            <a class="pt-btn pt-btn-icon pt-round" href="<?= $this->link('view=home&ym=' . date('Y-m', $nextTs) . $arch) ?>" aria-label="Mese successivo" title="Mese successivo">›</a>
            <form method="get" action="<?= \Joomla\CMS\Uri\Uri::base(true) ?>/index.php" class="pt-home-jump" data-home-jump>
                <input type="hidden" name="option" value="com_protis"><input type="hidden" name="view" value="home">
                <?php if ($this->showArchived) : ?><input type="hidden" name="archivati" value="1"><?php endif; ?>
                <input type="hidden" name="ym" value="<?= \sprintf('%04d-%02d', $this->year, $this->monthNum) ?>">
                <select class="pt-input" data-m aria-label="Mese"><?php for ($i = 1; $i <= 12; $i++) : ?><option value="<?= $i ?>" <?= $i === $this->monthNum ? 'selected' : '' ?>><?= Time::monthName($i) ?></option><?php endfor; ?></select>
                <select class="pt-input" data-y aria-label="Anno"><?php for ($y = (int) date('Y') + 1; $y >= 2020; $y--) : ?><option value="<?= $y ?>" <?= $y === $this->year ? 'selected' : '' ?>><?= $y ?></option><?php endfor; ?></select>
                <?php $isToday = $this->year === (int) date('Y') && $this->monthNum === (int) date('n'); ?>
                <a class="pt-btn pt-btn-ghost pt-btn-sm pt-home-today <?= $isToday ? 'is-current' : '' ?>" href="<?= $this->link('view=home&ym=' . date('Y-m') . $arch) ?>" title="Vai al mese in corso">Oggi</a>
            </form>
        </div>
        <a class="pt-btn pt-btn-primary" href="<?= $this->link('view=calendar&year=' . $this->year . '&month=' . $this->monthNum) ?>">Apri il calendario</a>
    </header>

    <div class="pt-home-month-body">
        <div>
            <h3 class="pt-card-title">Riepilogo del mese</h3>
            <dl class="pt-kv pt-home-kv">
                <dt>Ore rendicontabili</dt><dd><?= Time::format($cap) ?></dd>
                <dt>Ore rendicontate su progetti</dt><dd><strong><?= Time::format($rep) ?></strong></dd>
                <dt>Attività Ordinaria residua</dt><dd><?= Time::format($ord) ?></dd>
                <dd class="pt-kv-full">
                    <div class="pt-progress" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><span style="width:<?= $pct ?>%"></span></div>
                    <small class="pt-muted"><?= $pct ?>% delle ore rendicontabili è attribuito ai progetti</small>
                </dd>
                <?php foreach ($catLabels as $k => $l) : if ((int) ($cat[$k] ?? 0) > 0) : ?>
                    <dt><?= $l ?></dt><dd><?= Time::format((int) $cat[$k]) ?></dd>
                <?php endif; endforeach; ?>
                <?php foreach ($absLabels as $k => $l) : if ((int) ($cat[$k] ?? 0) > 0) : ?>
                    <dt class="pt-muted"><?= $l ?></dt><dd class="pt-muted"><?= Time::format((int) $cat[$k]) ?></dd>
                <?php endif; endforeach; ?>
            </dl>
            <p class="pt-help" style="margin-top:.6rem">Ore rendicontabili = presenza + lavoro agile + telelavoro + missione. L'Attività Ordinaria è la parte non attribuita ai progetti.</p>
        </div>
        <div>
            <h3 class="pt-card-title">Ore per progetto del mese</h3>
            <?php if (empty($t['projects'])) : ?>
                <p class="pt-muted">Nessuna ora rendicontata nel mese.</p>
            <?php else : foreach ($t['projects'] as $tp) : ?>
                <details class="pt-proj-sum" open>
                    <summary><span><?= htmlspecialchars($tp['acronimo']) ?></span><strong><?= Time::format((int) $tp['total']) ?></strong></summary>
                    <ul><?php foreach ($tp['wps'] as $w) : ?><li><span><?= htmlspecialchars($w['label']) ?></span><span><?= Time::format((int) $w['total']) ?></span></li><?php endforeach; ?></ul>
                </details>
            <?php endforeach; ?>
                <div class="pt-proj-total"><span>Totale su tutti i progetti</span><strong><?= Time::format((int) array_sum(array_column($t['projects'], 'total'))) ?></strong></div>
            <?php endif; ?>
        </div>
    </div>
</section>

<div class="pt-section-head">
    <h2 class="pt-section-title">I miei progetti</h2>
    <?php if ($this->archivedCount) : ?>
        <a class="pt-switch pt-switch-view <?= $this->showArchived ? 'is-on' : '' ?>" href="<?= $this->link('view=home&ym=' . \sprintf('%04d-%02d', $this->year, $this->monthNum) . ($this->showArchived ? '' : '&archivati=1')) ?>" title="Mostra anche i progetti archiviati">
            <span class="pt-switch-track" aria-hidden="true"><span class="pt-switch-thumb"></span></span>
            <span class="pt-switch-label">Mostra anche gli archiviati <small>(<?= $this->archivedCount ?>)</small></span>
        </a>
    <?php endif; ?>
</div>
<?php if (!$this->projects) : ?>
    <div class="pt-empty"><?= $this->archivedCount ? 'Nessun progetto attivo.' : 'Nessun progetto associato. <a href="' . $this->link('view=assignments') . '">Associati ai progetti</a> su cui lavori.' ?></div>
<?php else : ?>
    <div class="pt-grid pt-grid-auto">
        <?php foreach ($this->projects as $p) :
            $done = 0;
            foreach ($t['projects'] ?? [] as $tp) {
                if ((int) $tp['id'] === (int) $p->id) {
                    $done = (int) $tp['total'];
                }
            } ?>
            <article class="pt-card pt-project">
                <header>
                    <strong><?= htmlspecialchars($p->acronimo) ?><?= (int) $p->published ? '' : ' <span class="pt-chip pt-chip-warn">archiviato</span>' ?></strong>
                    <span class="pt-chip"><?= htmlspecialchars($p->cup) ?></span>
                </header>
                <p class="pt-muted pt-clamp"><?= htmlspecialchars((string) $p->titolo) ?></p>
                <ul class="pt-wplist">
                    <?php foreach ($p->wps as $w) : ?><li><?= htmlspecialchars($w->label) ?></li><?php endforeach; ?>
                </ul>
                <footer>
                    <span><?= htmlspecialchars($t['label'] ?? '') ?>: <strong><?= Time::format($done) ?></strong></span>
                    <?php if ($p->monteore_min) : ?><span class="pt-muted">Ore previste: <?= Time::format((int) $p->monteore_min) ?></span><?php endif; ?>

                </footer>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
// salto diretto a mese/anno
document.querySelector('[data-home-jump]')?.addEventListener('change', (e) => {
  const f = e.currentTarget;
  f.ym.value = `${f.querySelector('[data-y]').value}-${String(f.querySelector('[data-m]').value).padStart(2, '0')}`;
  f.submit();
});
</script>
<?php $this->partial('footer'); ?>
