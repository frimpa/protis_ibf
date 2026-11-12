<?php

/**
 * @var \Cnr\Component\Protis\Site\View\Report\HtmlView $this
 */

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Helper\Time;
use Joomla\CMS\Uri\Uri;

$this->partial('header');
$fmt = static fn (?int $m) => $m === null ? 'n/d' : Time::format($m);
?>
<div class="pt-page-head pt-report-head">
    <h1>Dashboard rendicontazione</h1>
    <label class="pt-switch pt-switch-view <?= $this->showArchived ? 'is-on' : '' ?>" data-switch data-submit title="Passa dai progetti attivi a quelli archiviati (disattivati)">
        <input type="checkbox" form="pt-report-filters" name="archived" value="1" <?= $this->showArchived ? 'checked' : '' ?>>
        <span class="pt-switch-label pt-switch-off-label">Progetti attivi</span>
        <span class="pt-switch-track" aria-hidden="true"><span class="pt-switch-thumb"></span></span>
        <span class="pt-switch-label">Progetti archiviati</span>
    </label>
</div>

<form class="pt-card pt-report-filters" id="pt-report-filters" method="get" action="<?= Uri::base(true) ?>/index.php" data-autosubmit>
    <input type="hidden" name="option" value="com_protis"><input type="hidden" name="view" value="report">
    <label class="pt-filter pt-filter-project"><span>Progetto</span>
        <select name="project" class="pt-input">
            <option value="0">Tutti i progetti</option>
            <?php foreach ($this->projects as $p) : ?><option value="<?= (int) $p->id ?>" <?= $this->projectFilter === (int) $p->id ? 'selected' : '' ?>><?= htmlspecialchars($p->acronimo) ?><?= $p->published ? '' : ' (archiviato)' ?></option><?php endforeach; ?>
        </select>
    </label>
    <label class="pt-filter"><span>Ore dal</span><input type="date" name="from" class="pt-input" value="<?= htmlspecialchars($this->from) ?>"></label>
    <label class="pt-filter"><span>al</span><input type="date" name="to" class="pt-input" value="<?= htmlspecialchars($this->to) ?>"></label>
    <?php if ($this->projectFilter || $this->from || $this->to || $this->showArchived) : ?>
        <a class="pt-btn pt-btn-ghost pt-btn-sm" href="<?= $this->link('view=report') ?>">Azzera filtri</a>
    <?php endif; ?>
    <noscript><button class="pt-btn pt-btn-primary">Applica</button></noscript>
    <p class="pt-help pt-filter-help">I risultati si aggiornano appena cambi un filtro. Senza date vengono conteggiate tutte le ore dall'inizio del progetto a oggi.</p>
</form>

<?php if (!$this->items) : ?>
    <div class="pt-empty">Nessun dato per i filtri selezionati.</div>
<?php endif; ?>

<div class="pt-report-grid">
<?php foreach ($this->items as $p) :
    $cls = $p['percent'] === null ? '' : ($p['percent'] > 100 ? 'is-over' : ($p['percent'] >= 80 ? 'is-warn' : '')); ?>
    <article class="pt-card pt-report-card" data-report-card title="Clicca per vedere il dettaglio per persona">
        <header>
            <div>
                <h2 style="font-size:1.15rem"><?= htmlspecialchars($p['acronimo']) ?> <?= $p['published'] ? '' : '<span class="pt-chip pt-chip-warn">archiviato</span>' ?></h2>
                <div class="pt-report-meta">
                    <?php if ($p['cup'] !== '') : ?><span><?= htmlspecialchars($p['cup']) ?></span><?php endif; ?>
                    <?php if ($p['start']) : ?><span><?= date('d/m/Y', strtotime($p['start'])) ?> → <?= $p['end'] ? date('d/m/Y', strtotime($p['end'])) : '…' ?></span><?php endif; ?>
                    <span><?= \count($p['people']) ?> persone</span>
                </div>
            </div>
            <?php if ($p['logo']) : ?><img src="<?= Uri::root(true) . '/' . $p['logo'] ?>" alt=""><?php endif; ?>
        </header>

        <div class="pt-grid pt-grid-3">
            <div class="pt-stat"><span class="pt-stat-label">Rendicontate</span><strong class="pt-stat-value"><?= Time::format($p['total']) ?></strong></div>
            <div class="pt-stat"><span class="pt-stat-label">Ore previste</span><strong class="pt-stat-value"><?= $fmt($p['monteore']) ?></strong></div>
            <div class="pt-stat"><span class="pt-stat-label">Avanzamento</span><strong class="pt-stat-value"><?= $p['percent'] !== null ? $p['percent'] . '%' : 'n/d' ?></strong>
                <div class="pt-progress <?= $cls ?>"><span style="width:<?= min(100, (int) $p['percent']) ?>%"></span></div></div>
        </div>

        <?php if ($p['wps'] && \count($p['wps']) > 1) : ?>
            <p class="pt-report-meta"><?php foreach ($p['wps'] as $w) : ?><span class="pt-chip"><?= htmlspecialchars($w['label']) ?>: <?= Time::format($w['total']) ?></span><?php endforeach; ?></p>
        <?php endif; ?>

        <?php if ($p['people']) : ?>
            <details <?= $this->projectFilter ? 'open' : '' ?> class="pt-report-details">
                <summary style="cursor:pointer;margin:.5rem 0"><strong>Dettaglio per persona</strong></summary>
                <div class="pt-table-wrap">
                    <table class="pt-table pt-table-cards">
                        <thead><tr><th>Persona</th><th>Sede</th><th class="num">Rendicontate</th><th class="num">Ore previste</th><th class="num">Residuo</th><th>Avanzamento</th><th class="actions">Timesheet</th></tr></thead>
                        <tbody>
                        <?php foreach ($p['people'] as $u) :
                            $c = $u['percent'] === null ? '' : ($u['percent'] > 100 ? 'is-over' : ($u['percent'] >= 80 ? 'is-warn' : '')); ?>
                            <tr class="pt-row-link" data-href="<?= $this->link('view=calendar&person=' . (int) $u['person_id']) ?>" tabindex="0" title="Apri il calendario di <?= htmlspecialchars($u['name']) ?>">
                                <td data-label="Persona"><a href="<?= $this->link('view=calendar&person=' . (int) $u['person_id']) ?>"><?= htmlspecialchars($u['name']) ?></a><?= $u['assigned'] ? '' : ' <span class="pt-chip pt-chip-warn">non più associato</span>' ?>
                                    <?php if (\count($u['wps']) > 1) : ?><div class="pt-muted" style="font-size:.78rem"><?php foreach ($u['wps'] as $w) : ?><?= htmlspecialchars($w['label']) ?>: <?= Time::format($w['total']) ?>; <?php endforeach; ?></div><?php endif; ?>
                                </td>
                                <td data-label="Sede"><?= htmlspecialchars((string) $u['sede']) ?></td>
                                <td class="num" data-label="Rendicontate"><?= Time::format($u['total']) ?></td>
                                <td class="num" data-label="Ore previste"><?= $fmt($u['monteore']) ?></td>
                                <td class="num" data-label="Residuo"><?php if ($u['residuo'] !== null) : ?><span class="pt-chip <?= $u['residuo'] < 0 ? 'pt-chip-danger' : ($c === 'is-warn' ? 'pt-chip-warn' : 'pt-chip-ok') ?>"><?= Time::format($u['residuo']) ?></span><?php else : ?>n/d<?php endif; ?></td>
                                <td data-label="Avanzamento" style="min-width:120px"><?php if ($u['percent'] !== null) : ?><div class="pt-progress <?= $c ?>"><span style="width:<?= min(100, $u['percent']) ?>%"></span></div><small><?= $u['percent'] ?>%</small><?php endif; ?></td>
                                <td data-label="Timesheet" class="actions"><button type="button" class="pt-btn pt-btn-ghost pt-btn-sm" data-interval
                                    data-person="<?= (int) $u['person_id'] ?>" data-project="<?= (int) $p['id'] ?>"
                                    data-title="<?= htmlspecialchars($p['acronimo'] . ' — ' . $u['name']) ?>"
                                    data-start="<?= htmlspecialchars((string) $p['start']) ?>" data-end="<?= htmlspecialchars((string) $p['end']) ?>">Esporta per intervallo</button></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        <?php endif; ?>
    </article>
<?php endforeach; ?>
</div>
<?php
$defFrom = $this->from ? substr($this->from, 0, 7) : date('Y-m', strtotime('first day of last month'));
$defTo   = $this->to ? substr($this->to, 0, 7) : date('Y-m', strtotime('first day of last month'));
?>
<dialog id="pt-interval" class="pt-dialog">
    <form class="pt-form" method="get" action="<?= Uri::base(true) ?>/index.php" target="_blank">
        <input type="hidden" name="option" value="com_protis"><input type="hidden" name="task" value="export.interval">
        <input type="hidden" name="<?= $this->token() ?>" value="1">
        <input type="hidden" name="return" value="<?= base64_encode(Uri::getInstance()->toString()) ?>">
        <input type="hidden" name="person" value=""><input type="hidden" name="project" value="">
        <header class="pt-dialog-head">
            <div><h2>Esporta timesheet per intervallo</h2><p class="pt-muted" data-interval-title></p></div>
            <button type="button" class="pt-btn pt-btn-icon" data-pt-close aria-label="Chiudi">✕</button>
        </header>
        <div class="pt-dialog-body">
            <fieldset class="pt-radio">
                <label><input type="radio" name="periodo" value="progetto" checked> <span>Intera durata del progetto<br><small data-interval-dates></small></span></label>
                <label><input type="radio" name="periodo" value="scelto"> <span>Periodo scelto</span></label>
            </fieldset>
            <div class="pt-row" data-interval-range hidden>
                <?php $this->partial('monthpicker', ['field' => 'from', 'label' => 'Dal mese', 'value' => $defFrom]); ?>
                <?php $this->partial('monthpicker', ['field' => 'to', 'label' => 'Al mese', 'value' => $defTo]); ?>
            </div>
            <label>Formato <select name="format_out"><option value="pdf">PDF (A3 orizzontale)</option><option value="xlsx">Excel</option></select></label>
            <p class="pt-help">Una riga per mese con le ore giornaliere sul progetto (somma dei WP), colonne di costo da compilare e riquadro firme. I giorni fuori dal periodo sono in grigio.</p>
        </div>
        <footer class="pt-dialog-foot">
            <button type="button" class="pt-btn pt-btn-ghost" data-pt-close>Annulla</button>
            <button type="submit" class="pt-btn pt-btn-primary">Genera</button>
        </footer>
    </form>
</dialog>

<script>
(() => {
  // filtri: applicazione immediata senza il pulsante "Applica"
  const f = document.querySelector('form[data-autosubmit]');
  f?.addEventListener('change', (e) => {
    if (e.target.name === 'project') { const a = f.querySelector('[name=archived]'); if (a) a.disabled = false; }
    f.requestSubmit ? f.requestSubmit() : f.submit();
  });

  // clic su un punto qualsiasi della scheda progetto = apri/chiudi "Dettaglio per persona"
  document.addEventListener('click', (e) => {
    const card = e.target.closest('[data-report-card]');
    if (!card || e.target.closest('a, button, input, select, label, summary, .pt-report-details')) return;
    const d = card.querySelector('.pt-report-details');
    if (d) d.open = !d.open;
  });

  // timesheet per intervallo
  const dlg = document.getElementById('pt-interval');
  if (!dlg) return;
  const form = dlg.querySelector('form');
  const range = dlg.querySelector('[data-interval-range]');
  const it = (s) => s ? s.slice(0, 10).split('-').reverse().join('/') : 'n/d';
  const filterFrom = <?= json_encode($this->from ? substr($this->from, 0, 7) : '') ?>;
  const filterTo = <?= json_encode($this->to ? substr($this->to, 0, 7) : '') ?>;
  const lastMonth = <?= json_encode(date('Y-m', strtotime('first day of last month'))) ?>;
  const setYm = (name, ym) => { const fs = form.querySelector(`[name=${name}]`).closest('[data-pt-month]'); Protis.setMonth(fs, +ym.slice(0, 4), +ym.slice(5, 7)); };
  const sync = () => { range.hidden = form.periodo.value !== 'scelto'; };
  form.addEventListener('change', (e) => { if (e.target.name === 'periodo') sync(); });
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-interval]');
    if (!b) return;
    form.person.value = b.dataset.person;
    form.project.value = b.dataset.project;
    dlg.querySelector('[data-interval-title]').textContent = b.dataset.title;
    const whole = b.dataset.start && b.dataset.end;
    dlg.querySelector('[data-interval-dates]').textContent = whole ? `${it(b.dataset.start)} – ${it(b.dataset.end)}` : 'date del progetto non impostate: scegli un periodo';
    form.querySelector('[value=progetto]').disabled = !whole;
    form.periodo.value = whole ? 'progetto' : 'scelto';
    // periodo scelto: filtri della dashboard, altrimenti dall'inizio del progetto al mese scorso
    const ps = (b.dataset.start || '').slice(0, 7), pe = (b.dataset.end || '').slice(0, 7);
    let to = filterTo || (pe && pe < lastMonth ? pe : lastMonth);
    let from = filterFrom || ps || to;
    if (from > to) from = to;
    setYm('from', from); setYm('to', to);
    sync();
    dlg.showModal();
  });
  form.addEventListener('submit', () => setTimeout(() => dlg.close(), 50));
})();
</script>
<?php $this->partial('footer'); ?>
