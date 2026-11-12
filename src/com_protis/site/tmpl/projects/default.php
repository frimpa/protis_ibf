<?php

/**
 * @var \Cnr\Component\Protis\Site\View\Projects\HtmlView $this
 */

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Service\ProjectService;

$this->partial('header');
?>
<div class="pt-page-head pt-head-actions pt-projects-head">
    <h1>Progetti</h1>
    <form method="get" action="<?= \Joomla\CMS\Uri\Uri::base(true) ?>/index.php" class="pt-switch-form">
        <input type="hidden" name="option" value="com_protis"><input type="hidden" name="view" value="projects">
        <label class="pt-switch pt-switch-view <?= $this->archived ? 'is-on' : '' ?>" data-switch data-submit title="Mostra i progetti attivi o quelli archiviati (disattivati)">
        <input type="checkbox" name="archived" value="1" <?= $this->archived ? 'checked' : '' ?>>
        <span class="pt-switch-label pt-switch-off-label">Attivi <small>(<?= $this->countActive ?>)</small></span>
        <span class="pt-switch-track" aria-hidden="true"><span class="pt-switch-thumb"></span></span>
        <span class="pt-switch-label">Archiviati <small>(<?= $this->countArchived ?>)</small></span>
    </label>
    </form>
</div>
<div class="pt-list-bar pt-list-bar-projects">
    <input type="search" class="pt-input" placeholder="Filtra per acronimo, CUP, Grant Agreement, titolo…" data-pt-filter="#pt-projects" aria-label="Filtra progetti">
    <a class="pt-btn pt-btn-primary" href="<?= $this->link('view=project') ?>">+ Nuovo progetto</a>
</div>

<?php if (!$this->items) : ?>
    <div class="pt-empty"><?= $this->archived ? 'Nessun progetto archiviato.' : 'Nessun progetto attivo. Crea il primo progetto con i relativi work package.' ?></div>
<?php else : ?>
    <div class="pt-table-wrap">
        <table class="pt-table pt-table-cards" id="pt-projects">
            <thead><tr><th>Progetto</th><th>CUP / Grant Agreement</th><th>Periodo</th><th class="num">WP</th><th class="num">Persone</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($this->items as $p) : ?>
                <tr data-row class="pt-row-link" data-href="<?= $this->link('view=project&id=' . (int) $p->id) ?>" tabindex="0" title="Apri il progetto">
                    <td data-label="Progetto">
                        <a class="pt-row-title" href="<?= $this->link('view=project&id=' . (int) $p->id) ?>"><strong><?= htmlspecialchars($p->acronimo) ?></strong></a>
                        <?php if (!$p->published) : ?><span class="pt-chip pt-chip-warn">archiviato</span><?php endif; ?>
                        <div class="pt-muted pt-clamp"><?= htmlspecialchars((string) $p->titolo) ?></div>
                    </td>
                    <td data-label="CUP / GA"><?php if ($p->cup !== '') : ?><code><?= htmlspecialchars($p->cup) ?></code><?php endif; ?><?php if (($p->grant_agreement ?? '') !== '') : ?> <span class="pt-muted">GA</span> <code><?= htmlspecialchars($p->grant_agreement) ?></code><?php endif; ?><?= $p->cup === '' && ($p->grant_agreement ?? '') === '' ? '–' : '' ?></td>
                    <td data-label="Periodo"><?= $p->data_inizio ? date('d/m/Y', strtotime($p->data_inizio)) : '–' ?> → <?= ($p->data_proroga ?: $p->data_fine) ? date('d/m/Y', strtotime($p->data_proroga ?: $p->data_fine)) : '–' ?></td>
                    <td class="num" data-label="WP"><?= (int) $p->n_wp ?></td>
                    <td class="num" data-label="Persone"><?= (int) $p->n_persone ?></td>
                    <td class="actions"><a class="pt-btn pt-btn-sm" href="<?= $this->link('view=project&id=' . (int) $p->id) ?>">Apri</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php $this->partial('footer'); ?>
