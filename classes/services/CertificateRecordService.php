<?php
/**
 * @file plugins/generic/academicCertificate/classes/services/CertificateRecordService.php
 *
 * Creates and prepares certificate records for all certificate types.
 */

namespace APP\plugins\generic\academicCertificate\classes\services;

use APP\plugins\generic\academicCertificate\classes\Certificate;

require_once(dirname(__FILE__) . '/CertificateNumberGenerator.php');

class CertificateRecordService {

    /**
     * Build a new reviewer certificate (not yet persisted).
     *
     * @param object $plugin AcademicCertificatePlugin
     * @param int $reviewerId
     * @param int $submissionId
     * @param int $reviewId
     * @param int $contextId
     * @param string|null $locale
     * @return Certificate
     */
    public static function createReviewerCertificate($plugin, $reviewerId, $submissionId, $reviewId, $contextId, $locale = null) {
        require_once(dirname(__DIR__) . '/Certificate.php');

        $certificate = new Certificate();
        $certificate->setCertificateType(Certificate::TYPE_REVIEWER);
        $certificate->setStatus(Certificate::STATUS_VALID);
        $certificate->setReviewerId((int) $reviewerId);
        $certificate->setUserId((int) $reviewerId);
        $certificate->setSubmissionId((int) $submissionId);
        $certificate->setReviewId((int) $reviewId);
        $certificate->setContextId((int) $contextId);
        $certificate->setDateIssued(self::getCurrentTimestamp());
        $certificate->setGeneratedAt(self::getCurrentTimestamp());
        $certificate->setCertificateCode(Certificate::generateCode());
        $certificate->setDownloadCount(0);

        if ($locale) {
            $certificate->setLocale($locale);
        }

        $generator = new CertificateNumberGenerator($plugin, (int) $contextId);
        $certificate->setCertificateNumber($generator->generate(Certificate::TYPE_REVIEWER));

        return $certificate;
    }

    /**
     * Build a new article acceptance certificate (not yet persisted).
     *
     * @param object $plugin
     * @param int $userId Recipient user id (author)
     * @param int $submissionId
     * @param int $contextId
     * @param string|null $locale
     * @param string|null $acceptanceDate
     * @return Certificate
     */
    public static function createAcceptanceCertificate($plugin, $userId, $submissionId, $contextId, $locale = null, $acceptanceDate = null) {
        require_once(dirname(__DIR__) . '/Certificate.php');

        $issuedAt = $acceptanceDate ?: self::getCurrentTimestamp();

        $certificate = new Certificate();
        $certificate->setCertificateType(Certificate::TYPE_ACCEPTANCE);
        $certificate->setStatus(Certificate::STATUS_VALID);
        $certificate->setUserId((int) $userId);
        $certificate->setReviewerId((int) $userId);
        $certificate->setSubmissionId((int) $submissionId);
        $certificate->setReviewId((int) $submissionId);
        $certificate->setContextId((int) $contextId);
        $certificate->setDateIssued($issuedAt);
        $certificate->setGeneratedAt(self::getCurrentTimestamp());
        $certificate->setCertificateCode(Certificate::generateCode());
        $certificate->setDownloadCount(0);

        if ($locale) {
            $certificate->setLocale($locale);
        }

        $generator = new CertificateNumberGenerator($plugin, (int) $contextId);
        $certificate->setCertificateNumber($generator->generate(Certificate::TYPE_ACCEPTANCE));

        return $certificate;
    }

    /**
     * Build a new author publication certificate (not yet persisted).
     *
     * @param object $plugin
     * @param int $userId
     * @param int $submissionId
     * @param int $contextId
     * @param string|null $locale
     * @param string|null $publicationDate
     * @return Certificate
     */
    public static function createAuthorCertificate($plugin, $userId, $submissionId, $contextId, $locale = null, $publicationDate = null) {
        require_once(dirname(__DIR__) . '/Certificate.php');

        $issuedAt = $publicationDate ?: self::getCurrentTimestamp();

        $certificate = new Certificate();
        $certificate->setCertificateType(Certificate::TYPE_AUTHOR);
        $certificate->setStatus(Certificate::STATUS_VALID);
        $certificate->setUserId((int) $userId);
        $certificate->setReviewerId((int) $userId);
        $certificate->setSubmissionId((int) $submissionId);
        $certificate->setReviewId(Certificate::getStorageReviewId($submissionId, Certificate::TYPE_AUTHOR));
        $certificate->setContextId((int) $contextId);
        $certificate->setDateIssued($issuedAt);
        $certificate->setGeneratedAt(self::getCurrentTimestamp());
        $certificate->setCertificateCode(Certificate::generateCode());
        $certificate->setDownloadCount(0);

        if ($locale) {
            $certificate->setLocale($locale);
        }

        $generator = new CertificateNumberGenerator($plugin, (int) $contextId);
        $certificate->setCertificateNumber($generator->generate(Certificate::TYPE_AUTHOR));

        return $certificate;
    }

    /**
     * @return string
     */
    public static function getCurrentTimestamp() {
        if (class_exists('PKP\core\Core')) {
            return \PKP\core\Core::getCurrentDate();
        }
        if (function_exists('import')) {
            import('lib.pkp.classes.core.Core');
            return \Core::getCurrentDate();
        }
        return date('Y-m-d H:i:s');
    }
}
