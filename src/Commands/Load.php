<?php

namespace Acpl\FlarumDbSnapshots\Commands;

use Exception;
use Flarum\Console\AbstractCommand;
use Flarum\Foundation\Config;
use Illuminate\Database\ConnectionInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Process;

class Load extends AbstractCommand
{
    public function __construct(protected ConnectionInterface $db, protected Config $config)
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

    protected function fire(): int
    {
        $path = $this->input->getArgument('path');
        if (! is_readable($path)) {
            $this->error("File not found or is not readable: $path");

            return 1;
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

            return 1;
        }

        return 0;
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
