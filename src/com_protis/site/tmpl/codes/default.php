<?php

/**
 * @var \Cnr\Component\Protis\Site\View\Codes\HtmlView $this
 */

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Helper\Time;

$this->partial('header');
$newCount = \count(array_filter($this->items, static fn ($c) => !empty($c->is_new)));
?>
<div class="pt-page-head">
    <h1>Codici di assenza ePAS</h1>
</div>

<div class="pt-card pt-codes-help">
    <p><strong>Come funziona.</strong> I codici e le descrizioni sono quelli di ePAS e non si aggiungono né si eliminano a mano. "Scarica codici da ePAS" legge da ePAS i codici di assenza in uso; quelli che PROTIS IBF non conosce ancora vengono aggiunti
        <span class="pt-chip pt-chip-ok">in verde</span> in cima all'elenco: per ciascuno scegli la <strong>categoria</strong> e l'<strong>orario</strong>, poi premi <strong>Salva modifiche</strong>.</p>
    <ul>
        <li><strong>Categoria</strong>: Lavoro Agile, Telelavoro e Missione contano come ore rendicontabili; Malattia, Ferie, Permessi e Altro sono assenze.</li>
        <li><strong>Orario</strong>: le ore che il codice vale nella giornata (es. 07:12). Vuoto = il codice non aggiunge ore.</li>
        <li><strong>Escludi da Riempi/Distribuisci</strong>: se spuntato, i giorni con quel codice vengono saltati dagli strumenti automatici
            "Riempi il mese" e "Distribuisci ore mensili" del calendario. Serve per le assenze di giornata intera (malattia, ferie, riposo compensativo…);
            le ore si possono comunque inserire a mano nel giorno, se ci sono.</li>
    </ul>
</div>

<div class="pt-card pt-codes-bar">
        <input type="search" class="pt-input" placeholder="Cerca codice o descrizione…" data-pt-filter="#pt-codes" aria-label="Cerca codice o descrizione">
        <div class="pt-codes-sync">
        <span class="pt-last-sync">
            <?php if ($this->lastDownload) :
                $ts = \Joomla\CMS\HTML\HTMLHelper::_('date', $this->lastDownload->created, 'd/m/Y \a\l\l\e H:i');
                preg_match('/scaricati: (\d+), nuovi: (.*)$/', (string) $this->lastDownload->message, $mm); ?>
                <span>Ultimo scaricamento</span><strong><?= htmlspecialchars($ts) ?></strong>
                <?php if ($mm) : ?><small><?= (int) $mm[1] ?> codici · <?= $mm[2] === 'nessuno' ? 'nessun nuovo' : 'nuovi: ' . htmlspecialchars($mm[2]) ?></small><?php endif; ?>
            <?php else : ?>
                Mai scaricati da ePAS
            <?php endif; ?>
        </span>
        <form method="post" action="<?= $this->link('task=manage.downloadCodes') ?>">
            <input type="hidden" name="<?= $this->token() ?>" value="1">
            <button class="pt-btn pt-btn-primary">⟳ Scarica codici da ePAS</button>
        </form>
        </div>
</div>

<?php if ($newCount) : ?>
    <div class="pt-alert pt-alert-ok pt-flash"><strong><?= $newCount === 1 ? '1 codice nuovo' : $newCount . ' codici nuovi' ?></strong> da completare: sono evidenziati in verde qui sotto.</div>
<?php endif; ?>

<?php if (!$this->items) : ?>
    <div class="pt-empty">Nessun codice: premi "Scarica codici da ePAS".</div>
<?php else : ?>
<form method="post" action="<?= $this->link('task=manage.saveCodes') ?>" id="pt-codes-form">
    <input type="hidden" name="<?= $this->token() ?>" value="1">
    <div class="pt-table-wrap">
        <table class="pt-table pt-table-cards" id="pt-codes">
            <thead><tr><th>Codice</th><th>Descrizione</th><th>Categoria</th><th>Orario</th><th title="Esclude il giorno da Riempi il mese e Distribuisci ore mensili">Escludi da Riempi/Distribuisci</th></tr></thead>
            <tbody>
            <?php foreach ($this->items as $c) : $id = (int) $c->id; $n = 'codes[' . $id . ']'; ?>
                <tr data-row data-text="<?= htmlspecialchars(mb_strtolower($c->code . ' ' . $c->description . ' ' . ($this->categories[$c->category] ?? ''))) ?>" class="<?= !empty($c->is_new) ? 'pt-code-new' : '' ?>">
                    <td data-label="Codice"><strong><?= htmlspecialchars($c->code) ?></strong><?= !empty($c->is_new) ? ' <span class="pt-chip pt-chip-ok">nuovo</span>' : '' ?></td>
                    <td data-label="Descrizione"><?= htmlspecialchars((string) $c->description) ?: '<span class="pt-muted">–</span>' ?></td>
                    <td data-label="Categoria"><select class="pt-input" name="<?= $n ?>[category]" aria-label="Categoria"><?php foreach ($this->categories as $k => $l) : ?><option value="<?= $k ?>" <?= $c->category === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></td>
                    <td data-label="Orario"><input class="pt-input" style="width:90px" name="<?= $n ?>[minutes]" value="<?= $c->minutes !== null ? Time::format((int) $c->minutes) : '' ?>" placeholder="—" aria-label="Orario"></td>
                    <td data-label="Escludi da Riempi/Distribuisci" class="pt-center"><input type="hidden" name="<?= $n ?>[blocks_fill]" value="0"><input type="checkbox" name="<?= $n ?>[blocks_fill]" value="1" <?= $c->blocks_fill ? 'checked' : '' ?> aria-label="Escludi da Riempi/Distribuisci"></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="pt-form-actions pt-sticky-actions"><button class="pt-btn pt-btn-primary">Salva modifiche</button></div>
</form>
<script>
// Si inviano solo le righe modificate: con centinaia di codici si supererebbe il limite di campi di PHP (max_input_vars)
(() => {
  const f = document.getElementById('pt-codes-form');
  f.addEventListener('input', (e) => { const r = e.target.closest('tr[data-row]'); if (r) r.dataset.dirty = '1'; });
  f.addEventListener('change', (e) => { const r = e.target.closest('tr[data-row]'); if (r) r.dataset.dirty = '1'; });
  f.addEventListener('submit', (e) => {
    const dirty = f.querySelectorAll('tr[data-row][data-dirty]');
    if (!dirty.length) { e.preventDefault(); Protis.toast('Nessuna modifica da salvare.', 'warn'); return; }
    f.querySelectorAll('tr[data-row]:not([data-dirty]) [name^="codes["]').forEach((i) => { i.disabled = true; });
  });
})();
</script>
<?php endif; ?>
<?php $this->partial('footer'); ?>
