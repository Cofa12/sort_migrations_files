<?php

namespace Cofa\SortMigrationsFiles\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SortMigrationFilesCommand extends Command
{
    public $signature = 'migrations:sort';
    public $description = 'Sort all migration files based on foreign key dependencies';

    public function handle()
    {
        $migrationFiles = File::allFiles(database_path('migrations'));
        $migrationFiles = array_values($migrationFiles);

        $indexes = [];
        foreach ($migrationFiles as $i => $file) {
            $indexes[$file->getFilename()] = $i;
        }

        $database = DB::getDatabaseName();
        $foreignKeys = DB::select('
            SELECT TABLE_NAME, REFERENCED_TABLE_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE REFERENCED_TABLE_SCHEMA = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ', [$database]);

        $swapped = true;
        while ($swapped) {
            $swapped = false;

            foreach ($foreignKeys as $fk) {
                $child = $fk->TABLE_NAME;
                $parent = $fk->REFERENCED_TABLE_NAME;

                $childFile = collect($migrationFiles)->first(fn($f) => str_contains($f->getFilename(), 'create_' . $child));
                $parentFile = collect($migrationFiles)->first(fn($f) => str_contains($f->getFilename(), 'create_' . $parent));

                if (! $childFile || ! $parentFile) {
                    continue;
                }

                $childIndex = array_search($childFile, $migrationFiles, true);
                $parentIndex = array_search($parentFile, $migrationFiles, true);

                if ($childIndex < $parentIndex) {
                    [$migrationFiles[$childIndex], $migrationFiles[$parentIndex]] = [$migrationFiles[$parentIndex], $migrationFiles[$childIndex]];
                    $swapped = true;
                }
            }
        }

        foreach ($migrationFiles as $i => $file) {
            $timestamp = now()->addSeconds($i)->format('Y_m_d_His');
            $parts = explode('_', $file->getFilename());
            $baseName = implode('_', array_slice($parts, 4));
            $newFileName = $timestamp . '_' . $baseName;
            $newPath = $file->getPath() . DIRECTORY_SEPARATOR . $newFileName;

            if ($file->getPathname() !== $newPath) {
                rename($file->getPathname(), $newPath);
                $this->info("Renamed {$file->getFilename()} -> {$newFileName}");
            }
        }

        $this->info('Migrations reordered and renamed successfully!');
    }
}
