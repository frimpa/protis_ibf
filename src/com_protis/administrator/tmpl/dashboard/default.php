<?php

/**
 * @var \Cnr\Component\Protis\Administrator\View\Dashboard\HtmlView $this
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;

$site = Uri::root() . 'index.php?option=com_protis';
?>
<div class="row">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="card-title">PROTIS IBF</h2>
                <p>Le funzioni operative (calendario, progetti, associazioni, dashboard, impostazioni ePAS) sono disponibili nel <strong>sito</strong>, con interfaccia ottimizzata per smartphone e tablet e installabile come app.</p>
                <p>
                    <a class="btn btn-primary" href="<?= $site ?>&view=home" target="_blank" rel="noopener">Apri PROTIS IBF</a>
                    <a class="btn btn-outline-secondary" href="<?= $site ?>&view=settings" target="_blank" rel="noopener">Sedi e credenziali ePAS</a>
                    <a class="btn btn-outline-secondary" href="index.php?option=com_users&view=users">Utenti e gruppi</a>
                    <a class="btn btn-outline-secondary" href="index.php?option=com_plugins&view=plugins&filter[search]=protis">Plugin PROTIS IBF</a>
                </p>
                <h3 class="h5 mt-4">Ruoli</h3>
                <ul>
                    <li><strong>Utente</strong> (Registered): proprio calendario, profilo, associazioni ai progetti e timesheet.</li>
                    <li><strong>PROTIS Responsabili</strong>: progetti, associazioni, persone, codici e calendari di tutte le sedi.</li>
                    <li><strong>PROTIS Amministratori</strong>: tutto, incluse sedi e credenziali ePAS.</li>
                </ul>
                <h3 class="h5 mt-4">Comandi da terminale</h3>
<pre class="bg-light p-2 border rounded"><code>php cli/joomla.php protis:epas-sync            # sincronizza il mese corrente (cron)
php cli/joomla.php protis:migrate-v2 --help      # importa i dati della V.2</code></pre>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-body">
                <h3 class="h5">Dati</h3>
                <table class="table table-sm">
                    <?php foreach ($this->counts as $label => $n) : ?><tr><td><?= $label ?></td><td class="text-end"><?= number_format($n, 0, ',', '.') ?></td></tr><?php endforeach; ?>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <h3 class="h5">Verifiche</h3>
                <ul class="list-unstyled">
                    <?php foreach ($this->checks as $label => $ok) : ?>
                        <li><span class="badge bg-<?= $ok ? 'success' : 'danger' ?>"><?= $ok ? 'OK' : 'NO' ?></span> <?= htmlspecialchars($label) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
