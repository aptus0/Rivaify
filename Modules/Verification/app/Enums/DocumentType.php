<?php

namespace Modules\Verification\Enums;

enum DocumentType: string
{
    case TaxCertificate = 'tax_certificate';
    case Identity = 'identity';
    case SignatureCircular = 'signature_circular';
    case BusinessLicense = 'business_license';
    case Other = 'other';
}
