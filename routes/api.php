<?php

use App\Http\Controllers\ClientController;
use Illuminate\Support\Facades\Route;

Route::prefix('clients')->group(function () {
    // Import functionality
    Route::post('/import', [ClientController::class, 'import']);
    
    // Progress and cancellation
    Route::get('/import-progress', [ClientController::class, 'importProgress']);
    Route::post('/cancel-import', [ClientController::class, 'cancelImport']);
    
    // Export functionality
    Route::get('/export', [ClientController::class, 'export']);
    
    // Duplicate management
    Route::get('/duplicate-groups', [ClientController::class, 'duplicateGroups']);
    
    // Client CRUD
    Route::get('/', [ClientController::class, 'index']);
    Route::delete('/{id}', [ClientController::class, 'destroy']);
});