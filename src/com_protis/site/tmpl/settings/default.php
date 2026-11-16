<?php

/**
 * @var \Cnr\Component\Protis\Site\View\Settings\HtmlView $this
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;

$this->partial('header');
$ok = static fn (bool $b, string $yes, string $no) => $b ? '<span class="pt-chip pt-chip-ok">✓ ' . $yes . '</span>' : '<span class="pt-chip pt-chip-danger">✕ ' . $no . '</span>';
?>
<div class="pt-page-head">
    <h1>Impostazioni</h1>
    <a class="pt-btn pt-btn-ghost" href="<?= Uri::root(true) ?>/administrator/index.php?option=com_config&view=component&component=com_protis" target="_blank" rel="noopener">Opzioni avanzate e permessi ↗</a>
</div>

<section class="pt-card">
    <h2 class="pt-card-title">Verifica di sistema</h2>
    <p class="pt-toolbar">
        <?= $ok($this->pdflatex, 'pdflatex disponibile', 'pdflatex mancante (apt install texlive-latex-extra)') ?>
        <?= $ok($this->spreadsheet, 'PhpSpreadsheet (Excel/ODS)', 'PhpSpreadsheet mancante') ?>
        <?= $ok($this->ldap, 'estensione PHP LDAP', 'php-ldap mancante') ?>
        <?= $ok(\extension_loaded('sodium'), 'cifratura sodium', 'php-sodium mancante') ?>
        <?= $ok(\extension_loaded('curl'), 'cURL (ePAS)', 'php-curl mancante') ?>
        <span class="pt-chip">PHP <?= PHP_VERSION ?></span><span class="pt-chip">Joomla <?= JVERSION ?></span>
    </p>
</section>

<?php
$ibfSites = array_filter($this->sites, static fn ($s) => empty($s->epas_only));
$extSites = array_filter($this->sites, static fn ($s) => !empty($s->epas_only));
$siteTable = function (array $rows, bool $ext) {
    if (!$rows) {
        echo '<p class="pt-empty">' . ($ext ? 'Nessuna sede di altri istituti.' : 'Nessuna sede.') . '</p>';

        return;
    } ?>
    <div class="pt-table-wrap">
        <table class="pt-table pt-table-cards">
            <thead><tr><th>Sede</th><th>Codice sede</th><th>Istituto</th><th>Credenziali ePAS</th><th>Attiva</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $s) : $fid = 'site-' . (int) $s->id; ?>
                <tr>
                    <td data-label="Sede"><input form="<?= $fid ?>" class="pt-input" name="jform[sede]" value="<?= htmlspecialchars($s->sede) ?>" aria-label="Sede"></td>
                    <td data-label="Codice sede"><input form="<?= $fid ?>" class="pt-input" style="width:100px" name="jform[codice_sede]" value="<?= htmlspecialchars($s->codice_sede) ?>" aria-label="Codice sede"></td>
                    <td data-label="Istituto">
                        <input form="<?= $fid ?>" class="pt-input" name="jform[istituto]" value="<?= htmlspecialchars($s->istituto) ?>" aria-label="Istituto">
                        <input form="<?= $fid ?>" type="hidden" name="jform[sigla]" value="<?= htmlspecialchars($s->sigla) ?>"><input form="<?= $fid ?>" type="hidden" name="jform[ente]" value="<?= htmlspecialchars($s->ente) ?>">
                        <input form="<?= $fid ?>" type="hidden" name="jform[epas_only]" value="<?= $ext ? 1 : 0 ?>">
                    </td>
                    <td data-label="Credenziali ePAS">
                        <?= $s->has_epas ? '<span class="pt-chip pt-chip-ok">configurate</span>' : '<span class="pt-chip pt-chip-warn">mancanti</span>' ?>
                        <input form="<?= $fid ?>" class="pt-input" name="jform[epas_user]" placeholder="<?= $s->has_epas ? 'nuovo utente (per cambiarlo)' : 'utente ePAS' ?>" autocomplete="off" aria-label="Utente ePAS">
                        <input form="<?= $fid ?>" class="pt-input" type="password" name="jform[epas_password]" placeholder="<?= $s->has_epas ? 'nuova password (per cambiarla)' : 'password ePAS' ?>" autocomplete="new-password" aria-label="Password ePAS">
                    </td>
                    <td data-label="Attiva"><input form="<?= $fid ?>" type="hidden" name="jform[published]" value="0"><input form="<?= $fid ?>" type="checkbox" name="jform[published]" value="1" <?= $s->published ? 'checked' : '' ?> aria-label="Attiva"></td>
                    <td class="actions">
                        <form id="<?= $fid ?>" method="post" action="<?= $this->link('task=manage.saveSite') ?>">
                            <input type="hidden" name="jform[id]" value="<?= (int) $s->id ?>"><input type="hidden" name="<?= $this->token() ?>" value="1">
                            <button class="pt-btn pt-btn-sm pt-btn-primary">Salva</button>
                        </form>
                        <form method="post" action="<?= $this->link('task=manage.deleteSite') ?>" data-confirm="Eliminare la sede?">
                            <input type="hidden" name="id" value="<?= (int) $s->id ?>"><input type="hidden" name="<?= $this->token() ?>" value="1">
                            <button class="pt-btn pt-btn-sm pt-btn-ghost pt-danger">×</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php }; ?>

<section class="pt-card" id="sedi-ibf">
    <h2 class="pt-card-title">Sedi dell'istituto e credenziali ePAS</h2>
    <p class="pt-muted">Il codice sede corrisponde al <em>departmentNumber</em> LDAP: solo il personale delle sedi elencate (attive) può accedere. Le credenziali ePAS (ruolo "gestore anagrafica") sono cifrate e non vengono mai mostrate.</p>
    <?php $siteTable($ibfSites, false); ?>
    <details style="margin-top:1rem">
        <summary class="pt-btn pt-btn-sm">+ Aggiungi sede dell'istituto</summary>
        <form class="pt-form" method="post" action="<?= $this->link('task=manage.saveSite') ?>" style="margin-top:.75rem">
            <input type="hidden" name="<?= $this->token() ?>" value="1"><input type="hidden" name="jform[epas_only]" value="0">
            <div class="pt-row">
                <label>Ente <input name="jform[ente]" value="CNR"></label>
                <label>Istituto * <input name="jform[istituto]" required placeholder="es. Istituto di Biofisica" value="<?= htmlspecialchars((string) ($ibfSites ? reset($ibfSites)->istituto : '')) ?>"></label>
                <label>Sigla <input name="jform[sigla]" placeholder="es. IBF" value="<?= htmlspecialchars((string) ($ibfSites ? reset($ibfSites)->sigla : '')) ?>"></label>
            </div>
            <div class="pt-row">
                <label>Sede * <input name="jform[sede]" required></label>
                <label>Codice sede ePAS * <input name="jform[codice_sede]" required pattern="\d{3,10}"></label>
                <label>Utente ePAS <input name="jform[epas_user]" autocomplete="off"></label>
                <label>Password ePAS <input type="password" name="jform[epas_password]" autocomplete="new-password"></label>
            </div>
            <div class="pt-form-actions"><button class="pt-btn pt-btn-primary">Aggiungi sede</button></div>
        </form>
    </details>
</section>

<section class="pt-card" id="altri-istituti">
    <h2 class="pt-card-title">ePAS di altri istituti (colleghi esterni)</h2>
    <p class="pt-muted">Per leggere da ePAS le presenze di un collega di un altro istituto, abilitato per username in Persone, aggiungi qui la sede del <strong>suo</strong> istituto
        con il codice sede e le credenziali ePAS "gestore anagrafica" di quell'istituto (le credenziali di un istituto vedono solo il suo personale).
        Poi, nella scheda del collega, scegli la sede in <em>Presenze → Sede ePAS del suo istituto</em>.
        Queste sedi <strong>non</strong> abilitano l'accesso del loro personale e non compaiono tra le sedi dell'istituto.</p>
    <?php $siteTable($extSites, true); ?>
    <details style="margin-top:1rem" <?= $extSites ? '' : 'open' ?>>
        <summary class="pt-btn pt-btn-sm">+ Aggiungi sede di altro istituto</summary>
        <form class="pt-form" method="post" action="<?= $this->link('task=manage.saveSite') ?>" style="margin-top:.75rem">
            <input type="hidden" name="<?= $this->token() ?>" value="1"><input type="hidden" name="jform[epas_only]" value="1">
            <div class="pt-row">
                <label>Ente <input name="jform[ente]" value="CNR"></label>
                <label>Istituto * <input name="jform[istituto]" required placeholder="es. Istituto di Neuroscienze"></label>
                <label>Sigla <input name="jform[sigla]" placeholder="es. IN"></label>
            </div>
            <div class="pt-row">
                <label>Sede * <input name="jform[sede]" required placeholder="es. Pisa"></label>
                <label>Codice sede ePAS * <input name="jform[codice_sede]" required pattern="\d{3,10}" placeholder="es. 223000"></label>
                <label>Utente ePAS * <input name="jform[epas_user]" required autocomplete="off"></label>
                <label>Password ePAS * <input type="password" name="jform[epas_password]" required autocomplete="new-password"></label>
            </div>
            <div class="pt-form-actions"><button class="pt-btn pt-btn-primary">Aggiungi sede di altro istituto</button></div>
        </form>
    </details>
</section>

<section class="pt-card" id="pt-epas-test">
    <h2 class="pt-card-title">Test connessione ePAS</h2>
    <p class="pt-help" style="margin-bottom:.6rem">Legge da ePAS le presenze del mese corrente della persona scelta e le aggiorna nel suo calendario. Compaiono le persone attive con matricola e presenze «Da ePAS»; i colleghi di altri istituti solo dopo aver scelto, nella loro scheda, la sede ePAS del loro istituto.</p>
    <?php if ($this->epasTest) : ?>
        <div class="pt-alert pt-alert-<?= $this->epasTest['type'] === 'ok' ? 'ok' : 'danger' ?>" role="status" style="margin-bottom:.75rem"><?= htmlspecialchars($this->epasTest['text']) ?></div>
    <?php endif; ?>
    <form class="pt-toolbar" method="post" action="<?= $this->link('task=manage.testEpas') ?>">
        <input type="hidden" name="<?= $this->token() ?>" value="1">
        <select name="person_id" class="pt-input" required aria-label="Persona">
            <?php
            $epasGroups = ['Personale dell\'istituto' => [], 'Altri istituti' => []];
            foreach ($this->epasPeople as $p) {
                $epasGroups[empty($p->external) ? 'Personale dell\'istituto' : 'Altri istituti'][] = $p;
            }
            $epasSel = (int) ($this->epasTest['person'] ?? 0);
            ?>
            <option value="" disabled <?= $epasSel ? '' : 'selected' ?>>Scegli la persona…</option>
            <?php foreach ($epasGroups as $grp => $list) : ?>
                <optgroup label="<?= htmlspecialchars($grp) ?>">
                <?php if (!$list) : ?><option value="" disabled>nessun collega con la sede ePAS del suo istituto</option><?php endif; ?>
                <?php foreach ($list as $p) :
                    $lbl = trim($p->cognome . ' ' . $p->nome) . ' (' . $p->matricola . ')';
                    if (!empty($p->external)) {
                        $lbl .= ' – Altro istituto' . (trim(($p->epas_sigla ?? '') . ' ' . ($p->epas_sede ?? '')) !== '' ? ', ePAS ' . trim($p->epas_sigla . ' ' . $p->epas_sede) : '');
                    }
                ?><option value="<?= (int) $p->id ?>" <?= $epasSel === (int) $p->id ? 'selected' : '' ?>><?= htmlspecialchars($lbl) ?></option><?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>
        <button class="pt-btn">Sincronizza mese corrente</button>
    </form>
</section>
<?php $this->partial('footer'); ?>
