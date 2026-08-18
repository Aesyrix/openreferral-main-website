<?php

namespace App\Services;

use App\Models\Lead;
use Barryvdh\Dompdf\Facade\Pdf;

class LeadPacketPdfService
{
    public function generateLeadPacket(Lead $lead)
    {
        $lead->load(['assignedAgent', 'assignedBy', 'updates.user', 'notes.user', 'documents']);

        $pdf = Pdf::loadView('pdf.lead_packet', [
            'lead' => $lead,
            'generatedAt' => now()->format('F j, Y - g:i A'),
        ]);

        return $pdf->download("OpenReferral_Packet_{$lead->public_ref}.pdf");
    }
}
