<?php

namespace Acpl\FlarumDbSnapshots\Commands;

use Acpl\FlarumDbSnapshots\Helpers\Format;
use Carbon\Carbon;
use Exception;
use Flarum\Console\AbstractCommand;
use Flarum\Foundation\{Config, Paths};
use Spatie\DbDumper\Compressors\{Bzip2Compressor, GzipCompressor};
use Spatie\DbDumper\Databases\MySql;
use Spatie\DbDumper\Exceptions\CannotSetParameter;
use Symfony\Component\Console\Input\{InputArgument, InputOption};

class Create extends AbstractCommand
{
    private const COMPRESSORS = [
        'gz' => GzipCompressor::class,
        'bz2' => Bzip2Compressor::class,
    ];

    /**
     * Inspired by WP-CLI's DB_Command.
     * @see https://github.com/wp-cli/db-command/blob/e9c4e8ab61e99f7fa7e31e584c2b2b5d54d071db/src/DB_Command.php#L1937
     */
    private const ALLOWED_MYSQLDUMP_OPTIONS = [
        'add-drop-table',
        'add-locks',
        'allow-keywords',
        'apply-slave-statements',
        'bind-address',
        'character-sets-dir',
        'comments',
        'compatible',
        'compact',
        'complete-insert',
        'create-options',
        'databases',
        'debug',
        'debug-check',
        'debug-info',
        'default-character-set',
        'delete-master-logs',
        'disable-keys',
        'dump-slave',
        'events',
        'extended-insert',
        'fields-terminated-by',
        'fields-enclosed-by',
        'fields-optionally-enclosed-by',
        'fields-escaped-by',
        'flush-logs',
        'flush-privileges',
        'force',
        'hex-blob',
        'host',
        'insert-ignore',
        'lines-terminated-by',
        'lock-all-tables',
        'lock-tables',
        'log-error',
        'master-data',
        'max-allowed-packet',
        'net-buffer-length',
        'no-autocommit',
        'no-create-db',
        'no-create-info',
        'no-set-names',
        'no-tablespaces',
        'opt',
        'order-by-primary',
        'port',
        'protocol',
        'quick',
        'quote-names',
        'replace',
        'routines',
        'set-charset',
        'single-transaction',
        'dump-date',
        'skip-comments',
        'skip-opt',
        'socket',
        'ssl',
        'ssl-ca',
        'ssl-capath',
        'ssl-cert',
        'ssl-cipher',
        'ssl-key',
        'ssl-verify-server-cert',
        'tab',
        'triggers',
        'tz-utc',
        'user',
        'where',
        'xml',
    ];

    public function __construct(protected Config $config, protected Paths $paths)
    {
        parent::__construct('snapshot:create');
    }

    protected function configure(): void
    {
        $command = $this
            ->setDescription('Create a snapshot of the database')
            ->addArgument(
                'path',
                InputArgument::OPTIONAL,
                'Path where to store the snapshot file',
            )
            ->addOption(
                'compress',
                null,
                InputOption::VALUE_REQUIRED,
                'Compression type (gz, bz2)',
            )
            ->addOption(
                'binary-path',
                null,
                InputOption::VALUE_REQUIRED,
                'Custom location for the mysqldump binary',
            )
            ->addOption(
                'include-tables',
                null,
                InputOption::VALUE_REQUIRED,
                'Comma separated list of tables to include in the snapshot',
            )
            ->addOption(
                'exclude-tables',
                null,
                InputOption::VALUE_REQUIRED,
                'Comma separated list of tables to exclude from the snapshot',
            )
            ->addOption(
                'skip-structure',
                null,
                InputOption::VALUE_NONE,
                'Skip table structure (CREATE TABLE statements)',
            )
            ->addOption(
                'no-data',
                null,
                InputOption::VALUE_NONE,
                'Do not write row data into the snapshot',
            )
            ->addOption(
                'skip-auto-increment',
                null,
                InputOption::VALUE_NONE,
                'Skip AUTO_INCREMENT values from the snapshot',
            )
            ->addOption(
                'no-column-statistics',
                null,
                InputOption::VALUE_NONE,
                'Do not use column statistics (for MySQL 8 compatibility)',
            );

        foreach (self::ALLOWED_MYSQLDUMP_OPTIONS as $option) {
            $command->addOption(
                $option,
                null,
                InputOption::VALUE_OPTIONAL,
                "Pass --$option to mysqldump",
            );
        }
    }

    /**
     * @throws CannotSetParameter
     */
    protected function fire(): int
    {
        $dbConfig = $this->config['database'];
        $dumper = MySql::create()
            ->setHost($dbConfig['host'])
            ->setDbName($dbConfig['database'])
            ->setPort($dbConfig['port'] ?? 3306)
            ->setUserName($dbConfig['username'])
            ->setPassword($dbConfig['password']);

        $path = $this->input->getArgument('path');
        if (empty($path)) {
            $path = $this->paths->storage.'/snapshots/snapshot-'.Carbon::now()->format('Y-m-d-His').'.sql';
        }

        $compression = $this->input->getOption('compress');
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        // If compression is specified and different from path extension
        if ($compression && $extension !== $compression) {
            $path .= '.'.$compression;
        } elseif (! $extension) {
            $path .= '.sql';
        }

        $finalExtension = pathinfo($path, PATHINFO_EXTENSION);
        if (isset(self::COMPRESSORS[$finalExtension])) {
            $compressorClass = self::COMPRESSORS[$finalExtension];
            $dumper->useCompressor(new $compressorClass());
        }

        $dir = dirname($path);
        if (! file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        if ($binaryPath = $this->input->getOption('binary-path')) {
            $dumper->setDumpBinaryPath($binaryPath);
        }

        if ($includeTables = $this->input->getOption('include-tables')) {
            $dumper->includeTables(explode(',', $includeTables));
        }

        if ($excludeTables = $this->input->getOption('exclude-tables')) {
            $dumper->excludeTables(explode(',', $excludeTables));
        }

        if ($this->input->getOption('skip-structure')) {
            $dumper->doNotCreateTables();
        }

        if ($this->input->getOption('no-data')) {
            $dumper->doNotDumpData();
        }

        if ($this->input->getOption('skip-auto-increment')) {
            $dumper->skipAutoIncrement();
        }

        if ($this->input->getOption('no-column-statistics')) {
            $dumper->doNotUseColumnStatistics();
        }

        foreach (self::ALLOWED_MYSQLDUMP_OPTIONS as $option) {
            $value = $this->input->getOption($option);
            if ($value !== null) {
                if ($value === true || $value === '') {
                    $dumper->addExtraOption("--$option");
                } else {
                    $dumper->addExtraOption("--$option=".$value);
                }
            }
        }

        try {
            $dumper->dumpToFile($path);
            $fullPath = realpath($path);
            $filesize = Format::humanReadableSize(filesize($fullPath));
            $this->info("Snapshot created successfully to: $fullPath ($filesize)");
        } catch (Exception $e) {
            $this->error('Failed to create snapshot: '.$e->getMessage());

            return 1;
        }

        return 0;
    }
}
