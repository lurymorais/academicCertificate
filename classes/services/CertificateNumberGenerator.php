<?php
/**
 * @file plugins/generic/academicCertificate/classes/services/CertificateNumberGenerator.php
 *
 * Generates stable certificate numbers: {PREFIX}-{TYPE}-{YEAR}-{SEQUENCE}
 * Example: ACM-REV-2026-000001
 */

namespace APP\plugins\generic\academicCertificate\classes\services;

use APP\plugins\generic\academicCertificate\classes\Certificate;
use PKP\db\DAORegistry;

class CertificateNumberGenerator {

    /** @var \APP\plugins\generic\academicCertificate\AcademicCertificatePlugin */
    private $plugin;

    /** @var int */
    private $contextId;

    /**
     * @param $plugin \APP\plugins\generic\academicCertificate\AcademicCertificatePlugin
     * @param int $contextId
     */
    public function __construct($plugin, $contextId) {
        $this->plugin = $plugin;
        $this->contextId = (int) $contextId;
    }

    /**
     * @param string $certificateType Certificate::TYPE_*
     * @return string
     */
    public function generate($certificateType) {
        $prefix = $this->getPrefix();
        $typeCode = $this->getTypeCode($certificateType);
        $year = (int) date('Y');
        $sequence = $this->getNextSequence($certificateType, $year);

        return sprintf('%s-%s-%d-%06d', $prefix, $typeCode, $year, $sequence);
    }

    /**
     * @return string
     */
    private function getPrefix() {
        $prefix = $this->plugin->getSetting($this->contextId, 'certificateNumberPrefix');
        if (!$prefix) {
            $prefix = 'ACM';
        }
        $prefix = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $prefix));
        return $prefix !== '' ? $prefix : 'ACM';
    }

    /**
     * @param string $certificateType
     * @return string
     */
    private function getTypeCode($certificateType) {
        switch ($certificateType) {
            case Certificate::TYPE_EDITOR:
                return 'EDT';
            case Certificate::TYPE_ACCEPTANCE:
                return 'ACC';
            case Certificate::TYPE_AUTHOR:
                return 'AUT';
            case Certificate::TYPE_REVIEWER:
            default:
                return 'REV';
        }
    }

    /**
     * @param string $certificateType
     * @param int $year
     * @return int
     */
    private function getNextSequence($certificateType, $year) {
        $dao = $this->plugin->getCertificateDao();
        if (!$dao) {
            return 1;
        }

        try {
            $result = $dao->retrieve(
                'SELECT COUNT(*) AS cnt FROM reviewer_certificates
                 WHERE context_id = ? AND certificate_type = ? AND YEAR(date_issued) = ?',
                array($this->contextId, $certificateType, $year)
            );
            $row = $result ? $result->current() : null;
            if (!$row) {
                return 1;
            }
            if (is_array($row)) {
                return (int) ($row['cnt'] ?? 0) + 1;
            }
            return (int) ($row->cnt ?? 0) + 1;
        } catch (\Throwable $e) {
            error_log('AcademicCertificate: getNextSequence failed: ' . $e->getMessage());
            return 1;
        }
    }
}
