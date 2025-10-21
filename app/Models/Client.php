<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_name',
        'email',
        'phone_number',
        'is_duplicate',
        'duplicate_group_hash',
    ];

    protected $casts = [
        'is_duplicate' => 'boolean',
    ];

    /**
     * Generate a unique hash for duplicate detection
     */
    public static function generateDuplicateHash($companyName, $email, $phoneNumber)
    {
        $data = implode('|', [
            strtolower(trim($companyName)),
            strtolower(trim($email)),
            preg_replace('/[^0-9]/', '', $phoneNumber)
        ]);

        return md5($data);
    }

    /**
     * Scope to get duplicates
     */
    public function scopeDuplicates($query)
    {
        return $query->where('is_duplicate', true);
    }

    /**
     * Scope to get unique records
     */
    public function scopeUnique($query)
    {
        return $query->where('is_duplicate', false);
    }

    /**
     * Scope to filter by duplicate group
     */
    public function scopeByDuplicateGroup($query, $hash)
    {
        return $query->where('duplicate_group_hash', $hash);
    }

    /**
     * Get all clients in the same duplicate group
     */
    public function clients()
    {
        return $this->hasMany(Client::class, 'duplicate_group_hash', 'duplicate_group_hash');
    }
}
