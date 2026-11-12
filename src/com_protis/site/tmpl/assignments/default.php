<?php

/**
 * @var \Cnr\Component\Protis\Site\View\Assignments\HtmlView $this
 */

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Helper\Time;

$this->partial('header');
$extra = $this->projectFilter ? '&project=' . $this->projectFilter : '';
?>
<div class="pt-page-head">
    <h1>Associazioni persone ↔ progetti</h1>
</div>
<form method="get" class="pt-list-bar pt-list-bar-split" action="<?= $this->link('view=assignments') ?>">
    <input type="hidden" name="option" value="com_protis"><input type="hidden" name="view" value="assignments">
    <select name="project" class="pt-input" onchange="this.form.submit()" aria-label="Filtra per progetto">
        <option value="0">Tutti i progetti</option>
        <?php foreach ($this->projects as $pr) : ?>
            <option value="<?= (int) $pr->id ?>" <?= $this->projectFilter === (int) $pr->id ? 'selected' : '' ?>><?= htmlspecialchars($pr->acronimo) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="search" class="pt-input" placeholder="Cerca nominativo o progetto…" data-pt-filter="#pt-assign" aria-label="Cerca nominativo o progetto">
</form>

<details class="pt-card" <?= $this->items ? '' : 'open' ?>>
    <summary><strong>+ Nuova associazione</strong></summary>
    <form class="pt-form" method="post" action="<?= $this->link('task=manage.saveAssignment') ?>" style="margin-top:.75rem">
        <input type="hidden" name="<?= $this->token() ?>" value="1">
        <input type="hidden" name="back" value="assignments"><input type="hidden" name="back_extra" value="<?= htmlspecialchars($extra) ?>">
        <label>Progetto
            <select name="project_id" required data-assign-project>
                <option value="" disabled <?= $this->projectFilter ? '' : 'selected' ?>>Seleziona progetto…</option>
                <?php foreach ($this->projects as $pr) : ?>
                    <option value="<?= (int) $pr->id ?>" <?= $this->projectFilter === (int) $pr->id ? 'selected' : '' ?>><?= htmlspecialchars(trim($pr->acronimo . ' – ' . ($pr->cup ?: ($pr->grant_agreement ?? '')), ' –')) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php $this->partial('assignpicker', ['people' => $this->people, 'current' => $this->assignedMap[$this->projectFilter] ?? []]); ?>
        <div class="pt-form-actions"><button type="reset" class="pt-btn pt-btn-ghost">Azzera</button><button class="pt-btn pt-btn-primary">Salva associazioni</button></div>
    </form>
</details>
<script>window.PROTIS_ASSIGNED = <?= json_encode((object) array_map(static fn ($m) => (object) $m, $this->assignedMap)) ?>;</script>

<?php if (!$this->groups) : ?>
    <div class="pt-empty">Nessuna associazione.</div>
<?php else : ?>
    <p class="pt-muted" style="margin:.25rem 0 .5rem"><?= \count($this->groups) ?> persone<?= $this->projectFilter ? ' associate al progetto selezionato' : '' ?> · clicca su un nominativo per vedere tutti i suoi progetti.</p>
    <div class="pt-assign-list" id="pt-assign">
    <?php foreach ($this->groups as $g) : ?>
        <details class="pt-assign-person" data-row>
            <summary>
                <span class="pt-assign-who"><?= $this->miniAvatar($g['person']) ?><span class="pt-assign-name"><strong><?= htmlspecialchars($g['name']) ?></strong>
                    <small class="pt-muted"><?= htmlspecialchars(implode(' · ', array_filter([$g['sede'], $g['matricola'] ? 'matr. ' . $g['matricola'] : ($g['external'] ? 'altro istituto' : 'senza matricola')]))) ?></small></span></span>
                <span class="pt-assign-count"><?= \count($g['items']) ?> <?= \count($g['items']) === 1 ? 'progetto' : 'progetti' ?></span>
                <span class="pt-assign-chips"><?php foreach ($g['items'] as $a) : ?><span class="pt-chip <?= (int) $a->project_id === $this->projectFilter ? 'pt-chip-ok' : '' ?>"><?= htmlspecialchars($a->acronimo) ?></span><?php endforeach; ?></span>
            </summary>
            <div class="pt-table-wrap">
                <table class="pt-table pt-table-cards">
                    <thead><tr><th>Progetto</th><th>CUP / GA</th><th class="num">Ore previste da rendicontare</th><th class="num">Monte ore annuo</th><th class="num">Rendicontate</th><th class="num">Residuo</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($g['items'] as $a) :
                        $res = $a->monteore_min !== null ? (int) $a->monteore_min - $a->reported : null; ?>
                        <tr>
                            <td data-label="Progetto"><a href="<?= $this->link('view=project&id=' . (int) $a->project_id) ?>"><strong><?= htmlspecialchars($a->acronimo) ?></strong></a></td>
                            <td data-label="CUP"><?= htmlspecialchars((string) $a->cup) ?></td>
                            <td class="num" data-label="Ore previste"><?php $this->partial('hoursedit', ['a' => $a, 'back' => 'assignments', 'backExtra' => $extra, 'field' => 'monteore']); ?></td>
                            <td class="num" data-label="Monte ore annuo"><?php $this->partial('hoursedit', ['a' => $a, 'back' => 'assignments', 'backExtra' => $extra, 'field' => 'monte_annuo']); ?></td>
                            <td class="num" data-label="Rendicontate"><?= Time::format($a->reported) ?></td>
                            <td class="num" data-label="Residuo"><?php if ($res !== null) : ?><span class="pt-chip <?= $res < 0 ? 'pt-chip-danger' : 'pt-chip-ok' ?>"><?= Time::format($res) ?></span><?php else : ?>–<?php endif; ?></td>
                            <td class="actions">
                                <form method="post" action="<?= $this->link('task=manage.deleteAssignment') ?>" data-confirm="Rimuovere <?= htmlspecialchars($g['name']) ?> dal progetto <?= htmlspecialchars($a->acronimo) ?>? Le ore già registrate restano.">
                                    <input type="hidden" name="id" value="<?= (int) $a->id ?>">
                                    <input type="hidden" name="back" value="assignments"><input type="hidden" name="back_extra" value="<?= htmlspecialchars($extra) ?>">
                                    <input type="hidden" name="<?= $this->token() ?>" value="1">
                                    <button class="pt-btn pt-btn-sm pt-btn-ghost pt-danger">Rimuovi</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="pt-form-actions" style="margin-top:.5rem">
                <a class="pt-btn pt-btn-sm" href="<?= $this->link('view=person&id=' . (int) $g['id']) ?>">Scheda persona</a>
                <a class="pt-btn pt-btn-sm pt-btn-primary" href="<?= $this->link('view=calendar&person=' . (int) $g['id']) ?>">Calendario</a>
            </div>
        </details>
    <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php $this->partial('footer'); ?>
