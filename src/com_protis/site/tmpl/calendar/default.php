<?php

/**
 * @var \Cnr\Component\Protis\Site\View\Calendar\HtmlView $this
 */

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Helper\Time;
use Joomla\CMS\Uri\Uri;

$this->partial('header');
$p = $this->person;
?>
<div id="pt-calendar" class="pt-calendar"
     data-person="<?= (int) ($p->id ?? 0) ?>"
     data-year="<?= $this->year ?>"
     data-month="<?= $this->month ?>"
     data-panel="<?= htmlspecialchars(\Joomla\CMS\Factory::getApplication()->getInput()->getCmd('panel', '')) ?>">

    <header class="pt-cal-head">
        <div class="pt-cal-monthnav">
            <button type="button" class="pt-btn pt-btn-icon pt-btn-round" data-act="prev" aria-label="Mese precedente">‹</button>
            <div class="pt-cal-titlebox">
                <span class="pt-cal-kicker" data-bind="kicker">Mese da rendicontare</span>
                <h1 class="pt-cal-title" data-bind="title"><?= Time::monthName($this->month) . ' ' . $this->year ?></h1>
            </div>
            <button type="button" class="pt-btn pt-btn-icon pt-btn-round" data-act="next" aria-label="Mese successivo">›</button>
            <div class="pt-cal-jump">
                <select class="pt-input" data-jump="m" aria-label="Vai al mese"><?php for ($i = 1; $i <= 12; $i++) : ?><option value="<?= $i ?>" <?= $i === (int) $this->month ? 'selected' : '' ?>><?= Time::monthName($i) ?></option><?php endfor; ?></select>
                <select class="pt-input" data-jump="y" aria-label="Vai all'anno"><?php for ($y = (int) date('Y') + 1; $y >= 2020; $y--) : ?><option value="<?= $y ?>" <?= $y === (int) $this->year ? 'selected' : '' ?>><?= $y ?></option><?php endfor; ?></select>
                <button type="button" class="pt-btn pt-btn-ghost pt-btn-sm" data-act="today" title="Vai al mese in corso">Oggi</button>
            </div>
        </div>

        <div class="pt-cal-who">
            <?php if ($this->canManage && $this->people) : ?>
                <label class="pt-person-select">
                    <span class="pt-cal-label">Calendario di</span>
                    <select data-act="person">
                        <?php foreach ($this->people as $pp) : ?>
                            <option value="<?= (int) $pp->id ?>" <?= (int) $pp->id === (int) $p->id ? 'selected' : '' ?>>
                                <?= htmlspecialchars(trim($pp->cognome . ' ' . $pp->nome) ?: $pp->uid) ?><?= !empty($pp->external) ? ' (Altro istituto)' : ($pp->sede_nome ? ' · ' . htmlspecialchars($pp->sede_nome) : '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <div class="pt-cal-info" data-bind="info"></div>
        </div>
    </header>

    <div class="pt-cal-bar">
        <div class="pt-cal-actions">
            <button type="button" class="pt-btn pt-btn-primary" data-act="sync" hidden>
                <svg class="pt-ico" viewBox="0 0 24 24"><path d="M21 12a9 9 0 0 1-15.5 6.2M3 12A9 9 0 0 1 18.5 5.8M21 4v5h-5M3 20v-5h5"/></svg>
                <span>Aggiorna da ePAS</span>
            </button>
            <div class="pt-dropdown">
                <button type="button" class="pt-btn" data-pt-toggle aria-haspopup="true">Strumenti ▾</button>
                <div class="pt-dropdown-menu" role="menu">
                    <button type="button" role="menuitem" data-act="open-fill">Riempi il mese con un progetto</button>
                    <button type="button" role="menuitem" data-act="open-distribute">Distribuisci ore mensili</button>
                    <button type="button" role="menuitem" data-act="open-export">Genera timesheet</button>
                    <hr>
                    <button type="button" role="menuitem" class="pt-danger" data-act="open-clear">Svuota ore del mese…</button>
                </div>
            </div>
        </div>
        <div class="pt-legend">
            <span><i class="pt-dot pt-dot-ok"></i>completo</span>
            <span><i class="pt-dot pt-dot-partial"></i>parziale</span>
            <span><i class="pt-dot pt-dot-over"></i>oltre il rendicontabile</span>
            <span><i class="pt-dot pt-dot-abs"></i>assenza</span>
            <span><i class="pt-dot pt-dot-off"></i>non rendicontabile</span>
        </div>
    </div>

    <div class="pt-cal-note" data-bind="note" hidden></div>

    <div class="pt-cal-layout">
        <section class="pt-cal-main">
            <div class="pt-cal-grid" data-bind="grid" aria-live="polite">
                <div class="pt-skeleton">Caricamento…</div>
            </div>
            <div class="pt-card pt-export-wide" id="pt-export-card">
                <h2 class="pt-card-title">Genera timesheet</h2>
                <form method="get" action="<?= Uri::base(true) ?>/index.php" target="_blank" class="pt-form pt-export-form" data-bind="export">
                    <input type="hidden" name="option" value="com_protis">
                    <input type="hidden" name="task" value="export.timesheet">
                    <input type="hidden" name="person" value="<?= (int) ($p->id ?? 0) ?>">
                    <input type="hidden" name="<?= $this->token() ?>" value="1">
                    <input type="hidden" name="return" value="<?= base64_encode(Uri::getInstance()->toString()) ?>">
                    <div class="pt-export-row">
                    <label class="pt-export-project">Progetto
                        <select name="project" required data-bind="exportProjects"></select>
                    </label>
                        <?php $this->partial('monthpicker', ['field' => 'from', 'label' => 'Dal mese', 'value' => date('Y-m', strtotime('first day of last month')), 'bind' => 'exportFrom']); ?>
                        <?php $this->partial('monthpicker', ['field' => 'to', 'label' => 'Al mese', 'value' => date('Y-m', strtotime('first day of last month')), 'bind' => 'exportTo']); ?>
                    <label>Formato
                        <select name="format_out">
                            <option value="pdf">PDF (stampa e firma)</option>
                            <option value="xlsx">Excel (.xlsx)</option>
                            <option value="ods">LibreOffice (.ods)</option>
                            <option value="csv">CSV</option>
                            <option value="tex">LaTeX (.tex)</option>
                        </select>
                    </label>
                    <button type="submit" class="pt-btn pt-btn-accent pt-export-btn">Scarica timesheet</button>
                    </div>
                    <p class="pt-help">Con più mesi viene prodotta una pagina per mese.</p>
                </form>
            </div>
        </section>

        <aside class="pt-cal-side">
            <div class="pt-card">
                <h2 class="pt-card-title">Riepilogo del mese</h2>
                <dl class="pt-kv" data-bind="summary"></dl>
            </div>
            <div class="pt-card">
                <h2 class="pt-card-title">Ore per progetto del mese</h2>
                <div data-bind="projects"></div>
            </div>
        </aside>
    </div>
</div>

<!-- Dialog: giorno -->
<dialog id="pt-day" class="pt-dialog">
    <form method="dialog" class="pt-form" data-form="day">
        <header class="pt-dialog-head">
            <div>
                <h2 data-bind="dayTitle">Giorno</h2>
                <p class="pt-muted" data-bind="daySub"></p>
            </div>
            <button type="button" class="pt-btn pt-btn-icon" data-pt-close aria-label="Chiudi">✕</button>
        </header>
        <div class="pt-dialog-body">
            <fieldset class="pt-manual" data-bind="manual" hidden>
                <legend>Presenza del giorno <small>(personale senza timbrature ePAS)</small></legend>
                <div class="pt-row pt-row-wrap">
                    <label>Lavoro <input type="text" inputmode="decimal" autocomplete="off" placeholder="hh:mm" class="pt-dur" name="manual[worked]"></label>
                    <label>Lavoro Agile <input type="text" inputmode="decimal" autocomplete="off" placeholder="hh:mm" class="pt-dur" name="manual[smartworking]"></label>
                    <label>Telelavoro <input type="text" inputmode="decimal" autocomplete="off" placeholder="hh:mm" class="pt-dur" name="manual[telelavoro]"></label>
                    <label>Missione <input type="text" inputmode="decimal" autocomplete="off" placeholder="hh:mm" class="pt-dur" name="manual[missione]"></label>
                    <label>Malattia <input type="text" inputmode="decimal" autocomplete="off" placeholder="hh:mm" class="pt-dur" name="manual[malattia]"></label>
                    <label>Ferie <input type="text" inputmode="decimal" autocomplete="off" placeholder="hh:mm" class="pt-dur" name="manual[ferie]"></label>
                    <label>Permessi <input type="text" inputmode="decimal" autocomplete="off" placeholder="hh:mm" class="pt-dur" name="manual[permessi]"></label>
                    <label>Altro <input type="text" inputmode="decimal" autocomplete="off" placeholder="hh:mm" class="pt-dur" name="manual[altro]"></label>
                </div>
                <div class="pt-quick">
                    <span>Rapido:</span>
                    <button type="button" class="pt-chip-btn" data-quick="worked=07:12">Giornata 7:12</button>
                    <button type="button" class="pt-chip-btn" data-quick="smartworking=07:12">Lavoro Agile 7:12</button>
                    <button type="button" class="pt-chip-btn" data-quick="telelavoro=07:12">Telelavoro 7:12</button>
                    <button type="button" class="pt-chip-btn" data-quick="missione=07:12">Missione 7:12</button>
                    <button type="button" class="pt-chip-btn" data-quick="ferie=07:12">Ferie 7:12</button>
                    <button type="button" class="pt-chip-btn" data-quick="malattia=07:12">Malattia 7:12</button>
                    <button type="button" class="pt-chip-btn" data-quick="permessi=07:12">Permessi 7:12</button>
                    <button type="button" class="pt-chip-btn" data-quick="clear">Svuota</button>
                </div>
            </fieldset>
            <div class="pt-day-capacity" data-bind="dayCap"></div>
            <div data-bind="dayWps"></div>
        </div>
        <footer class="pt-dialog-foot">
            <span class="pt-day-total" data-bind="dayTotal"></span>
            <button type="button" class="pt-btn pt-btn-ghost" data-pt-close>Annulla</button>
            <button type="submit" class="pt-btn pt-btn-primary" value="save">Salva</button>
        </footer>
    </form>
</dialog>

<!-- Dialog: riempi mese -->
<dialog id="pt-fill" class="pt-dialog">
    <form method="dialog" class="pt-form" data-form="fill">
        <header class="pt-dialog-head"><h2>Riempi il mese con un progetto</h2><button type="button" class="pt-btn pt-btn-icon" data-pt-close aria-label="Chiudi">✕</button></header>
        <div class="pt-dialog-body">
            <p class="pt-muted">Assegna al work package scelto le ore rendicontabili di ogni giorno utile. I giorni con malattia, ferie, permessi o codici esclusi vengono saltati.</p>
            <label>Work package <select name="wp" required data-bind="wpSelect"></select></label>
            <input type="hidden" name="mode" value="residual">
            <p class="pt-help">Vengono usate solo le ore ancora libere di ogni giorno: le ore già inserite su altri progetti restano invariate.</p>
        </div>
        <footer class="pt-dialog-foot">
            <button type="button" class="pt-btn pt-btn-ghost" data-pt-close>Annulla</button>
            <button type="submit" class="pt-btn pt-btn-primary">Conferma</button>
        </footer>
    </form>
</dialog>

<!-- Dialog: distribuisci -->
<dialog id="pt-distribute" class="pt-dialog pt-dialog-wide">
    <form method="dialog" class="pt-form" data-form="distribute">
        <header class="pt-dialog-head"><h2>Distribuisci ore mensili</h2><button type="button" class="pt-btn pt-btn-icon" data-pt-close aria-label="Chiudi">✕</button></header>
        <div class="pt-dialog-body">
            <p class="pt-muted">Indica il totale del mese per ciascun WP (es. <code>40:00</code> o <code>40,5</code>). Le ore vengono ripartite sui giorni utili senza superare il rendicontabile; i WP compilati vengono prima azzerati nel mese.</p>
            <div class="pt-distribute-cap" data-bind="distCap"></div>
            <div data-bind="distWps"></div>
            <div class="pt-row pt-row-wrap">
                <fieldset class="pt-radio">
                    <legend>Modalità</legend>
                    <label><input type="radio" name="mode" value="uniform" checked> Uniforme (proporzionale alle ore del giorno)</label>
                    <label><input type="radio" name="mode" value="random"> Casuale</label>
                </fieldset>
                <label>Ore giornaliere assegnate a blocchi di
                    <select name="granularity">
                        <option value="15" selected>un quarto d'ora (es. 2:15, 2:30, 2:45)</option>
                        <option value="30">mezz'ora (es. 2:00, 2:30)</option>
                        <option value="5">5 minuti (es. 2:35, 2:40)</option>
                        <option value="1">un minuto (valori qualsiasi, es. 2:37)</option>
                    </select>
                    <small>Così le ore di ogni giorno risultano "tonde". L'ultimo giorno può avere qualche minuto di differenza per far tornare il totale.</small>
                </label>
            </div>
        </div>
        <footer class="pt-dialog-foot">
            <span class="pt-day-total" data-bind="distTotal"></span>
            <button type="button" class="pt-btn pt-btn-ghost" data-pt-close>Annulla</button>
            <button type="submit" class="pt-btn pt-btn-primary">Distribuisci</button>
        </footer>
    </form>
</dialog>

<!-- Dialog: svuota -->
<dialog id="pt-clear" class="pt-dialog">
    <form method="dialog" class="pt-form" data-form="clear">
        <header class="pt-dialog-head"><h2>Svuota ore del mese</h2><button type="button" class="pt-btn pt-btn-icon" data-pt-close aria-label="Chiudi">✕</button></header>
        <div class="pt-dialog-body">
            <p>Le ore rendicontate nel mese selezionato verranno eliminate. Le presenze ePAS non vengono toccate.</p>
            <label>Cosa svuotare <select name="target" data-bind="clearProjects"></select></label>
            <p class="pt-help">Puoi svuotare tutti i progetti, un intero progetto oppure un solo work package.</p>
        </div>
        <footer class="pt-dialog-foot">
            <button type="button" class="pt-btn pt-btn-ghost" data-pt-close>Annulla</button>
            <button type="submit" class="pt-btn pt-btn-danger">Svuota</button>
        </footer>
    </form>
</dialog>
<?php $this->partial('footer'); ?>
