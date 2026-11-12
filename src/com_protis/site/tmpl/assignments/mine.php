<?php

/**
 * Associazioni dell'utente collegato (non responsabile): si associa da solo ai progetti esistenti.
 *
 * @var \Cnr\Component\Protis\Site\View\Assignments\HtmlView $this
 */

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Helper\Time;

$this->partial('header');
$me    = $this->me;
$assoc = array_flip(array_map(static fn ($a) => (int) $a->project_id, $this->items));
?>
<div class="pt-page-head">
    <h1>Le mie associazioni</h1>
</div>

<?php if (!$me) : ?>
    <div class="pt-alert pt-alert-warning">Profilo PROTIS IBF non trovato: rivolgiti al responsabile.</div>
<?php else : ?>

<div class="pt-grid pt-grid-2 pt-my-assoc">
    <section class="pt-card">
        <h2 class="pt-card-title">Progetti a cui sei associato</h2>
        <?php if ($this->items) : ?>
            <div class="pt-assoc-head" aria-hidden="true"><span>Progetto</span><span>Ore previste<br>da rendicontare</span><span>Monte ore<br>annuo</span><span></span></div>
            <ul class="pt-kv" style="display:block;list-style:none;padding:0">
                <?php foreach ($this->items as $a) : $hasHours = (int) $a->reported > 0; ?>
                    <li class="pt-assoc-row">
                        <span class="pt-my-assoc-name">
                            <strong><?= htmlspecialchars($a->acronimo) ?></strong>
                            <?php if (empty($a->project_published)) : ?> <span class="pt-chip pt-chip-warn">archiviato</span><?php endif; ?>
                            <small class="pt-muted">Rendicontate: <?= Time::format((int) $a->reported) ?></small>
                        </span>
                        <span class="pt-assoc-hours" data-label="Ore previste"><?php $this->partial('hoursedit', ['a' => $a, 'back' => 'assignments', 'backExtra' => '', 'field' => 'monteore']); ?></span>
                        <span class="pt-assoc-hours" data-label="Monte ore annuo"><?php $this->partial('hoursedit', ['a' => $a, 'back' => 'assignments', 'backExtra' => '', 'field' => 'monte_annuo']); ?></span>
                        <?php if ($hasHours) : ?>
                            <span class="pt-assoc-del" title="Hai già rendicontato ore su questo progetto: per togliere l'associazione rivolgiti al responsabile"><button class="pt-btn pt-btn-sm pt-btn-ghost" disabled aria-label="Rimozione non possibile">×</button></span>
                        <?php else : ?>
                            <form class="pt-assoc-del" method="post" action="<?= $this->link('task=manage.deleteAssignment') ?>" data-confirm="Togliere l'associazione a <?= htmlspecialchars($a->acronimo) ?>?">
                                <input type="hidden" name="id" value="<?= (int) $a->id ?>"><input type="hidden" name="back" value="assignments">
                                <input type="hidden" name="<?= $this->token() ?>" value="1">
                                <button class="pt-btn pt-btn-sm pt-btn-ghost pt-danger" title="Togli l'associazione" aria-label="Togli l'associazione">×</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="pt-help">Le ore si correggono direttamente nei campi (compare "Salva"). Un'associazione si può togliere solo finché non hai rendicontato ore su quel progetto.</p>
        <?php else : ?>
            <p class="pt-muted">Non sei ancora associato a nessun progetto: aggiungilo qui accanto.</p>
        <?php endif; ?>
    </section>

    <section class="pt-card">
        <form class="pt-form pt-assign-new" method="post" action="<?= $this->link('task=manage.saveAssignment') ?>">
            <h2 class="pt-card-title">Associati a un progetto</h2>
            <input type="hidden" name="person_id[]" value="<?= (int) $me->id ?>"><input type="hidden" name="back" value="assignments">
            <input type="hidden" name="<?= $this->token() ?>" value="1">
            <label>Progetto
                <select name="project_id" required>
                    <option value="" disabled selected>Seleziona progetto…</option>
                    <?php foreach ($this->projects as $pr) : $isA = isset($assoc[(int) $pr->id]); ?>
                        <option value="<?= (int) $pr->id ?>" <?= $isA ? 'disabled' : '' ?>><?= htmlspecialchars($pr->acronimo) ?><?= $isA ? ' (già associato)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Ore previste sul progetto da rendicontare <input name="monteore" inputmode="decimal" placeholder="es. 300"></label>
            <label>Monte ore annuo <small>se previsto dal progetto</small><input name="monte_annuo" inputmode="decimal" placeholder="es. 1520"></label>
            <div class="pt-form-actions"><button class="pt-btn pt-btn-primary">Associa</button></div>
            <p class="pt-help">Dopo l'associazione il progetto compare nel tuo calendario e puoi rendicontare le ore e generare il timesheet.</p>
        </form>
    </section>
</div>
<?php endif; ?>
<?php $this->partial('footer'); ?>
