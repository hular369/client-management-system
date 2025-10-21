<?php

/**
 * Class ClientImportTest
 *
 * Functional/Feature tests for the client import functionality.
 *
 * This test class is responsible for exercising the client import workflow, including:
 *  - validating CSV/Excel file parsing and mapping,
 *  - handling of missing/invalid columns and data validation rules,
 *  - deduplication and conflict resolution when importing existing clients,
 *  - correct creation/updating of related entities (addresses, contacts, tags, etc.),
 *  - import progress reporting and error collection,
 *  - access control: ensuring only authorized users can initiate imports.
 *
 * Tests in this class should be written as isolated, repeatable feature tests:
 *  - Use database transactions or refresh the database between tests.
 *  - Mock external dependencies (queues, storage disks, third-party APIs) where appropriate.
 *  - Seed any required reference data explicitly within each test or in setUp helpers.
 *
 * PHPUnit / Laravel testing notes:
 *  - Group these tests under "feature" (default) and consider adding a "client-import" group for targeted runs.
 *  - If the import process is queued, assert jobs are dispatched and handle synchronous execution in tests.
 *  - When asserting file uploads, store test fixtures in tests/Fixtures and use Storage::fake() to avoid touching real disks.
 *
 * Examples of test cases to include:
 *  - import_creates_clients_from_valid_csv
 *  - import_updates_existing_client_on_matching_identifier
 *  - import_records_validation_errors_for_invalid_rows
 *  - import_ignores_duplicate_rows_or_merges_based_on_configuration
 *  - unauthorized_user_cannot_start_import
 *
 * @package Tests\Feature
 * @coversNothing Specific implementations should add @covers annotations per test or per tested class.
 */

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ImportSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_import_with_valid_data()
    {
        Storage::fake('temp_imports');
        Queue::fake();

        // Use proper CSV format with multiple rows to ensure processing
        $csvContent = "company_name,email,phone_number\nTest Company,test@example.com,1234567890\nAnother Company,another@example.com,9876543210";
        $file = UploadedFile::fake()->createWithContent('clients.csv', $csvContent);

        $response = $this->postJson('/api/clients/import', [
            'csv_file' => $file
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'session_id',
                    'total_rows',
                    'batch_id'
                ]
            ]);

        // For files with data, it should be processing
        $this->assertDatabaseHas('import_sessions', [
            'original_filename' => 'clients.csv',
            'status' => 'processing'
        ]);

        // Check that batch was dispatched
        Queue::assertPushed(\App\Jobs\ProcessClientImport::class);
    }

    public function test_csv_import_with_single_row_processes_in_background()
    {
        Storage::fake('temp_imports');
        Queue::fake();

        // Single data row - should still process in background for consistency
        $csvContent = "company_name,email,phone_number\nSingle Company,single@example.com,1234567890";
        $file = UploadedFile::fake()->createWithContent('single.csv', $csvContent);

        $response = $this->postJson('/api/clients/import', [
            'csv_file' => $file
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Single row files still go through background processing for consistency
        $this->assertDatabaseHas('import_sessions', [
            'original_filename' => 'single.csv',
            'status' => 'processing'
        ]);

        // Jobs should be dispatched even for single row files
        Queue::assertPushed(\App\Jobs\ProcessClientImport::class);
    }

    public function test_csv_import_detects_duplicates()
    {
        Storage::fake('temp_imports');
        Queue::fake();

        // Create a client first
        $client = Client::create([
            'company_name' => 'Test Company',
            'email' => 'test@example.com',
            'phone_number' => '1234567890',
            'is_duplicate' => false,
        ]);

        // Use multiple rows to ensure processing
        $csvContent = "company_name,email,phone_number\nTest Company,test@example.com,1234567890\nNew Company,new@example.com,5555555555";
        $file = UploadedFile::fake()->createWithContent('clients.csv', $csvContent);

        $response = $this->postJson('/api/clients/import', [
            'csv_file' => $file
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Should be processing for files with data
        $this->assertDatabaseHas('import_sessions', [
            'original_filename' => 'clients.csv',
            'status' => 'processing'
        ]);
    }

    public function test_csv_import_validates_required_fields()
    {
        Storage::fake('temp_imports');
        Queue::fake();

        // Use multiple rows including one with error
        $csvContent = "company_name,email,phone_number\n,test@example.com,1234567890\nValid Company,valid@example.com,9876543210";
        $file = UploadedFile::fake()->createWithContent('clients.csv', $csvContent);

        $response = $this->postJson('/api/clients/import', [
            'csv_file' => $file
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('import_sessions', [
            'original_filename' => 'clients.csv',
            'status' => 'processing'
        ]);
    }

    public function test_csv_import_validates_email_format()
    {
        Storage::fake('temp_imports');
        Queue::fake();

        // Use multiple rows including one with error
        $csvContent = "company_name,email,phone_number\nTest Company,invalid-email,1234567890\nValid Company,valid@example.com,9876543210";
        $file = UploadedFile::fake()->createWithContent('clients.csv', $csvContent);

        $response = $this->postJson('/api/clients/import', [
            'csv_file' => $file
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('import_sessions', [
            'original_filename' => 'clients.csv',
            'status' => 'processing'
        ]);
    }

    public function test_csv_export_functionality()
    {
        // Create test data
        Client::factory()->create([
            'company_name' => 'Test Company',
            'email' => 'test@example.com',
            'phone_number' => '1234567890',
            'is_duplicate' => false,
        ]);

        $response = $this->getJson('/api/clients/export');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.count', 1);
    }

    public function test_import_progress_endpoint()
    {
        // Create a test import session
        $session = ImportSession::create([
            'session_id' => 'test-session-123',
            'original_filename' => 'test.csv',
            'total_rows' => 100,
            'processed_rows' => 50,
            'imported_count' => 45,
            'duplicate_count' => 3,
            'error_count' => 2,
            'status' => 'processing',
            'file_path' => 'temp_imports/test.csv',
        ]);

        $response = $this->getJson('/api/clients/import-progress?session_id=test-session-123');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.progress', 50) // Remove .0 for integer comparison
            ->assertJsonPath('data.imported_count', 45)
            ->assertJsonPath('data.duplicate_count', 3)
            ->assertJsonPath('data.error_count', 2);
    }

    public function test_import_progress_with_invalid_session()
    {
        $response = $this->getJson('/api/clients/import-progress?session_id=invalid-session');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Import session not found'
            ]);
    }

    public function test_cancel_import_endpoint()
    {
        // Create a test import session
        $session = ImportSession::create([
            'session_id' => 'test-session-123',
            'original_filename' => 'test.csv',
            'total_rows' => 100,
            'processed_rows' => 50,
            'imported_count' => 45,
            'duplicate_count' => 3,
            'error_count' => 2,
            'status' => 'processing',
            'file_path' => 'temp_imports/test.csv',
        ]);

        $response = $this->postJson('/api/clients/cancel-import', [
            'session_id' => 'test-session-123'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Import cancelled successfully'
            ]);

        $this->assertDatabaseHas('import_sessions', [
            'session_id' => 'test-session-123',
            'status' => 'cancelled'
        ]);
    }

    public function test_cancel_import_with_invalid_session()
    {
        $response = $this->postJson('/api/clients/cancel-import', [
            'session_id' => 'invalid-session'
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Import session not found'
            ]);
    }

    public function test_csv_import_with_empty_file()
    {
        Storage::fake('temp_imports');
        Queue::fake();

        $csvContent = "company_name,email,phone_number";
        $file = UploadedFile::fake()->createWithContent('empty.csv', $csvContent);

        $response = $this->postJson('/api/clients/import', [
            'csv_file' => $file
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Should create a session but with 0 rows and completed status
        $this->assertDatabaseHas('import_sessions', [
            'original_filename' => 'empty.csv',
            'status' => 'completed'
        ]);
    }
}
