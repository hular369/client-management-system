<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing clients
        Client::truncate();

        // Sample company names
        $companies = [
            'TechCorp Solutions', 'Global Innovations Inc', 'Digital Dynamics', 
            'WebWorks Agency', 'DataSystems Ltd', 'CloudNet Services',
            'SoftTech Enterprises', 'NetSolutions Group', 'InfoTech Partners',
            'SmartSystems Co', 'FutureTech Labs', 'CyberSecure Inc',
            'AppWorks Studio', 'CodeCrafters LLC', 'PixelPerfect Designs',
            'ByteSize Solutions', 'InnovateSoft', 'TechNova Systems',
            'WebCraft Studios', 'DataDriven Insights'
        ];

        // Sample domains for emails
        $domains = [
            'techcorp.com', 'globalinnovations.com', 'digitaldynamics.io',
            'webworks.dev', 'datasystems.com', 'cloudnet.services',
            'softtech.biz', 'netsolutions.group', 'infotech.partners',
            'smartsystems.co', 'futuretech.labs', 'cybersecure.io',
            'appworks.studio', 'codecrafters.dev', 'pixelperfect.design',
            'bytesize.solutions', 'innovatesoft.com', 'technova.systems',
            'webcraft.studio', 'datadriven.insights'
        ];

        $clients = [];
        $usedCombinations = [];

        // Create unique clients
        for ($i = 0; $i < 50; $i++) {
            $company = $companies[array_rand($companies)];
            $domain = $domains[array_rand($domains)];
            $email = strtolower(str_replace(' ', '.', $company)) . '@' . $domain;
            $phone = $this->generatePhoneNumber();
            
            // Ensure unique combination
            $key = $company . $email . $phone;
            if (in_array($key, $usedCombinations)) {
                continue;
            }
            
            $usedCombinations[] = $key;

            $clients[] = [
                'company_name' => $company,
                'email' => $email,
                'phone_number' => $phone,
                'is_duplicate' => false,
                'duplicate_group_hash' => null,
                'created_at' => now()->subDays(rand(1, 365)),
                'updated_at' => now(),
            ];
        }

        // Insert unique clients
        Client::insert($clients);

        // Create some duplicate records for testing duplicate detection
        $this->createDuplicates();

        $this->command->info('Seeded ' . count($clients) . ' unique clients with duplicates for testing.');
    }

    /**
     * Generate realistic phone numbers
     */
    private function generatePhoneNumber(): string
    {
        $formats = [
            '+1-###-###-####',
            '(###) ###-####',
            '###-###-####',
            '+44 ## #### ####',
            '+61 # #### ####',
        ];

        $format = $formats[array_rand($formats)];
        
        return preg_replace_callback('/#/', function() {
            return rand(0, 9);
        }, $format);
    }

    /**
     * Create duplicate records for testing
     */
    private function createDuplicates(): void
    {
        // Get some existing clients to duplicate
        $existingClients = Client::inRandomOrder()->limit(10)->get();

        $duplicates = [];

        foreach ($existingClients as $client) {
            // Create 2-3 duplicates for each selected client
            $duplicateCount = rand(2, 3);
            
            for ($i = 0; $i < $duplicateCount; $i++) {
                $duplicates[] = [
                    'company_name' => $client->company_name,
                    'email' => $client->email,
                    'phone_number' => $client->phone_number,
                    'is_duplicate' => true,
                    'duplicate_group_hash' => $client->duplicate_group_hash ?? Client::generateDuplicateHash(
                        $client->company_name,
                        $client->email,
                        $client->phone_number
                    ),
                    'created_at' => now()->subDays(rand(1, 30)),
                    'updated_at' => now(),
                ];
            }
        }

        // Insert duplicates
        Client::insert($duplicates);

        // Update original records to mark as duplicates too
        foreach ($existingClients as $client) {
            $client->update([
                'is_duplicate' => true,
                'duplicate_group_hash' => $client->duplicate_group_hash ?? Client::generateDuplicateHash(
                    $client->company_name,
                    $client->email,
                    $client->phone_number
                )
            ]);
        }

        $this->command->info('Created ' . count($duplicates) . ' duplicate records for testing.');
    }
}