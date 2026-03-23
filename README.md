# Flarum Database Snapshots

[![Latest Stable Version](https://img.shields.io/packagist/v/acpl/flarum-db-snapshots)](https://packagist.org/packages/acpl/flarum-db-snapshots) [![Total Downloads](https://img.shields.io/packagist/dt/acpl/flarum-db-snapshots.svg)](https://packagist.org/packages/acpl/flarum-db-snapshots/stats) [![GitHub Sponsors](https://img.shields.io/badge/Donate-%E2%9D%A4-%23db61a2.svg?&logo=github&logoColor=white&labelColor=181717)](https://github.com/android-com-pl/flarum-db-snapshots?sponsor=1)

This extension provides commands to quickly create and restore database snapshots for Flarum.

## Installation

```sh
composer require acpl/flarum-db-snapshots
```

## Create Snapshot

Create a database snapshot using the `snapshot:create` command.

Basic usage:
```sh
# Dump to storage/snapshots/snapshot-Y-m-d-His.sql
php flarum snapshot:create

# Dump to a specific path/file
php flarum snapshot:create /path/to/backup.sql
php flarum snapshot:create ../backups/forum.sql

# Dump with compression (based on extension)
php flarum snapshot:create /backups/dump.sql.gz   # gzip compression
php flarum snapshot:create /backups/dump.sql.bz2  # bzip2 compression

# Create a backup on a live site without locking tables (recommended for production)
php flarum snapshot:create --single-transaction --quick --lock-tables=false
```

### Options for Creation

- `--compress`: Compression type (`gz` or `bz2`).
- `--include-tables=table1,table2`: Include only specific tables in the snapshot.
- `--exclude-tables=table1,table2`: Exclude specific tables from the snapshot.
- `--skip-structure`: Skip table structure and do not include `CREATE TABLE` statements.
- `--no-data`: Dump schema only, without row data.
- `--skip-auto-increment`: Skip `AUTO_INCREMENT` values.
- `--no-column-statistics`: Disable column statistics.
- `--binary-path=/path/to/binary`: Custom path to the `mysqldump` binary.

Most standard `mysqldump` options are also supported, such as `--single-transaction`, `--quick`, and `--lock-tables`. See the [mysqldump documentation](https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html) for details.

## Load Snapshot

Restore a database from an existing snapshot using the `snapshot:load` command.

Basic usage:
```sh
# Restore from a standard SQL file
php flarum snapshot:load /path/to/backup.sql

# Restore directly from a compressed file (automatically decompressed on the fly)
php flarum snapshot:load /path/to/backup.sql.gz
php flarum snapshot:load /path/to/backup.sql.bz2
```
### Options for Loading

- `--drop-tables`: Drop all existing tables before loading the snapshot. Useful when restoring a full backup into an existing database to avoid conflicts.
- `--binary-path=/path/to/binary`: Custom path to the `mysql` binary.

## Requirements

- `mysql` and `mysqldump` binaries
- `gzip` for `.gz` snapshots
- `bzip2` for `.bz2` snapshots

## Links

- [Packagist](https://packagist.org/packages/acpl/flarum-db-snapshots)
- [GitHub](https://github.com/android-com-pl/flarum-db-snapshots)
- [Discuss](https://discuss.flarum.org/d/38880-flarum-database-snapshots)
