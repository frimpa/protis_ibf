<?php

/**
 * @var \Cnr\Component\Protis\Site\View\People\HtmlView $this
 */

\defined('_JEXEC') or die;

$this->partial('header');
?>
<?php
$ist  = $this->sigla !== '' ? 'Personale ' . $this->sigla : 'Personale dell\'istituto';
// [etichetta, etichetta breve per smartphone]
$tabs = ['ist' => [$ist, $this->sigla !== '' ? $this->sigla : 'Istituto'], 'ext' => ['Altri istituti', 'Altri istituti']];
// gli account tecnici (amministratori senza matricola né progetti) non hanno una scheda: restano fuori dagli elenchi
?>
<div class="pt-page-head">
    <h1>Persone</h1>
</div>
<nav class="pt-seg" aria-label="Gruppi di persone">
    <?php foreach ($tabs as $k => [$label, $short]) : ?>
        <a class="pt-seg-item <?= $this->tab === $k ? 'is-active' : '' ?>" href="<?= $this->link('view=people&gruppo=' . $k) ?>" <?= $this->tab === $k ? 'aria-current="page"' : '' ?>><span class="pt-seg-long"><?= htmlspecialchars($label) ?></span><span class="pt-seg-short"><?= htmlspecialchars($short) ?></span> <span class="pt-seg-count"><?= (int) $this->counts[$k] ?></span></a>
    <?php endforeach; ?>
</nav>
<div class="pt-list-bar pt-list-bar-split">
    <input type="search" class="pt-input" placeholder="Filtra per nome, username, matricola…" data-pt-filter="#pt-people" aria-label="Filtra persone">
    <a class="pt-btn pt-btn-primary" href="<?= $this->link('view=person') ?>">+ Persona senza LDAP</a>
</div>

<?php if ($this->tab === 'ist') : ?>
<div class="pt-card">
    <form class="pt-toolbar" method="post" action="<?= $this->link('task=manage.syncAll') ?>" data-confirm="Aggiornare da ePAS il mese selezionato per tutte le persone con matricola? L'operazione può richiedere qualche minuto.">
        <input type="hidden" name="<?= $this->token() ?>" value="1">
        <strong>Sincronizza ePAS per tutti</strong>
        <select name="month" class="pt-input" style="max-width:150px" aria-label="Mese">
            <?php for ($m = 1; $m <= 12; $m++) : ?><option value="<?= $m ?>" <?= $m === (int) date('n') ? 'selected' : '' ?>><?= \Cnr\Component\Protis\Administrator\Helper\Time::monthName($m) ?></option><?php endfor; ?>
        </select>
        <input type="number" name="year" class="pt-input" style="max-width:100px" value="<?= date('Y') ?>" min="2020" max="2100" aria-label="Anno">
        <button class="pt-btn pt-btn-primary">Avvia</button>
        <span class="pt-help">La sincronizzazione automatica notturna è configurata dallo script di installazione (cron).</span>
    </form>
</div>
<?php endif; ?>

<?php if ($this->tab === 'ext') : ?>
<details class="pt-card" <?= $this->counts['ext'] ? '' : 'open' ?>>
    <summary><strong>+ Abilita colleghi di altri istituti CNR</strong> <span class="pt-muted">per username, es. per progetti condivisi</span></summary>
    <form class="pt-form" method="post" action="<?= $this->link('task=manage.addExternal') ?>" style="margin-top:.75rem">
        <input type="hidden" name="<?= $this->token() ?>" value="1"><input type="hidden" name="back" value="people">
        <label>Username CNR (uno per riga o separati da virgola)
            <textarea name="uids" rows="3" placeholder="mario.rossi&#10;anna.bianchi" autocapitalize="none" spellcheck="false" required></textarea>
        </label>
        <p class="pt-help">Per associarli subito a un progetto usa la stessa funzione nella scheda del progetto.</p>
        <div class="pt-form-actions"><button class="pt-btn pt-btn-primary">Abilita</button></div>
    </form>
</details>
<?php endif; ?>
<?php if (!$this->items) : ?>
    <div class="pt-empty"><?= $this->tab === 'ext' ? 'Nessun collega di altri istituti abilitato.' : 'Nessuna persona.' ?></div>
<?php endif; ?>

<div class="pt-table-wrap">
    <table class="pt-table pt-table-cards" id="pt-people">
        <thead><tr><th>Persona</th><th>Username</th><th>Matricola</th><th>Sede</th><th>Contratto</th><th class="num">Progetti</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($this->items as $p) : ?>
            <tr data-row class="pt-row-link" data-href="<?= $this->link('view=person&id=' . (int) $p->id) ?>" tabindex="0" title="Apri la scheda">
                <td data-label="Persona"><span class="pt-person-cell"><?= $this->miniAvatar($p) ?><strong><?= htmlspecialchars(\Cnr\Component\Protis\Administrator\Service\PeopleService::displayName($p)) ?></strong><?= trim($p->cognome . $p->nome) === '' ? ' <small class="pt-muted">(nome al primo accesso)</small>' : '' ?></span><?= $p->active ? '' : ' <span class="pt-chip pt-chip-warn">disattivo</span>' ?><?= ['manager' => ' <span class="pt-chip pt-chip-role">Responsabile</span>', 'admin' => ' <span class="pt-chip pt-chip-role">Amministratore</span>'][$this->roles[(int) $p->id] ?? ''] ?? '' ?><?= \Cnr\Component\Protis\Administrator\Service\PeopleService::usesEpas($p) ? ' <span class="pt-chip pt-chip-ok" title="Presenze lette da ePAS">ePAS</span>' : ' <span class="pt-chip" title="Presenze inserite a mano nel calendario">manuale</span>' ?></td>
                <td data-label="Username"><?= htmlspecialchars($p->uid) ?></td>
                <td data-label="Matricola"><?= $p->matricola ? htmlspecialchars($p->matricola) : '<span class="pt-muted">no matricola</span>' ?></td>
                <td data-label="Sede"><?= htmlspecialchars((string) ($p->sede_nome ?: (!empty($p->external) ? $p->ldap_gruppo : ''))) ?></td>
                <td data-label="Contratto"><?= htmlspecialchars($p->tipo_contratto) ?></td>
                <td class="num" data-label="Progetti"><?= (int) $p->n_progetti ?></td>
                <td class="actions">
                    <a class="pt-btn pt-btn-sm" href="<?= $this->link('view=calendar&person=' . (int) $p->id) ?>">Calendario</a>
                    <a class="pt-btn pt-btn-sm pt-btn-ghost" href="<?= $this->link('view=person&id=' . (int) $p->id) ?>">Scheda</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php $this->partial('footer'); ?>
