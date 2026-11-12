<?php

/**
 * @var \Cnr\Component\Protis\Site\View\Project\HtmlView $this
 */

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Helper\Time;
use Joomla\CMS\Uri\Uri;

$this->partial('header');
$p   = $this->item;
$v   = static fn (string $k) => htmlspecialchars((string) ($p->$k ?? ''));
$wps = $p->wps ?? [];

if (!$wps) {
    $wps = [(object) ['id' => 0, 'label' => '', 'published' => 1, 'code' => '']];
}
?>
<div class="pt-page-head">
    <h1><?= $p ? htmlspecialchars($p->acronimo) : 'Nuovo progetto' ?></h1>
    <a class="pt-btn pt-btn-ghost" href="<?= $this->link('view=projects') ?>">← Torna all'elenco dei progetti</a>
</div>

<form class="pt-form" method="post" enctype="multipart/form-data" action="<?= $this->link('task=manage.saveProject') ?>">
    <input type="hidden" name="id" value="<?= (int) ($p->id ?? 0) ?>">
    <input type="hidden" name="<?= $this->token() ?>" value="1">

    <section class="pt-card pt-form pt-project-data">
        <?php $on = !$p || $p->published; ?>
        <div class="pt-project-data-head">
            <h2 class="pt-card-title">Dati del progetto</h2>
            <label class="pt-switch <?= $on ? 'is-on' : '' ?>" data-switch <?= $p ? 'data-autosave="api.toggleProject" data-id="' . (int) $p->id . '"' : '' ?> title="Un progetto disattivato non compare nei calendari ed è archiviato nella dashboard">
                <input type="hidden" name="jform[published]" value="0">
                <input type="checkbox" name="jform[published]" value="1" <?= $on ? 'checked' : '' ?>>
                <span class="pt-switch-track" aria-hidden="true"><span class="pt-switch-thumb"></span></span>
                <span class="pt-switch-label" data-on="Attivo" data-off="Disattivato (archiviato)"><?= $on ? 'Attivo' : 'Disattivato (archiviato)' ?></span>
            </label>
        </div>
        <div class="pt-project-grid">
            <label>Acronimo * <input name="jform[acronimo]" required maxlength="255" value="<?= $v('acronimo') ?>"></label>
            <label>CUP <input name="jform[cup]" maxlength="100" value="<?= $v('cup') ?>" placeholder="es. B53C20040660001"></label>
            <label>Grant Agreement <input name="jform[grant_agreement]" maxlength="100" value="<?= $v('grant_agreement') ?>" placeholder="es. 101061694"></label>
            <label>Codice del progetto <input name="jform[codice_progetto]" value="<?= $v('codice_progetto') ?>"></label>
            <label class="pt-span-2">Titolo <textarea name="jform[titolo]" rows="2"><?= $v('titolo') ?></textarea></label>
            <label class="pt-span-2">Avviso / Call <textarea name="jform[codice_avviso]" rows="2"><?= $v('codice_avviso') ?></textarea></label>
            <label class="pt-span-2">Ente / Beneficiario <input name="jform[ente]" value="<?= $v('ente') ?: htmlspecialchars((string) $this->cparams->get('istituto', '')) ?>"></label>
            <?php $altro = !empty($p->sede_altro) && empty($p->site_id); ?>
            <label>Sede proponente
                <select name="jform[site_id]" data-sede>
                    <option value="">Tutte le sedi</option>
                    <?php foreach ($this->sites as $s) : ?>
                        <option value="<?= (int) $s->id ?>" <?= !$altro && (int) ($p->site_id ?? 0) === (int) $s->id ? 'selected' : '' ?>><?= htmlspecialchars($s->sede) ?></option>
                    <?php endforeach; ?>
                    <option value="altro" <?= $altro ? 'selected' : '' ?>>Altra sede (scrivi)…</option>
                </select>
            </label>
            <label data-sede-altro <?= $altro ? '' : 'hidden' ?>>Altra sede proponente <input name="jform[sede_altro]" value="<?= $v('sede_altro') ?>" maxlength="255" placeholder="es. CNR - IBF Napoli"></label>
            <span class="pt-grid-break" aria-hidden="true"></span>
            <label>Inizio <input type="date" name="jform[data_inizio]" value="<?= $v('data_inizio') ?>"></label>
            <label>Fine <input type="date" name="jform[data_fine]" value="<?= $v('data_fine') ?>"></label>
            <label>Proroga <input type="date" name="jform[data_proroga]" value="<?= $v('data_proroga') ?>"></label>
        </div>
    </section>

    <section class="pt-card pt-form">
        <h2 class="pt-card-title">Work package</h2>
        <div class="pt-wp-editor" id="pt-wp-editor">
            <?php foreach ($wps as $i => $w) : ?>
                <div class="pt-wp-edit-row" data-wp-row data-has-id="<?= (int) $w->id ? 1 : 0 ?>">
                    <span class="pt-handle">WP<?= $i + 1 ?></span>
                    <input type="hidden" name="wps[<?= $i ?>][id]" value="<?= (int) $w->id ?>">
                    <input class="pt-input" name="wps[<?= $i ?>][label]" value="<?= htmlspecialchars($w->label) ?>" placeholder="Descrizione WP (compare nel timesheet)" aria-label="Descrizione WP <?= $i + 1 ?>">
                    <label class="pt-check" title="Attivo"><input type="hidden" name="wps[<?= $i ?>][published]" value="0"><input type="checkbox" name="wps[<?= $i ?>][published]" value="1" <?= $w->published ? 'checked' : '' ?>> attivo</label>
                    <?php if ($i > 0) : ?><button type="button" class="pt-btn pt-btn-sm pt-btn-ghost pt-danger" data-del-wp aria-label="Rimuovi WP">Rimuovi</button><?php else : ?><span class="pt-wp-fixed"></span><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="pt-btn pt-btn-sm" data-add-wp>+ Aggiungi WP</button>
        <p class="pt-help">Il primo WP non si rimuove. Un WP rimosso che ha già ore registrate non viene cancellato ma solo disattivato (le ore restano).</p>
    </section>

    <?php $this->partial('header_editor', ['p' => $p]); ?>

    <div class="pt-form-actions">
        <button type="submit" class="pt-btn pt-btn-primary pt-btn-lg">Salva progetto</button>
    </div>
</form>

<?php if ($p) : ?>
    <section class="pt-card">
        <h2 class="pt-card-title">Timesheet di tutte le persone del progetto</h2>
        <form class="pt-form" method="get" action="<?= Uri::base(true) ?>/index.php" target="_blank">
            <input type="hidden" name="option" value="com_protis"><input type="hidden" name="task" value="export.project">
            <input type="hidden" name="project" value="<?= (int) $p->id ?>"><input type="hidden" name="<?= $this->token() ?>" value="1">
            <input type="hidden" name="return" value="<?= base64_encode(Uri::getInstance()->toString()) ?>">
            <div class="pt-row">
                <?php $this->partial('monthpicker', ['field' => 'from', 'label' => 'Dal mese', 'value' => date('Y-m', strtotime('first day of last month'))]); ?>
                <?php $this->partial('monthpicker', ['field' => 'to', 'label' => 'Al mese', 'value' => date('Y-m', strtotime('first day of last month'))]); ?>
                <label>Formato <select name="format_out"><option value="pdf">PDF</option><option value="xlsx">Excel</option><option value="ods">ODS</option><option value="csv">CSV</option></select></label>
            </div>
            <div class="pt-form-actions"><button class="pt-btn pt-btn-accent">Genera</button></div>
        </form>
    </section>

    <?php if (\Cnr\Component\Protis\Administrator\Helper\Acl::isSuperUser()) : ?>
    <form method="post" action="<?= $this->link('task=manage.deleteProject') ?>" data-confirm="Eliminare il progetto? Se ci sono ore registrate verrà solo archiviato." class="pt-form-actions">
        <input type="hidden" name="id" value="<?= (int) $p->id ?>"><input type="hidden" name="<?= $this->token() ?>" value="1">
        <button class="pt-btn pt-btn-ghost pt-danger">Elimina / archivia progetto</button>
    </form>
    <?php endif; ?>
<?php endif; ?>

<script>
(() => {
  const sede = document.querySelector('[data-sede]');
  const altro = document.querySelector('[data-sede-altro]');
  sede?.addEventListener('change', () => { altro.hidden = sede.value !== 'altro'; if (!altro.hidden) altro.querySelector('input').focus(); });
})();
(() => {
  const box = document.getElementById('pt-wp-editor');
  const renumber = () => box.querySelectorAll('[data-wp-row]').forEach((r, i) => { r.querySelector('.pt-handle').textContent = `WP${i + 1}`; });
  let n = box.children.length;
  document.querySelector('[data-add-wp]').addEventListener('click', () => {
    const i = n++;
    box.insertAdjacentHTML('beforeend', `<div class="pt-wp-edit-row" data-wp-row data-has-id="0"><span class="pt-handle"></span>
      <input type="hidden" name="wps[${i}][id]" value="0">
      <input class="pt-input" name="wps[${i}][label]" placeholder="Descrizione WP (compare nel timesheet)">
      <label class="pt-check"><input type="hidden" name="wps[${i}][published]" value="0"><input type="checkbox" name="wps[${i}][published]" value="1" checked> attivo</label>
      <button type="button" class="pt-btn pt-btn-sm pt-btn-ghost pt-danger" data-del-wp aria-label="Rimuovi WP">Rimuovi</button></div>`);
    renumber();
    box.lastElementChild.querySelector('.pt-input').focus();
  });
  box.addEventListener('click', (e) => {
    const b = e.target.closest('[data-del-wp]');
    if (!b) return;
    const row = b.closest('[data-wp-row]');
    const name = row.querySelector('.pt-input').value || row.querySelector('.pt-handle').textContent;
    if (row.dataset.hasId === '1' && !confirm(`Rimuovere il work package "${name}"? Se ha ore registrate verrà solo disattivato. La modifica vale dopo "Salva progetto".`)) return;
    row.remove();
    renumber();
  });
})();
</script>
<div class="pt-form-actions" style="justify-content:flex-start;margin-top:1rem">
    <a class="pt-btn pt-btn-ghost" href="<?= $this->link('view=projects') ?>">← Torna all'elenco dei progetti</a>
</div>
<?php $this->partial('footer'); ?>
