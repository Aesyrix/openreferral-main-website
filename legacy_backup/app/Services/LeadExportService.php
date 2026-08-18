<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class LeadExportService
{
    public function exportLeads(Collection $leads, string $format = 'csv'): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'Reference', 'Type', 'Status', 'First Name', 'Last Name', 'Phone', 'Email',
            'City', 'State', 'ZIP', 'Property Type', 'Assigned Agent', 'Created At'
        ];

        $sheet->fromArray([$headers], null, 'A1');

        $rowNum = 2;
        foreach ($leads as $lead) {
            $agentName = $lead->assignedAgent ? $lead->assignedAgent->name : 'Unassigned';
            $statusStr = is_object($lead->status) ? $lead->status->value : (string)$lead->status;
            $typeStr = is_object($lead->type) ? $lead->type->value : (string)$lead->type;

            $sheet->fromArray([[
                $lead->public_ref,
                ucfirst($typeStr),
                ucfirst($statusStr),
                $lead->first_name,
                $lead->last_name,
                $lead->phone,
                $lead->email,
                $lead->city,
                $lead->state,
                $lead->postal_code,
                $lead->property_type,
                $agentName,
                $lead->created_at ? $lead->created_at->format('Y-m-d H:i') : ''
            ]], null, "A{$rowNum}");
            $rowNum++;
        }

        $tempPath = storage_path('app/private/exports/export_' . time() . '.' . $format);
        if (!is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        if ($format === 'xlsx') {
            $writer = new Xlsx($spreadsheet);
        } else {
            $writer = new Csv($spreadsheet);
        }

        $writer->save($tempPath);

        return $tempPath;
    }
}
