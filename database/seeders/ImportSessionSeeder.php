<?php

namespace Database\Seeders;

use App\Models\ImportSession;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ImportSessionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing sessions
        ImportSession::truncate();

        // Create completed sessions
        for ($i = 0; $i < 5; $i++) {
            $totalRows = rand(100, 1000);
            $importedCount = $totalRows - rand(5, 50); // Some errors
            $duplicateCount = rand(10, 100);
            
            ImportSession::create([
                'session_id' => uniqid(),
                'original_filename' => 'client_import_' . ($i + 1) . '.csv',
                'total_rows' => $totalRows,
                'processed_rows' => $totalRows,
                'imported_count' => $importedCount,
                'duplicate_count' => $duplicateCount,
                'error_count' => $totalRows - $importedCount,
                'errors' => $this->generateSampleErrors($totalRows - $importedCount), // No JSON encode needed
                'status' => 'completed',
                'file_path' => 'temp_imports/sample_' . uniqid() . '.csv',
                'created_at' => now()->subDays(rand(1, 30)),
                'updated_at' => now()->subDays(rand(0, 29)),
            ]);
        }

        // Create a failed session
        ImportSession::create([
            'session_id' => uniqid(),
            'original_filename' => 'failed_import.csv',
            'total_rows' => 500,
            'processed_rows' => 150,
            'imported_count' => 120,
            'duplicate_count' => 20,
            'error_count' => 30,
            'errors' => ['Row 45: Invalid email format', 'Row 89: Missing company name', 'Row 156: Server connection timeout'],
            'status' => 'failed',
            'file_path' => 'temp_imports/failed_' . uniqid() . '.csv',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        // Create a processing session
        ImportSession::create([
            'session_id' => uniqid(),
            'original_filename' => 'current_import.csv',
            'total_rows' => 2000,
            'processed_rows' => 750,
            'imported_count' => 680,
            'duplicate_count' => 45,
            'error_count' => 25,
            'errors' => $this->generateSampleErrors(25),
            'status' => 'processing',
            'file_path' => 'temp_imports/current_' . uniqid() . '.csv',
            'created_at' => now()->subMinutes(30),
            'updated_at' => now()->subMinutes(5),
        ]);

        $this->command->info('Seeded import sessions with various statuses.');
    }

    /**
     * Generate sample error messages
     */
    private function generateSampleErrors(int $count): array
    {
        $errorTemplates = [
            'Row {row}: Missing required fields',
            'Row {row}: Invalid email format',
            'Row {row}: Phone number format incorrect',
            'Row {row}: Company name too short',
            'Row {row}: Email domain not valid',
            'Row {row}: Special characters not allowed in company name',
        ];

        $errors = [];
        for ($i = 0; $i < $count; $i++) {
            $template = $errorTemplates[array_rand($errorTemplates)];
            $row = rand(2, 1000); // Row numbers starting from 2 (after header)
            $errors[] = str_replace('{row}', $row, $template);
        }

        return $errors;
    }
}