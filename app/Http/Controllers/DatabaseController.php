<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Http;

class DatabaseController extends Controller
{
    public function checkDatabaseConnection()
    {
        try {
            // Attempt to connect to the database
            DB::connection()->getPdo();
            $dbName = DB::connection()->getDatabaseName();
            return response()->json([
                'status' => 'success',
                'message' => "Successfully connected to the database: {$dbName}"
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Could not connect to the database. Please check your configuration.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
