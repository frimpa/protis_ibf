<?php

/**
 * Elenco persone con casella di selezione e monte ore individuale.
 *
 * @var object[] $people   persone selezionabili
 * @var array    $current  [person_id => monte ore in minuti|null] già associate (facoltativo)
 */

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Helper\Time;

$current ??= [];
?>
<div class="pt-picker" data-pt-picker>
    <div class="pt-picker-bar">
        <input type="search" class="pt-input" placeholder="Cerca persona o sede…" data-picker-search aria-label="Cerca persona">
        <span class="pt-muted" data-picker-count>0 selezionate</span>
    </div>
    <div class="pt-picker-head" aria-hidden="true"><span>Persona</span><span>Ore previste<br>da rendicontare</span><span>Monte ore<br>annuo</span></div>
    <ul class="pt-picker-list">
        <?php foreach ($people as $pp) :
            $id  = (int) $pp->id;
            $has = \array_key_exists($id, $current);
            $mo  = $has && $current[$id] !== null ? rtrim(rtrim(number_format($current[$id] / 60, 2, '.', ''), '0'), '.') : ''; ?>
            <li data-picker-row data-person="<?= $id ?>" data-text="<?= htmlspecialchars(mb_strtolower($pp->cognome . ' ' . $pp->nome . ' ' . ($pp->sede_nome ?? '') . (!empty($pp->external) ? ' altro istituto' : ''))) ?>">
                <label class="pt-picker-name">
                    <input type="checkbox" name="person_id[]" value="<?= $id ?>" <?= $has ? 'disabled' : '' ?>>
                    <span><?= htmlspecialchars($pp->cognome . ' ' . $pp->nome) ?><?php if (!empty($pp->external)) : ?> <small class="pt-muted">(Altro istituto)</small><?php elseif (!empty($pp->sede_nome)) : ?> <small class="pt-muted">· <?= htmlspecialchars($pp->sede_nome) ?></small><?php endif; ?>
                        <small class="pt-chip pt-chip-ok" data-picker-assigned <?= $has ? '' : 'hidden' ?>>già associata<?= $has && $current[$id] !== null ? ' · ' . Time::format((int) $current[$id]) : '' ?></small></span>
                </label>
                <input type="text" class="pt-input pt-picker-hours" name="monteore[<?= $id ?>]" value="<?= htmlspecialchars($mo) ?>" inputmode="decimal" placeholder="es. 300" aria-label="Ore previste sul progetto per <?= htmlspecialchars($pp->cognome . ' ' . $pp->nome) ?>" disabled>
                <input type="text" class="pt-input pt-picker-hours" name="monte_annuo[<?= $id ?>]" value="" inputmode="decimal" placeholder="es. 1520" aria-label="Monte ore annuo di <?= htmlspecialchars($pp->cognome . ' ' . $pp->nome) ?>" disabled>
            </li>
        <?php endforeach; ?>
    </ul>
    <p class="pt-help">Spunta le persone da associare e indica per ciascuna le <strong>ore previste sul progetto da rendicontare</strong> e, se il progetto lo richiede, il <strong>monte ore annuo</strong> (facoltativo); i valori possono essere diversi da persona a persona. Le persone già associate non si possono selezionare: le loro ore si modificano nell'elenco delle associazioni.</p>
</div>
