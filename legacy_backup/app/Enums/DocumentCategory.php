<?php

namespace App\Enums;

enum DocumentCategory: string
{
    case LICENSE = 'license';
    case CONTRACT = 'contract';
    case ID_VERIFICATION = 'id_verification';
    case LEAD_ATTACHMENT = 'lead_attachment';
    case IMPORT = 'import';
    case EXPORT = 'export';
    case REPORT = 'report';
    case TICKET_ATTACHMENT = 'ticket_attachment';
    case OTHER = 'other';
}
