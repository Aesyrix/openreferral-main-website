<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Enums\LeadType;
use App\Models\Activity;
use App\Models\Import;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class LeadImportService
{
    public function processImport(Import $import): Import
    {
        $import->update(['status' => 'processing']);

        $fullPath = Storage::disk('local')->path($import->error_report_path ?? '');
        if (!file_exists($fullPath)) {
            $import->update(['status' => 'failed']);
            return $import;
        }

        try {
            $spreadsheet = IOFactory::load($fullPath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            if (count($rows) <= 1) {
                $import->update(['status' => 'completed', 'total_rows' => 0]);
                return $import;
            }

            $header = array_map('strtolower', array_map('trim', array_values($rows[1])));
            unset($rows[1]);

            $successCount = 0;
            $errorCount = 0;
            $errorRows = [];

            foreach ($rows as $rowIndex => $row) {
                $data = array_combine($header, array_slice(array_values($row), 0, count($header)));

                $firstName = trim($data['first_name'] ?? '');
                $lastName = trim($data['last_name'] ?? '');
                $type = strtolower(trim($data['type'] ?? 'buyer'));
                $city = trim($data['city'] ?? '');
                $phone = trim($data['phone'] ?? '');

                if (empty($firstName) || empty($lastName) || empty($city)) {
                    $errorCount++;
                    $errorRows[] = array_merge(array_values($row), ['Missing required fields: first_name, last_name, or city']);
                    continue;
                }

                $lead = Lead::create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'type' => in_array($type, ['buyer', 'seller', 'renter', 'landlord', 'investor']) ? $type : 'buyer',
                    'city' => $city,
                    'state' => trim($data['state'] ?? 'FL'),
                    'postal_code' => trim($data['postal_code'] ?? '33139'),
                    'email' => trim($data['email'] ?? null),
                    'phone' => !empty($phone) ? $phone : '+1 (555) 000-0000',
                    'source' => $import->format ?? 'csv',
                    'status' => LeadStatus::QUALIFIED,
                    'created_by_id' => $import->user_id,
                ]);

                Activity::create([
                    'subject_type' => Lead::class,
                    'subject_id' => $lead->id,
                    'user_id' => $import->user_id,
                    'type' => 'imported',
                    'new_status' => 'qualified',
                    'body' => "Lead imported via {$import->format} batch upload.",
                ]);

                $successCount++;
            }

            $import->update([
                'status' => 'completed',
                'total_rows' => count($rows),
                'success_count' => $successCount,
                'error_count' => $errorCount,
            ]);

        } catch (\Throwable $e) {
            $import->update(['status' => 'failed']);
        }

        return $import;
    }
}
