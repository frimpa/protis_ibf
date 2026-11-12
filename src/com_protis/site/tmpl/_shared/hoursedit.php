<?php

/**
 * Modifica in linea di un campo ore di un'associazione:
 *  - field = monteore     → Ore previste sul progetto da rendicontare
 *  - field = monte_annuo  → Monte ore annuo
 *
 * @var object $a         associazione (person_id, project_id, monteore_min)
 * @var string $back      vista di ritorno
 * @var string $backExtra parametri di ritorno (es. &id=5)
 */

\defined('_JEXEC') or die;

$field = ($field ?? 'monteore') === 'monte_annuo' ? 'monte_annuo' : 'monteore';
$min   = $field === 'monte_annuo' ? ($a->monte_annuo_min ?? null) : $a->monteore_min;
$val   = $min !== null ? \Cnr\Component\Protis\Administrator\Helper\Time::hoursNumber((int) $min) : '';
$lbl   = $field === 'monte_annuo' ? 'Monte ore annuo' : 'Ore previste sul progetto da rendicontare';
?>
<form class="pt-hours-edit" method="post" action="<?= $this->link('task=manage.saveAssignment') ?>">
    <input type="hidden" name="<?= $this->token() ?>" value="1">
    <input type="hidden" name="person_id[]" value="<?= (int) $a->person_id ?>"><input type="hidden" name="project_id" value="<?= (int) $a->project_id ?>">
    <input type="hidden" name="back" value="<?= htmlspecialchars($back) ?>"><input type="hidden" name="back_extra" value="<?= htmlspecialchars($backExtra) ?>">
    <input type="hidden" name="edit" value="1">
    <input class="pt-input" name="<?= $field ?>" value="<?= htmlspecialchars($val) ?>" inputmode="decimal" placeholder="—" aria-label="<?= $lbl ?>" title="<?= $lbl ?>" data-orig="<?= htmlspecialchars($val) ?>">
    <span class="pt-hours-unit"><?= $field === 'monte_annuo' ? 'anno' : 'ore' ?></span>
    <button class="pt-btn pt-btn-sm pt-btn-primary" hidden>Salva</button>
</form>
