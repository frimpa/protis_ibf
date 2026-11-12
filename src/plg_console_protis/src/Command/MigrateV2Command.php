<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Plugin\Console\Protis\Command;

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Service\LegacyImporter;
use Joomla\Console\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Migrazione da PROTIS IBF V.2 (WordPress).
 *
 * Esempio:
 *   php cli/joomla.php protis:migrate-v2 \
 *     --wp-db=<db WordPress> --proj-db=<db progetti> --wp-prefix=<prefisso>_ \
 *     --db-user=root --db-password=*** \
 *     --v2-path=/var/www/html/<cartella V.2>
 */
final class MigrateV2Command extends AbstractCommand
{
    protected static $defaultName = 'protis:migrate-v2';

    protected function configure(): void
    {
        $this->setDescription('Importa utenti, progetti, associazioni, calendari e ore da PROTIS IBF V.2');
        $this->addOption('db-host', null, InputOption::VALUE_REQUIRED, 'Host MySQL dei database V.2', 'localhost');
        $this->addOption('db-user', null, InputOption::VALUE_REQUIRED, 'Utente MySQL V.2');
        $this->addOption('db-password', null, InputOption::VALUE_REQUIRED, 'Password MySQL V.2 (oppure variabile d\'ambiente PROTIS_V2_DB_PASSWORD)');
        $this->addOption('wp-db', null, InputOption::VALUE_REQUIRED, 'Database WordPress V.2');
        $this->addOption('proj-db', null, InputOption::VALUE_REQUIRED, 'Database progetti/calendari V.2');
        $this->addOption('wp-prefix', null, InputOption::VALUE_REQUIRED, 'Prefisso tabelle WordPress', 'wp_');
        $this->addOption('v2-path', null, InputOption::VALUE_REQUIRED, 'Cartella dell\'installazione V.2 (loghi, JSON ePAS, chiave .env)');
        $this->addOption('key-file', null, InputOption::VALUE_REQUIRED, 'File chiave V.2 (default <v2-path>/.env/chiave.env)');
        $this->addOption('no-users', null, InputOption::VALUE_NONE, 'Non creare gli account Joomla (verranno creati al primo accesso LDAP)');
        $this->addOption('author-scope', null, InputOption::VALUE_REQUIRED, '(non più usato: i responsabili gestiscono tutte le sedi)', 'all');
        $this->addOption('sal', null, InputOption::VALUE_REQUIRED, 'Acronimi dei progetti con intestazione SAL scritta nel codice V.2, separati da virgola', '');
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('PROTIS IBF – migrazione dalla V.2 (WordPress)');

        if (!$input->getOption('wp-db') || !$input->getOption('proj-db')) {
            $io->error('Indicare i database della V.2 con --wp-db e --proj-db.');

            return 1;
        }

        $password = $input->getOption('db-password') ?? getenv('PROTIS_V2_DB_PASSWORD') ?: '';
        $conn     = [
            'host'     => $input->getOption('db-host'),
            'user'     => $input->getOption('db-user') ?? 'root',
            'password' => $password,
        ];

        try {
            $wp   = LegacyImporter::connect($conn + ['database' => $input->getOption('wp-db')]);
            $proj = LegacyImporter::connect($conn + ['database' => $input->getOption('proj-db')]);
            $wp->connect();
            $proj->connect();
        } catch (\Throwable $e) {
            $io->error('Connessione ai database V.2 fallita: ' . $e->getMessage());

            return 1;
        }

        $path    = $input->getOption('v2-path');
        $keyFile = $input->getOption('key-file') ?? ($path ? rtrim($path, '/') . '/.env/chiave.env' : null);
        $key     = $keyFile ? LegacyImporter::readLegacyKey($keyFile) : null;

        if ($path && !is_dir($path)) {
            $io->warning("Cartella V.2 non trovata: $path (loghi e JSON ePAS non verranno importati)");
            $path = null;
        }

        $importer = new LegacyImporter(
            $wp,
            $proj,
            (string) $input->getOption('wp-prefix'),
            $path,
            $key,
            static fn (string $m) => $io->writeln($m)
        );

        $importer->salProjects = array_filter(array_map(static fn ($a) => strtoupper(trim($a)), explode(',', (string) $input->getOption('sal'))));
        $res = $importer->run(!$input->getOption('no-users'), (string) $input->getOption('author-scope'));

        $io->section('Riepilogo');
        $io->table(['Elemento', 'Importati'], array_map(null, array_keys($res['stats']), array_values($res['stats'])));

        if ($res['warnings']) {
            $io->warning(\count($res['warnings']) . ' avvisi (vedi sopra): verificare prima di andare in produzione.');
        } else {
            $io->success('Migrazione completata senza avvisi.');
        }

        return 0;
    }
}
