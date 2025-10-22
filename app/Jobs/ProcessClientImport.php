<?php

/**
 * ProcessClientImport
 *
 * Job that processes a chunk of a client CSV import.
 *
 * This queued job reads a portion of a CSV stored on the "temp_imports" disk and attempts to
 * create clients via the injected ClientService. It is designed to run as part of a batch of
 * chunked import jobs, supports early cancellation if the batch is cancelled, and records
 * progress and errors to a central ImportSession model.
 *
 * Responsibilities:
 * - Verify the import file exists on the "temp_imports" disk and open it with League\Csv\Reader.
 * - Treat the first CSV row as headers (header offset 0).
 * - Process rows beginning at $startRow and limit to $chunkSize using League\Csv\Statement.
 * - For each row, call ClientService::createClient($record) and track:
 *     - number of successfully imported clients,
 *     - number of duplicates (as indicated by the created client),
 *     - per-row exceptions collected as errors.
 * - Update the associated ImportSession with processed_rows, imported_count, duplicate_count,
 *   error_count and append any error messages.
 * - Cache per-chunk results under a key like import_{session}_chunk_{startRow} for aggregation.
 * - Log progress, missing files, and any exceptions encountered; when exceptions occur, update
 *   the session with a chunk-level error entry.
 *
 * Class properties:
 * @var string $filePath        Path (relative to the "temp_imports" disk) to the CSV file.
 * @var int    $startRow       Zero-based start offset for rows this job should process.
 * @var int    $chunkSize      Maximum number of rows to process in this job.
 * @var string $importSessionId Identifier used to locate and update the ImportSession model.
 *
 * Important behavior and notes:
 * - The job checks batch cancellation via $this->batch()->cancelled() and exits early if cancelled.
 * - The row number reported in error messages accounts for CSV header and the start offset.
 * - updateImportSession and updateImportSessionOnError are used to persist progress and failures.
 * - Concurrency: updates to ImportSession are best-effort increments; if strict atomicity is
 *   required under heavy parallelism, consider database-level locking or atomic increments.
 * - Cached chunk results are stored for 1 hour (configurable) to allow aggregation after batch completion.
 *
 * Dependencies:
 * - App\Services\ClientService for creating clients from CSV records.
 * - App\Models\ImportSession for persisting import progress and errors.
 * - League\Csv\Reader and League\Csv\Statement for CSV parsing and chunking.
 *
 * Usage:
 * Dispatch the job with the CSV path, start row, chunk size and import session id. The job
 * is serializable and suitable for queued execution in a Laravel queue/batch environment.
 *
 * @package App\Jobs
 */


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

            // Update the ImportSession with progress
            $this->updateImportSession($imported, $duplicateCount, $errors);

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
