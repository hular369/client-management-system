<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'original_filename',
        'total_rows',
        'processed_rows',
        'imported_count',
        'duplicate_count',
        'error_count',
        'errors',
        'status',
        'file_path',
    ];

    protected $casts = [
        'errors' => 'array',
        'processed_rows' => 'integer',
        'imported_count' => 'integer',
        'duplicate_count' => 'integer',
        'error_count' => 'integer',
        'total_rows' => 'integer',
    ];

    public function markAsCompleted()
    {
        $this->update([
            'status' => 'completed',
            'processed_rows' => $this->total_rows,
        ]);
    }

    public function markAsFailed($error = null)
    {
        $this->update([
            'status' => 'failed',
            'errors' => $error ? array_merge($this->errors ?? [], [$error]) : $this->errors,
        ]);
    }

    public function updateProgress($imported, $duplicates, $errors)
    {
        $this->increment('processed_rows', 1000); // chunk size
        $this->increment('imported_count', $imported);
        $this->increment('duplicate_count', $duplicates);
        $this->increment('error_count', count($errors));
        
        if ($this->errors) {
            $this->update([
                'errors' => array_merge($this->errors, $errors)
            ]);
        } else {
            $this->update(['errors' => $errors]);
        }
    }
}