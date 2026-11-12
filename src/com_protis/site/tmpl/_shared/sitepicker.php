<?php

/**
 * Sede di afferenza: sedi dell'istituto dal menu oppure "Altra sede (scrivi)…" con campo libero.
 *
 * @var array       $sites
 * @var object|null $person
 * @var string      $label
 * @var bool        $required
 * @var bool        $disabled
 */

\defined('_JEXEC') or die;

$siteId   = (int) ($person->site_id ?? 0);
$altro    = !$siteId && trim((string) ($person->sede_esterna ?? '')) !== '' && empty($person->external);
$required = !empty($required);
$disabled = !empty($disabled);
?>
<div class="pt-sitepicker" data-sitepicker>
    <label><?= htmlspecialchars($label) ?>
        <select name="jform[site_id]" <?= $required ? 'required' : '' ?> <?= $disabled ? 'disabled' : '' ?> data-sitepicker-select>
            <option value="">—</option>
            <?php foreach ($sites as $s) : ?>
                <option value="<?= (int) $s->id ?>" <?= !$altro && $siteId === (int) $s->id ? 'selected' : '' ?>><?= htmlspecialchars(($s->istituto ? $s->istituto . ' – ' : '') . $s->sede) ?></option>
            <?php endforeach; ?>
            <option value="altro" <?= $altro ? 'selected' : '' ?>>Altra sede (scrivi)…</option>
        </select>
    </label>
    <label data-sitepicker-other <?= $altro ? '' : 'hidden' ?>>Sede di afferenza (scritta a mano)
        <input name="jform[sede_esterna]" value="<?= htmlspecialchars($altro ? (string) $person->sede_esterna : '') ?>" maxlength="150" placeholder="es. CNR - IBF Napoli" <?= $disabled ? 'disabled' : '' ?>>
    </label>
</div>
