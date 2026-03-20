<?php

namespace App\Enums;

enum ImportFormatTypeEnum: string
{
    case GENERIC_CSV = 'generic_csv';
    case BANK_CSV = 'bank_csv';
    case BANK_PDF = 'bank_pdf';
}
