<?php

/**
 * ClientService
 *
 * Service responsible for managing Client records, including:
 *  - Retrieving clients with search, filter and pagination support.
 *  - Creating clients with duplicate detection and duplicate-group management.
 *  - Deleting clients while maintaining correct duplicate-group status.
 *  - Retrieving grouped duplicate information.
 *  - Formatting client collections for CSV export.
 *
 * Behavior & contracts:
 *  - getClients(array $filters = []): LengthAwarePaginator
 *      - Supports a 'search' key (matches company_name, email, phone_number).
 *      - Supports a 'filter' key with values 'all'|'duplicates'|'unique'.
 *      - Supports a 'per_page' key to control pagination size.
 *      - Results are ordered by created_at desc.
 *
 *  - createClient(array $data): Client
 *      - Expects at minimum company_name, email, phone_number in $data.
 *      - Uses Client::generateDuplicateHash(...) to compute duplicate_group_hash.
 *      - If other records share the same duplicate_group_hash, the new record
 *        and the group are marked as duplicates (is_duplicate = true).
 *      - Trims and normalizes incoming fields (email downcased).
 *
 *  - deleteClient(int $clientId): bool
 *      - Finds the client or fails (findOrFail).
 *      - If the client belonged to a duplicate group, adjusts the remaining
 *        group's is_duplicate flag when appropriate (when only one remains).
 *      - Returns boolean result of the delete operation.
 *
 *  - getDuplicateGroups(): Collection
 *      - Returns groups where duplicate_count > 1.
 *      - Each item contains: hash, count and clients (id, company_name, email, phone_number, duplicate_group_hash).
 *      - Relies on Client model scopes/relationships (duplicates(), clients).
 *
 *  - formatForExport(Collection $clients): string
 *      - Produces a CSV string with header:
 *        company_name,email,phone_number,is_duplicate,duplicate_group_hash
 *      - Properly escapes quotes in text fields and emits is_duplicate as 'true'|'false'.
 *
 * Notes:
 *  - This service delegates duplicate detection logic and scopes to the Client model
 *    (e.g. generateDuplicateHash(), duplicates(), unique()).
 *  - Consumers should validate input data before calling createClient.
 *  - deleteClient will throw an exception if the client id does not exist.
 *
 * @package App\Services
 */

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ClientService
{
    /**
     * Get clients with filtering and pagination
     */
    public function getClients(array $filters = []): LengthAwarePaginator
    {
        $query = Client::query();

        // Apply filters
        if (isset($filters['search']) && $filters['search']) {
            $query->where(function ($q) use ($filters) {
                $q->where('company_name', 'like', "%{$filters['search']}%")
                    ->orWhere('email', 'like', "%{$filters['search']}%")
                    ->orWhere('phone_number', 'like', "%{$filters['search']}%");
            });
        }

        if (isset($filters['filter']) && $filters['filter'] !== 'all') {
            if ($filters['filter'] === 'duplicates') {
                $query->duplicates();
            } elseif ($filters['filter'] === 'unique') {
                $query->unique();
            }
        }

        return $query->orderBy('created_at', 'desc')->paginate($filters['per_page']);
    }

    /**
     * Create a new client
     */
    public function createClient(array $data): Client
    {
        // Generate duplicate hash
        $duplicateHash = Client::generateDuplicateHash(
            $data['company_name'],
            $data['email'],
            $data['phone_number']
        );

        // Check for existing duplicates
        $existingClients = Client::where('duplicate_group_hash', $duplicateHash)->get();
        $isDuplicate = $existingClients->count() > 0;

        $clientData = [
            'company_name' => trim($data['company_name']),
            'email' => strtolower(trim($data['email'])),
            'phone_number' => trim($data['phone_number']),
            'duplicate_group_hash' => $duplicateHash,
            'is_duplicate' => $isDuplicate,
        ];

        $client = Client::create($clientData);

        // If this is a duplicate, mark all records in this group as duplicates
        if ($isDuplicate) {
            Client::where('duplicate_group_hash', $duplicateHash)
                ->update(['is_duplicate' => true]);
        }

        return $client;
    }

    /**
     * Delete a client
     */
    public function deleteClient(int $clientId): bool
    {
        $client = Client::findOrFail($clientId);

        // If this was the last duplicate in a group, update the group status
        if ($client->is_duplicate) {
            $remainingDuplicates = Client::where('duplicate_group_hash', $client->duplicate_group_hash)
                ->where('id', '!=', $clientId)
                ->count();

            if ($remainingDuplicates === 1) {
                // Only one duplicate remains, mark it as unique
                Client::where('duplicate_group_hash', $client->duplicate_group_hash)
                    ->update(['is_duplicate' => false]);
            }
        }

        return $client->delete();
    }

    /**
     * Get duplicate groups
     */
    public function getDuplicateGroups(): Collection
    {
        return Client::duplicates()
            ->select('duplicate_group_hash')
            ->selectRaw('COUNT(*) as duplicate_count')
            ->groupBy('duplicate_group_hash')
            ->having('duplicate_count', '>', 1)
            ->with(['clients' => function ($query) {
                $query->select('id', 'company_name', 'email', 'phone_number', 'duplicate_group_hash');
            }])
            ->get()
            ->map(function ($group) {
                return [
                    'hash' => $group->duplicate_group_hash,
                    'count' => $group->duplicate_count,
                    'clients' => $group->clients
                ];
            });
    }

    /**
     * Format clients for CSV export
     */
    public function formatForExport(Collection $clients): string
    {
        $csvContent = "company_name,email,phone_number,imported_at\n";

        foreach ($clients as $client) {
            $csvContent .= implode(',', [
                '"' . str_replace('"', '""', $client->company_name) . '"',
                '"' . str_replace('"', '""', $client->email) . '"',
                '"' . str_replace('"', '""', $client->phone_number) . '"',
                '"' . str_replace('"', '""', $client->created_at) . '"',

            ]) . "\n";
        }

        return $csvContent;
    }
}
