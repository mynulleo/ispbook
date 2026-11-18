<?php

namespace App\Http\Controllers\Admin\System;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

class DatabaseUpdateController extends Controller
{
    /**
     * Get pending migrations count and list
     */
    public function status()
    {
        try {
            $connection = DB::getDefaultConnection();
            $database = DB::connection()->getDatabaseName();

            $migrator = app('migrator');
            $migrator->setConnection($connection);

            if (! $migrator->repositoryExists()) {
                $migrator->getRepository()->createRepository();
            }

            $files = $migrator->getMigrationFiles(database_path('migrations'));
            $ran = $migrator->getRepository()->getRan();
            $pending = array_values(array_diff(array_keys($files), $ran));

            return response()->json([
                'success' => true,
                'has_pending' => count($pending) > 0,
                'pending_count' => count($pending),
                'pending_migrations' => $pending,
                'database' => $database,
                'connection' => $connection,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'has_pending' => false,
                'pending_count' => 0,
                'pending_migrations' => [],
                'error' => $e->getMessage(),
            ], 200);
        }
    }

    /**
     * Execute database migrations
     */
    public function update(Request $request)
    {
        try {
            $connection = DB::getDefaultConnection();

            Artisan::call('migrate', [
                '--database' => $connection,
                '--force' => true,
            ]);

            $output = Artisan::output();

            // Refresh status
            $migrator = app('migrator');
            $migrator->setConnection($connection);
            $files = $migrator->getMigrationFiles(database_path('migrations'));
            $ran = $migrator->getRepository()->getRan();
            $pending = array_values(array_diff(array_keys($files), $ran));

            return response()->json([
                'success' => true,
                'message' => 'Database migrations executed successfully!',
                'output' => $output,
                'has_pending' => count($pending) > 0,
                'pending_count' => count($pending),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Migration failed: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ], 200);
        }
    }
}

