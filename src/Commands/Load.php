<?php

namespace Acpl\FlarumDbSnapshots\Commands;

use Exception;
use Flarum\Console\AbstractCommand;
use Flarum\Foundation\Config;
use Flarum\Foundation\Paths;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Arr;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Process\Process;

class Load extends AbstractCommand
{
    public function __construct(protected ConnectionInterface $db, protected Config $config, protected Paths $paths)
    {
        parent::__construct('snapshot:load');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Load a database dump')
            ->addArgument('path', InputArgument::REQUIRED, 'Path to the SQL dump file')
            ->addOption('drop-tables', null, InputOption::VALUE_NONE, 'Drop all existing tables before loading')
            ->addOption(
                'binary-path',
                null,
                InputOption::VALUE_REQUIRED,
                'Custom location for the mysql binary',
                'mysql'
            );
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        if (! empty($input->getArgument('path'))) {
            return;
        }

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        $snapshotsDir = $this->paths->storage.'/snapshots';
        $paths = glob("$snapshotsDir/*.{sql,gz,bz2}", GLOB_BRACE);
        if (empty($paths)) {
            $input->setArgument('path', $helper->ask(
                $input,
                $output,
                new Question("No snapshots were found in $snapshotsDir. Please specify a snapshot path: ")
            ));

            return;
        }

        usort($paths, fn ($a, $b) => -(filemtime($a) <=> filemtime($b)));

        $file = $helper->ask(
            $input,
            $output,
            new ChoiceQuestion('Select a snapshot to load:', array_map('basename', $paths))
        );

        $input->setArgument(
            'path',
            Arr::first($paths, fn ($path) => str_ends_with($path, $file))
        );
    }

    protected function fire(): int
    {
        $path = $this->input->getArgument('path');
        if (! is_readable($path)) {
            $this->error("File not found or is not readable: $path");

            return self::FAILURE;
        }

        if ($this->input->getOption('drop-tables')) {
            $this->info('Dropping all existing tables...');
            $this->db->getSchemaBuilder()->dropAllTables();
        }

        $this->info("Restoring database from $path...");

        try {
            $this->runImport($path);
            $this->info('Database restored successfully.');
        } catch (Exception $e) {
            $this->error('Failed to restore database: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function runImport(string $path): void
    {
        $dbConfig = $this->config['database'];
        $mysqlBinary = $this->input->getOption('binary-path');

        $baseCommand = sprintf(
            '%s --host=%s --port=%s --user=%s --password=%s %s',
            escapeshellarg($mysqlBinary),
            escapeshellarg($dbConfig['host']),
            escapeshellarg((string) ($dbConfig['port'] ?? 3306)),
            escapeshellarg($dbConfig['username']),
            escapeshellarg((string) $dbConfig['password']),
            escapeshellarg($dbConfig['database']),
        );

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $escapedPath = escapeshellarg($path);

        $fullCommand = match ($extension) {
            'gz' => "gzip -dc $escapedPath | $baseCommand",
            'bz2' => "bzip2 -dc $escapedPath | $baseCommand",
            default => "$baseCommand < $escapedPath",
        };

        Process::fromShellCommandline($fullCommand, null, null, null, null)
            ->mustRun();
    }
}
