<?php
/**
 * @file plugins/generic/academicCertificate/classes/AcademicCertificateMailable.php
 *
 * Copyright (c) 2024
 * Distributed under the GNU GPL v3.
 *
 * @class AcademicCertificateMailable
 * @ingroup plugins_generic_academicCertificate
 *
 * @brief Mailable class for OJS 3.5+ email system
 *
 * Registers the certificate notification email with OJS's Laravel-based
 * mail system. On OJS 3.3/3.4, the legacy MailTemplate is used instead.
 */

namespace APP\plugins\generic\academicCertificate\classes;

use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;

class AcademicCertificateMailable extends Mailable {
    use Configurable;

    protected static ?string $name = 'plugins.generic.academicCertificate.email.name';
    protected static ?string $description = 'plugins.generic.academicCertificate.email.description';
    protected static ?string $emailTemplateKey = 'REVIEWER_CERTIFICATE_AVAILABLE';
}
