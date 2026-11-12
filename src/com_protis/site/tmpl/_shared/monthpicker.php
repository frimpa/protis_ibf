<?php

/**
 * Selettore mese / anno (due menu a tendina) che compila un campo nascosto YYYY-MM.
 *
 * @var string $field  nome del campo (from|to)
 * @var string $label  etichetta
 * @var string $value  valore iniziale YYYY-MM
 * @var string $bind   (facoltativo) data-bind per il calendario
 */

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Helper\Time;

$y0 = (int) substr($value, 0, 4);
$m0 = (int) substr($value, 5, 2);
?>
<fieldset class="pt-monthpicker" data-pt-month <?= !empty($bind) ? 'data-bind="' . htmlspecialchars($bind) . '"' : '' ?>>
    <legend><?= htmlspecialchars($label) ?></legend>
    <select data-m aria-label="<?= htmlspecialchars($label) ?> – mese">
        <?php for ($m = 1; $m <= 12; $m++) : ?><option value="<?= $m ?>" <?= $m === $m0 ? 'selected' : '' ?>><?= Time::monthName($m) ?></option><?php endfor; ?>
    </select>
    <select data-y aria-label="<?= htmlspecialchars($label) ?> – anno">
        <?php for ($y = (int) date('Y') + 1; $y >= 2020; $y--) : ?><option value="<?= $y ?>" <?= $y === $y0 ? 'selected' : '' ?>><?= $y ?></option><?php endfor; ?>
    </select>
    <input type="hidden" name="<?= htmlspecialchars($field) ?>" value="<?= htmlspecialchars($value) ?>">
</fieldset>
