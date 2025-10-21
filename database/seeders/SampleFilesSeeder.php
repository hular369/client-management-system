<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class SampleFilesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sampleFiles = [
            'small_clients.csv',
            'medium_clients.csv', 
            'with_duplicates.csv',
            'with_errors.csv',
        ];

        $sourceDir = database_path('seeders/sample_data');
        $targetDir = 'sample_imports';

        // Create target directory if it doesn't exist
        if (!Storage::disk('private')->exists($targetDir)) {
            Storage::disk('private')->makeDirectory($targetDir);
        }

        // Copy sample files to storage
        foreach ($sampleFiles as $filename) {
            $sourcePath = $sourceDir . '/' . $filename;
            $targetPath = $targetDir . '/' . $filename;

            if (file_exists($sourcePath)) {
                $content = file_get_contents($sourcePath);
                Storage::disk('private')->put($targetPath, $content);
                $this->command->info("Copied sample file: {$filename}");
            }
        }

        $this->command->info('Sample CSV files have been copied to storage/app/private/sample_imports/');
        $this->command->info('You can use these files for testing the import functionality.');
    }
}