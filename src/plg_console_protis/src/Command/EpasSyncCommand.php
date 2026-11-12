<?php

/**
 * @package     PROTIS IBF
 * @copyright   (C) 2026 Francesco Impallari / CNR - Istituto di Biofisica
 * @license     EUPL-1.2 - vedi LICENSE
 */

namespace Cnr\Plugin\Console\Protis\Command;

\defined('_JEXEC') or die;

use Cnr\Component\Protis\Administrator\Helper\Time;
use Cnr\Component\Protis\Administrator\Service\Services;
use Joomla\Console\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * php cli/joomla.php protis:epas-sync [--month=YYYY-MM] [--previous-until=5] [--person=uid]
 *
 * Pensato per cron: sincronizza il mese corrente e, nei primi giorni del mese, anche il precedente.
 */
final class EpasSyncCommand extends AbstractCommand
{
    protected static $defaultName = 'protis:epas-sync';

    protected function configure(): void
    {
        $this->setDescription('Sincronizza presenze e assenze da ePAS per il personale con matricola');
        $this->addOption('month', null, InputOption::VALUE_REQUIRED, 'Mese da sincronizzare (YYYY-MM), default mese corrente');
        $this->addOption('previous-until', null, InputOption::VALUE_REQUIRED, 'Sincronizza anche il mese precedente fino a questo giorno del mese', '7');
        $this->addOption('person', null, InputOption::VALUE_REQUIRED, 'Solo questa persona (username o matricola)');
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $months = [];

        if ($m = $input->getOption('month')) {
            if (!preg_match('/^(\d{4})-(\d{2})$/', $m, $x)) {
                $io->error('Formato mese non valido (YYYY-MM)');

                return 1;
            }

            $months[] = [(int) $x[1], (int) $x[2]];
        } else {
            $months[] = [(int) date('Y'), (int) date('n')];

            if ((int) date('j') <= (int) $input->getOption('previous-until')) {
                $prev     = strtotime('first day of last month');
                $months[] = [(int) date('Y', $prev), (int) date('n', $prev)];
            }
        }

        $people = Services::epas()->syncablePeople();

        if ($only = $input->getOption('person')) {
            $people = array_filter($people, static fn ($p) => strcasecmp($p->uid, $only) === 0 || $p->matricola === $only);
        }

        $ok = $ko = 0;

        foreach ($months as [$y, $mo]) {
            $io->section(\sprintf('ePAS %s %d – %d persone', Time::monthName($mo), $y, \count($people)));

            foreach ($people as $p) {
                try {
                    $r = Services::epas()->syncMonth($p, $y, $mo, Services::calendar());
                    $io->writeln(\sprintf('  ✓ %-30s %2d giorni  %s', $p->cognome . ' ' . $p->nome, $r['days'], Time::format($r['worked'])));
                    $ok++;
                } catch (\Throwable $e) {
                    $io->writeln(\sprintf('  ✕ %-30s %s', $p->cognome . ' ' . $p->nome, $e->getMessage()));
                    $ko++;
                }
            }
        }

        $ko ? $io->warning("Completato con $ko errori ($ok ok)") : $io->success("Completato: $ok sincronizzazioni");

        return $ko && !$ok ? 1 : 0;
    }
}
