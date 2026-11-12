<?php

/**
 * @var \Cnr\Component\Protis\Site\View\Log\HtmlView $this
 */

\defined('_JEXEC') or die;

$this->partial('header');
?>
<div class="pt-page-head">
    <h1>Registro attività</h1>
    <input type="search" class="pt-input" style="max-width:320px" placeholder="Filtra…" data-pt-filter="#pt-log" aria-label="Filtra registro">
</div>
<div class="pt-table-wrap">
    <table class="pt-table pt-table-cards" id="pt-log">
        <thead><tr><th>Data</th><th>Utente</th><th>Persona</th><th>Azione</th><th>Dettaglio</th></tr></thead>
        <tbody>
        <?php foreach ($this->items as $l) : ?>
            <tr data-row>
                <td data-label="Data"><?= htmlspecialchars(\Joomla\CMS\HTML\HTMLHelper::_('date', $l->created, 'd/m/Y H:i')) ?></td>
                <td data-label="Utente"><?= htmlspecialchars((string) ($l->user_name ?? 'sistema')) ?></td>
                <td data-label="Persona"><?= htmlspecialchars((string) $l->person_name) ?></td>
                <td data-label="Azione"><code><?= htmlspecialchars($l->action) ?></code></td>
                <td data-label="Dettaglio"><?= htmlspecialchars((string) $l->message) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php $this->partial('footer'); ?>
