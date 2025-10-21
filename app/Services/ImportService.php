<?php

/**
 * ImportService
 *
 * Handles the lifecycle of CSV imports: validating and storing uploaded CSV files,
 * creating and managing import sessions, cleaning up temporary files, and reporting/canceling progress.
 *
 * Responsibilities
 * - validateAndStoreCsv(UploadedFile $file): stores the uploaded CSV to the 'temp_imports' disk with a unique name,
 *   verifies the file exists, checks that the CSV contains the required headers (company_name, email, phone_number),
 *   counts rows by iterating records and returns an array containing file metadata and a League\Csv\Reader instance.
 *   Throws \Exception on storage failure or invalid CSV format.
 *
 * - createImportSession(string $originalName, int $totalRows, string $filePath): creates an ImportSession record
 *   initialized with a generated session_id, status 'processing' and the provided metadata.
 *
 * - cleanupFile(string $filePath): removes the temporary file from the configured 'temp_imports' disk.
 *
 * - getImportProgress(string $sessionId): returns null when the session is not found; otherwise returns an array
 *   with status, rounded progress percentage, imported/duplicate/error counts, total and processed rows, and errors.
 *
 * - cancelImport(string $sessionId): sets the session status to 'cancelled' and returns true on success or false if not found.
 *
 * Notes & recommendations
 * - Required CSV headers: company_name, email, phone_number (header matching is performed via League\Csv with setHeaderOffset(0)).
 * - validateAndStoreCsv returns a League\Csv\Reader instance which holds a reference to the file; avoid serializing it.
 * - Counting rows iterates all records; for very large files consider streaming or batching to reduce memory/time impact.
 * - Currently throws generic \Exception for errors; consider introducing domain-specific exceptions for clearer error handling.
 * - Ensure the 'temp_imports' filesystem disk is configured, writable, and cleaned up (cleanupFile) after import completes or fails.
 *
 * Example (high level)
 *   $meta = $importService->validateAndStoreCsv($uploadedFile);
 *   $session = $importService->createImportSession($meta['original_name'], $meta['total_rows'], $meta['file_path']);
 *
 * @package App\Services
 */


namespace App\Services;

use App\Models\ImportSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;

class ImportService
{
    /**
     * Validate and store CSV file
     */
    public function validateAndStoreCsv(UploadedFile $file): array
    {
        $originalName = $file->getClientOriginalName();
        $filePath = $file->storeAs('', uniqid() . '_' . $originalName, 'temp_imports');
        $fullPath = Storage::disk('temp_imports')->path($filePath);

        if (!file_exists($fullPath)) {
            throw new \Exception('Failed to store uploaded file');
        }

        // Validate CSV structure
        $csv = Reader::createFromPath($fullPath, 'r');
        $csv->setHeaderOffset(0);

        $headers = $csv->getHeader();
        $requiredHeaders = ['company_name', 'email', 'phone_number'];

        if (count(array_intersect($requiredHeaders, $headers)) !== count($requiredHeaders)) {
            Storage::disk('temp_imports')->delete($filePath);
            throw new \Exception('Invalid CSV format. Required columns: company_name, email, phone_number');
        }

        // Count rows
        $records = $csv->getRecords();
        $totalRows = 0;
        foreach ($records as $record) {
            $totalRows++;
        }

        return [
            'file_path' => $filePath,
            'full_path' => $fullPath,
            'original_name' => $originalName,
            'total_rows' => $totalRows,
            'csv_reader' => $csv,
        ];
    }

    /**
     * Create import session
     */
    public function createImportSession(string $originalName, int $totalRows, string $filePath): ImportSession
    {
        return ImportSession::create([
            'session_id' => uniqid(),
            'original_filename' => $originalName,
            'total_rows' => $totalRows,
            'file_path' => $filePath,
            'status' => 'processing',
        ]);
    }

    /**
     * Clean up temporary file
     */
    public function cleanupFile(string $filePath): void
    {
        Storage::disk('temp_imports')->delete($filePath);
    }

    /**
     * Get import progress
     */
    public function getImportProgress(string $sessionId): ?array
    {
        $session = ImportSession::where('session_id', $sessionId)->first();

        if (!$session) {
            return null;
        }

        $progress = $session->total_rows > 0 ?
            round(($session->processed_rows / $session->total_rows) * 100) : 0;

        return [
            'status' => $session->status,
            'progress' => $progress,
            'imported_count' => $session->imported_count,
            'duplicate_count' => $session->duplicate_count,
            'error_count' => $session->error_count,
            'total_rows' => $session->total_rows,
            'processed_rows' => $session->processed_rows,
            'errors' => $session->errors ?? [],
        ];
    }

    /**
     * Cancel import session
     */
    public function cancelImport(string $sessionId): bool
    {
        $session = ImportSession::where('session_id', $sessionId)->first();

        if (!$session) {
            return false;
        }

        $session->update(['status' => 'cancelled']);
        return true;
    }
}
