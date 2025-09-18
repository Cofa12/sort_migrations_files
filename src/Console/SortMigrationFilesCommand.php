<?php

namespace Cofa\SortMigrationsFiles\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

class SortMigrationFilesCommand extends Command
{
    public $signature = 'migrations:sort';
    public $description = 'Sort all migration files based on foreign key dependencies';

    public function handle()
    {
        $migrationFiles = File::allFiles(database_path('migrations'));
        $migrationFiles = array_values($migrationFiles);

        $database = DB::getDatabaseName();

        $foreignKeys = DB::select('
            SELECT TABLE_NAME, REFERENCED_TABLE_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE REFERENCED_TABLE_SCHEMA = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ', [$database]);

        $dependencies = [];
        foreach ($foreignKeys as $fk) {
            $dependencies[$fk->TABLE_NAME][] = $fk->REFERENCED_TABLE_NAME;
        }
        foreach ($dependencies as $dependency => $tables) {
            $childTable = collect($migrationFiles)->first(function ($file) use ($dependency) {
                return str_contains($file->getFilename(), 'create_'.$dependency);
            });


            foreach ($tables as $table) {
                $baseTable = collect($migrationFiles)->first(function ($file) use ($table) {
                    return str_contains($file->getFilename(), 'create_' . $table);
                });
                if ($childTable->getRelativePathname()<$baseTable->getRelativePathname()) {
                    $childPath = $childTable->getPathname();
                    $basePath  = $baseTable->getPathname();

                    [$childTimestamp, $childRest] = explode('_', $childTable->getFilename(), 2);
                    [$baseTimestamp, $baseRest]   = explode('_', $baseTable->getFilename(), 2);

                    $newChildName = $baseTimestamp . '_' . $childRest;
                    $newBaseName  = $childTimestamp . '_' . $baseRest;

                    $newChildPath = $childTable->getPath() . DIRECTORY_SEPARATOR . $newChildName;
                    $newBasePath  = $baseTable->getPath() . DIRECTORY_SEPARATOR . $newBaseName;

                    rename($childPath, $newChildPath);
                    rename($basePath, $newBasePath);
                }
            }
        }
    }

}
