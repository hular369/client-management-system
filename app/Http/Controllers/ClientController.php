<?php

/**
 * Class ClientController
 *
 * Controller responsible for managing clients and handling CSV import/export workflows.
 *
 * Main responsibilities:
 * - Accept and validate CSV uploads, create import sessions and dispatch chunked background jobs
 *   (uses Bus::batch and ProcessClientImport jobs) to process large files without blocking requests.
 * - Provide endpoints to query import progress and cancel an ongoing import.
 * - List clients with pagination, filtering (all, duplicates, unique) and search support.
 * - Export client datasets to CSV (returns filename and base64-encoded content).
 * - Retrieve duplicate groups and delete individual clients.
 *
 * Behavioural notes:
 * - ImportService is used for file validation, storage, session lifecycle management, progress/cancellation
 *   and cleanup of temporary files.
 * - ClientService is used for client retrieval, formatting for export, duplicate detection and deletion.
 * - Import processing is chunked (default chunk size: 1000 rows). An import session is marked completed
 *   or failed in batch callbacks; uploaded files are cleaned up in both success and failure paths.
 * - All public actions return JSON responses and log important events and errors.
 * - Methods perform request-level validation and convert errors into appropriate HTTP JSON responses
 *   (including 404 for missing sessions and 500 for unexpected failures).
 *
 * Injected dependencies:
 * @property App\Services\ClientService  $clientService
 * @property App\Services\ImportService  $importService
 *
 * Public endpoints (methods):
 * - import(ImportClientsRequest $request): Start CSV import in background. Returns session_id, total_rows, batch_id.
 * - importProgress(Request $request): Return progress for given session_id.
 * - cancelImport(Request $request): Cancel an import session by session_id.
 * - index(Request $request): Return paginated list of clients with optional filters and search.
 * - export(Request $request): Export clients to CSV according to filters; returns filename and base64 content.
 * - duplicateGroups(Request $request): Return groups of duplicate clients.
 * - destroy($id): Delete a client by id.
 *
 * Error handling & logging:
 * - Exceptions are caught at the controller level, logged, and returned as JSON error responses.
 * - Uploaded files are attempted to be cleaned up on both success and error paths when a file path is available.
 *
 * Implementation caveats:
 * - Chunk size is currently hard-coded; consider making configurable.
 * - Export returns CSV content base64-encoded (consumer must decode to download).
 */


namespace App\Http\Controllers;

use App\Http\Requests\ImportClientsRequest;
use App\Jobs\ProcessClientImport;
use App\Models\Client;
use App\Services\ClientService;
use App\Services\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class ClientController extends Controller
{
    public function __construct(
        private ClientService $clientService,
        private ImportService $importService
    ) {}

    /**
     * Import clients from CSV with batch processing
     */
    public function import(ImportClientsRequest $request): JsonResponse
    {
        try {
            $file = $request->file('csv_file');
            
            // Use ImportService to handle file validation and storage
            $fileInfo = $this->importService->validateAndStoreCsv($file);
            
            // Create import session
            $importSession = $this->importService->createImportSession(
                $fileInfo['original_name'],
                $fileInfo['total_rows'],
                $fileInfo['file_path']
            );

            Log::info("Created import session: {$importSession->session_id} for {$fileInfo['total_rows']} rows");

            // If no data rows, mark as completed immediately
            if ($fileInfo['total_rows'] === 0) {
                $importSession->markAsCompleted();
                $this->importService->cleanupFile($fileInfo['file_path']);
                
                return response()->json([
                    'success' => true,
                    'message' => 'CSV file processed - no data rows found',
                    'data' => [
                        'session_id' => $importSession->session_id,
                        'total_rows' => 0,
                        'batch_id' => null,
                    ]
                ]);
            }

            // Process in chunks
            $chunkSize = 1000;
            $jobs = [];
            
            for ($startRow = 0; $startRow < $fileInfo['total_rows']; $startRow += $chunkSize) {
                $jobs[] = new ProcessClientImport($fileInfo['file_path'], $startRow, $chunkSize, $importSession->session_id);
            }

            Log::info("Dispatching " . count($jobs) . " jobs for import session: {$importSession->session_id}");

            // Dispatch batch
            $batch = Bus::batch($jobs)
                ->then(function () use ($importSession, $fileInfo) {
                    $importSession->markAsCompleted();
                    $this->importService->cleanupFile($fileInfo['file_path']);
                    Log::info("Import session {$importSession->session_id} completed successfully");
                })
                ->catch(function ($batch, $e) use ($importSession, $fileInfo) {
                    $importSession->markAsFailed($e->getMessage());
                    $this->importService->cleanupFile($fileInfo['file_path']);
                    Log::error("Import session {$importSession->session_id} failed: " . $e->getMessage());
                })
                ->name("Client Import - {$importSession->session_id}")
                ->dispatch();

            return response()->json([
                'success' => true,
                'message' => 'CSV import started in background',
                'data' => [
                    'session_id' => $importSession->session_id,
                    'total_rows' => $fileInfo['total_rows'],
                    'batch_id' => $batch->id,
                ]
            ]);

        } catch (\Exception $e) {
            // Clean up file if error occurs
            if (isset($fileInfo['file_path'])) {
                $this->importService->cleanupFile($fileInfo['file_path']);
            }
            
            Log::error("Import failed: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check import progress
     */
    public function importProgress(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|string',
        ]);

        $progress = $this->importService->getImportProgress($request->session_id);

        if (!$progress) {
            return response()->json([
                'success' => false,
                'message' => 'Import session not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $progress
        ]);
    }

    /**
     * Cancel import
     */
    public function cancelImport(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|string',
        ]);

        $cancelled = $this->importService->cancelImport($request->session_id);

        if (!$cancelled) {
            return response()->json([
                'success' => false,
                'message' => 'Import session not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Import cancelled successfully'
        ]);
    }

    /**
     * Get all clients with pagination
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'per_page' => 'nullable|integer|min:1|max:100',
                'filter' => 'nullable|in:all,duplicates,unique',
                'search' => 'nullable|string|max:255',
            ]);

            $filters = [
                'per_page' => $request->get('per_page', 15),
                'filter' => $request->get('filter', 'all'),
                'search' => $request->get('search'),
            ];

            $clients = $this->clientService->getClients($filters);

            return response()->json([
                'success' => true,
                'data' => $clients
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch clients: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export clients to CSV
     */
    public function export(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'filter' => 'nullable|in:all,duplicates,unique',
                'duplicate_group' => 'nullable|string',
            ]);

            $filter = $request->get('filter', 'all');
            $duplicateGroup = $request->get('duplicate_group');

            $query = Client::query();

            if ($filter === 'duplicates') {
                $query->duplicates();
            } elseif ($filter === 'unique') {
                $query->unique();
            }

            if ($duplicateGroup) {
                $query->byDuplicateGroup($duplicateGroup);
            }

            $clients = $query->get();
            $csvContent = $this->clientService->formatForExport($clients);

            $filename = 'clients_export_' . date('Y-m-d_H-i-s') . '.csv';

            return response()->json([
                'success' => true,
                'data' => [
                    'filename' => $filename,
                    'content' => base64_encode($csvContent),
                    'count' => $clients->count(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get duplicate groups
     */
    public function duplicateGroups(Request $request): JsonResponse
    {
        try {
            $duplicateGroups = $this->clientService->getDuplicateGroups();

            return response()->json([
                'success' => true,
                'data' => $duplicateGroups
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch duplicate groups: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a client
     */
    public function destroy($id): JsonResponse
    {
        try {
            $this->clientService->deleteClient($id);

            return response()->json([
                'success' => true,
                'message' => 'Client deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete client: ' . $e->getMessage()
            ], 500);
        }
    }
}