<?php

namespace App\Jobs;

use App\Services\ClientService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;
use League\Csv\Statement;

class ProcessClientImport implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $filePath;
    public $startRow;
    public $chunkSize;
    public $importSessionId;

    public function __construct($filePath, $startRow, $chunkSize, $importSessionId)
    {
        $this->filePath = $filePath;
        $this->startRow = $startRow;
        $this->chunkSize = $chunkSize;
        $this->importSessionId = $importSessionId;
    }

    public function handle(ClientService $clientService)
    {
        if ($this->batch()->cancelled()) {
            Log::info("Job cancelled for session: {$this->importSessionId}, start row: {$this->startRow}");
            return;
        }

        Log::info("Processing import chunk for session: {$this->importSessionId}, start row: {$this->startRow}");

        // Use the temp_imports disk
        $fullPath = Storage::disk('temp_imports')->path($this->filePath);
        
        if (!file_exists($fullPath)) {
            Log::error("Import file not found: {$fullPath}");
            Log::error("Job file path: {$this->filePath}");
            Log::error("Session ID: {$this->importSessionId}");
            return;
        }

        Log::info("File found at: {$fullPath}");

        try {
            $csv = Reader::createFromPath($fullPath, 'r');
            $csv->setHeaderOffset(0);
            
            $stmt = (new Statement())
                ->offset($this->startRow)
                ->limit($this->chunkSize);

            $records = $stmt->process($csv);
            
            $imported = 0;
            $errors = [];
            $duplicates = [];

            foreach ($records as $offset => $record) {
                $rowNumber = $this->startRow + $offset + 2;
                
                try {
                    // Use ClientService to create client
                    $clientService->createClient($record);
                    $imported++;

                } catch (\Exception $e) {
                    $errors[] = "Row {$rowNumber}: " . $e->getMessage();
                }
            }

            // Store results in cache for aggregation
            $results = [
                'imported' => $imported,
                'errors' => $errors,
                'duplicates' => 0, // This is now handled in ClientService
            ];

            cache()->put("import_{$this->importSessionId}_chunk_{$this->startRow}", $results, 3600);

            Log::info("Completed chunk for session: {$this->importSessionId}, start row: {$this->startRow}, imported: {$imported}, errors: " . count($errors));

        } catch (\Exception $e) {
            Log::error("Error processing chunk for session: {$this->importSessionId}, start row: {$this->startRow}: " . $e->getMessage());
        }
    }
}