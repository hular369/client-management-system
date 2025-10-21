<?php

namespace App\Jobs;

use App\Models\ImportSession;
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
            $duplicateCount = 0;

            foreach ($records as $offset => $record) {
                $rowNumber = $this->startRow + $offset + 2;

                try {
                    $client = $clientService->createClient($record);
                    $imported++;

                    // Count if this was marked as duplicate
                    if ($client->is_duplicate) {
                        $duplicateCount++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Row {$rowNumber}: " . $e->getMessage();
                }
            }

            // CRITICAL FIX: Update the ImportSession with progress
            $this->updateImportSession($imported, $duplicateCount, $errors);

            // Store results in cache for aggregation (optional, for batch completion)
            $results = [
                'imported' => $imported,
                'errors' => $errors,
                'duplicates' => $duplicateCount,
            ];

            cache()->put("import_{$this->importSessionId}_chunk_{$this->startRow}", $results, 3600);

            Log::info("Completed chunk for session: {$this->importSessionId}, start row: {$this->startRow}, imported: {$imported}, duplicates: {$duplicateCount}, errors: " . count($errors));
        } catch (\Exception $e) {
            Log::error("Error processing chunk for session: {$this->importSessionId}, start row: {$this->startRow}: " . $e->getMessage());

            // update session on error
            $this->updateImportSessionOnError($e->getMessage());
        }
    }

    /**
     * Update import session with progress
     */
    private function updateImportSession(int $imported, int $duplicateCount, array $errors): void
    {
        try {
            $session = \App\Models\ImportSession::where('session_id', $this->importSessionId)->first();

            if ($session) {
                $session->update([
                    'processed_rows' => $session->processed_rows + $this->chunkSize,
                    'imported_count' => $session->imported_count + $imported,
                    'duplicate_count' => $session->duplicate_count + $duplicateCount,
                    'error_count' => $session->error_count + count($errors),
                ]);

                // Append errors if any
                if (!empty($errors)) {
                    $existingErrors = $session->errors ?? [];
                    $session->update([
                        'errors' => array_merge($existingErrors, $errors)
                    ]);
                }

                Log::info("Updated import session {$this->importSessionId}: +{$imported} imported, +{$duplicateCount} duplicates, +" . count($errors) . " errors");
            }
        } catch (\Exception $e) {
            Log::error("Failed to update import session {$this->importSessionId}: " . $e->getMessage());
        }
    }

    /**
     * Update import session when job fails
     */
    private function updateImportSessionOnError(string $errorMessage): void
    {
        try {
            $session = ImportSession::where('session_id', $this->importSessionId)->first();

            if ($session) {
                $existingErrors = $session->errors ?? [];
                $session->update([
                    'errors' => array_merge($existingErrors, ["Chunk {$this->startRow}: " . $errorMessage]),
                    'error_count' => $session->error_count + 1,
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Failed to update import session on error {$this->importSessionId}: " . $e->getMessage());
        }
    }
}
