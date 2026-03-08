<?php

namespace App\Enums;

enum DocumentType: string
{
    case MedicalCertificate = 'medical_certificate';
    case IdentityDoc = 'identity_doc';
    case PrivacyConsent = 'privacy_consent';
    case Other = 'other';
}
