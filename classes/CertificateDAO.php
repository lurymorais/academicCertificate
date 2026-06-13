<?php
/**
 * @file plugins/generic/academicCertificate/classes/CertificateDAO.php
 *
 * Copyright (c) 2024
 * Distributed under the GNU GPL v3.
 *
 * @class CertificateDAO
 * @ingroup plugins_generic_academicCertificate
 *
 * @brief Operations for retrieving and modifying Certificate objects
 */

namespace APP\plugins\generic\academicCertificate\classes;

use PKP\db\DAO;
use PKP\db\DAOResultFactory;

require_once(dirname(__FILE__) . '/Certificate.php');

class CertificateDAO extends DAO {

    /**
     * Return a registered instance without probing getDAO() (OJS 3.3 fatals on unknown DAO names).
     * @return CertificateDAO|null
     */
    public static function getRegistered() {
        if (class_exists('PKP\db\DAORegistry')) {
            $daos = \PKP\db\DAORegistry::getDAOs();
        } else {
            if (function_exists('import')) {
                import('lib.pkp.classes.db.DAORegistry');
            }
            $daos = \DAORegistry::getDAOs();
        }
        return isset($daos['CertificateDAO']) ? $daos['CertificateDAO'] : null;
    }

    /**
     * Retrieve a certificate by certificate ID
     * @param $certificateId int
     * @return Certificate
     */
    public function getById($certificateId) {
        $result = $this->retrieve(
            'SELECT * FROM reviewer_certificates WHERE certificate_id = ?',
            array((int) $certificateId)
        );

        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Retrieve a certificate by review ID
     * @param $reviewId int
     * @return Certificate
     */
    public function getByReviewId($reviewId, $certificateType = Certificate::TYPE_REVIEWER) {
        $result = $this->retrieve(
            'SELECT * FROM reviewer_certificates WHERE review_id = ? AND certificate_type = ?',
            array((int) $reviewId, $certificateType)
        );

        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Retrieve a certificate by review ID and context ID
     * @param $reviewId int
     * @param $contextId int
     * @return Certificate
     */
    public function getByReviewIdAndContext($reviewId, $contextId, $certificateType = Certificate::TYPE_REVIEWER) {
        $result = $this->retrieve(
            'SELECT * FROM reviewer_certificates WHERE review_id = ? AND context_id = ? AND certificate_type = ?',
            array((int) $reviewId, (int) $contextId, $certificateType)
        );

        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Retrieve acceptance/editor certificate by submission and type.
     * Uses submission_id as review_id for non-reviewer types.
     *
     * @param int $submissionId
     * @param int $contextId
     * @param string $certificateType
     * @return Certificate|null
     */
    public function getBySubmissionIdAndType($submissionId, $contextId, $certificateType) {
        $storageReviewId = Certificate::getStorageReviewId($submissionId, $certificateType);
        if ($storageReviewId === null) {
            return null;
        }

        $result = $this->retrieve(
            'SELECT rc.* FROM reviewer_certificates rc
             INNER JOIN submissions s ON rc.submission_id = s.submission_id
             WHERE rc.submission_id = ? AND rc.context_id = ? AND s.context_id = ?
             AND rc.certificate_type = ? AND rc.review_id = ?',
            array(
                (int) $submissionId,
                (int) $contextId,
                (int) $contextId,
                $certificateType,
                (int) $storageReviewId,
            )
        );

        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Retrieve a certificate by certificate code
     * @param $certificateCode string
     * @return Certificate
     */
    public function getByCertificateCode($certificateCode) {
        $result = $this->retrieve(
            'SELECT * FROM reviewer_certificates WHERE certificate_code = ?',
            array($certificateCode)
        );

        $row = $result->current();
        return $row ? $this->_fromRow((array) $row) : null;
    }

    /**
     * Retrieve all certificates for a reviewer
     * @param $reviewerId int
     * @param $contextId int optional
     * @return DAOResultFactory
     */
    public function getByReviewerId($reviewerId, $contextId = null) {
        $params = array((int) $reviewerId);
        $sql = 'SELECT * FROM reviewer_certificates WHERE reviewer_id = ?';

        if ($contextId !== null) {
            $sql .= ' AND context_id = ?';
            $params[] = (int) $contextId;
        }

        $sql .= ' ORDER BY date_issued DESC';

        $result = $this->retrieve($sql, $params);
        // OJS 3.4+/3.3 compatibility
        if (class_exists('PKP\db\DAOResultFactory')) {
            return new DAOResultFactory($result, $this, '_fromRow');
        } elseif (function_exists('import')) {
            import('lib.pkp.classes.db.DAOResultFactory');
            return new \DAOResultFactory($result, $this, '_fromRow');
        }
        return null;
    }

    /**
     * Retrieve all certificates for a context
     * @param $contextId int
     * @return DAOResultFactory
     */
    public function getByContextId($contextId) {
        $result = $this->retrieve(
            'SELECT * FROM reviewer_certificates WHERE context_id = ? ORDER BY date_issued DESC',
            array((int) $contextId)
        );

        // OJS 3.4+/3.3 compatibility
        if (class_exists('PKP\db\DAOResultFactory')) {
            return new DAOResultFactory($result, $this, '_fromRow');
        } elseif (function_exists('import')) {
            import('lib.pkp.classes.db.DAOResultFactory');
            return new \DAOResultFactory($result, $this, '_fromRow');
        }
        return null;
    }

    /**
     * Get certificate count by reviewer ID
     * @param $reviewerId int
     * @param $contextId int optional
     * @return int
     */
    public function getCountByReviewerId($reviewerId, $contextId = null) {
        $params = array((int) $reviewerId);
        $sql = 'SELECT COUNT(*) AS cnt FROM reviewer_certificates WHERE reviewer_id = ?';

        if ($contextId !== null) {
            $sql .= ' AND context_id = ?';
            $params[] = (int) $contextId;
        }

        $result = $this->retrieve($sql, $params);
        $row = $result->current();
        return $row ? (int) $row->cnt : 0;
    }

    /**
     * Insert a new certificate
     * @param $certificate Certificate
     * @return int inserted certificate ID
     */
    public function insertObject($certificate) {
        $this->update(
            'INSERT INTO reviewer_certificates
                (reviewer_id, user_id, submission_id, review_id, context_id, certificate_type, role_type,
                 template_id, date_issued, generated_at, certificate_code, certificate_number, status, locale,
                 metadata_json, download_count, notification_sent_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array(
                (int) $certificate->getReviewerId(),
                (int) $certificate->getUserId(),
                (int) $certificate->getSubmissionId(),
                (int) $certificate->getReviewId(),
                (int) $certificate->getContextId(),
                $certificate->getCertificateType(),
                $certificate->getRoleType(),
                $certificate->getTemplateId() ? (int) $certificate->getTemplateId() : null,
                $certificate->getDateIssued(),
                $certificate->getGeneratedAt() ?: $certificate->getDateIssued(),
                $certificate->getCertificateCode(),
                $certificate->getCertificateNumber(),
                $certificate->getStatus(),
                $certificate->getLocale(),
                $certificate->getMetadataJson(),
                (int) $certificate->getDownloadCount(),
                $certificate->getNotificationSentAt()
            )
        );

        $certificate->setCertificateId($this->getInsertId());
        return $certificate->getCertificateId();
    }

    /**
     * Update an existing certificate
     * @param $certificate Certificate
     */
    public function updateObject($certificate) {
        $this->update(
            'UPDATE reviewer_certificates
            SET
                reviewer_id = ?,
                user_id = ?,
                submission_id = ?,
                review_id = ?,
                context_id = ?,
                certificate_type = ?,
                role_type = ?,
                template_id = ?,
                date_issued = ?,
                generated_at = ?,
                certificate_code = ?,
                certificate_number = ?,
                status = ?,
                locale = ?,
                metadata_json = ?,
                download_count = ?,
                last_downloaded = ?,
                notification_sent_at = ?
            WHERE certificate_id = ?',
            array(
                (int) $certificate->getReviewerId(),
                (int) $certificate->getUserId(),
                (int) $certificate->getSubmissionId(),
                (int) $certificate->getReviewId(),
                (int) $certificate->getContextId(),
                $certificate->getCertificateType(),
                $certificate->getRoleType(),
                $certificate->getTemplateId() ? (int) $certificate->getTemplateId() : null,
                $certificate->getDateIssued(),
                $certificate->getGeneratedAt(),
                $certificate->getCertificateCode(),
                $certificate->getCertificateNumber(),
                $certificate->getStatus(),
                $certificate->getLocale(),
                $certificate->getMetadataJson(),
                (int) $certificate->getDownloadCount(),
                $certificate->getLastDownloaded(),
                $certificate->getNotificationSentAt(),
                (int) $certificate->getCertificateId()
            )
        );
    }

    /**
     * Delete a certificate
     * @param $certificate Certificate
     */
    public function deleteObject($certificate) {
        return $this->deleteById($certificate->getCertificateId());
    }

    /**
     * Delete a certificate by ID
     * @param $certificateId int
     */
    public function deleteById($certificateId) {
        $this->update(
            'DELETE FROM reviewer_certificates WHERE certificate_id = ?',
            array((int) $certificateId)
        );
    }

    /**
     * Delete all certificates for a review
     * @param $reviewId int
     */
    public function deleteByReviewId($reviewId) {
        $this->update(
            'DELETE FROM reviewer_certificates WHERE review_id = ?',
            array((int) $reviewId)
        );
    }

    /**
     * Delete all certificates for a context
     * @param $contextId int
     */
    public function deleteByContextId($contextId) {
        $this->update(
            'DELETE FROM reviewer_certificates WHERE context_id = ?',
            array((int) $contextId)
        );
    }

    /**
     * Create a ReviewAssignment-like object from a database row.
     * For OJS 3.5 compatibility where ReviewAssignmentDAO is not available.
     * @param $row object Database row
     * @return object Object with getter methods for review assignment data
     */
    public function reviewAssignmentFromRow($row) {
        return new class($row) {
            private $data;
            public function __construct($row) {
                $this->data = (array) $row;
            }
            public function getId() {
                return $this->data['review_id'] ?? null;
            }
            public function getReviewerId() {
                return $this->data['reviewer_id'] ?? null;
            }
            public function getSubmissionId() {
                return $this->data['submission_id'] ?? null;
            }
            public function getDateCompleted() {
                return $this->data['date_completed'] ?? null;
            }
            public function getDateNotified() {
                return $this->data['date_notified'] ?? null;
            }
        };
    }

    /**
     * Construct a new certificate object
     * @return Certificate
     */
    public function newDataObject() {
        return new Certificate();
    }

    /**
     * Internal function to return a Certificate object from a row
     * @param $row array
     * @return Certificate
     */
    public function _fromRow($row) {
        $certificate = $this->newDataObject();

        $certificate->setCertificateId($row['certificate_id']);
        $certificate->setReviewerId($row['reviewer_id']);
        if (isset($row['user_id'])) {
            $certificate->setUserId($row['user_id']);
        }
        $certificate->setSubmissionId($row['submission_id']);
        $certificate->setReviewId($row['review_id']);
        $certificate->setContextId($row['context_id']);
        if (isset($row['certificate_type'])) {
            $certificate->setCertificateType($row['certificate_type']);
        }
        if (isset($row['role_type'])) {
            $certificate->setRoleType($row['role_type']);
        }
        $certificate->setTemplateId($row['template_id']);
        $certificate->setDateIssued($row['date_issued']);
        if (isset($row['generated_at'])) {
            $certificate->setGeneratedAt($row['generated_at']);
        }
        $certificate->setCertificateCode($row['certificate_code']);
        if (isset($row['certificate_number'])) {
            $certificate->setCertificateNumber($row['certificate_number']);
        }
        if (isset($row['status'])) {
            $certificate->setStatus($row['status']);
        }
        if (isset($row['locale'])) {
            $certificate->setLocale($row['locale']);
        }
        if (isset($row['metadata_json'])) {
            $certificate->setMetadataJson($row['metadata_json']);
        }
        $certificate->setDownloadCount($row['download_count']);
        $certificate->setLastDownloaded($row['last_downloaded']);
        if (isset($row['notification_sent_at'])) {
            $certificate->setNotificationSentAt($row['notification_sent_at']);
        }

        return $certificate;
    }

    /**
     * Update certificate status.
     * @param int $certificateId
     * @param string $status
     * @return void
     */
    public function updateStatus($certificateId, $status) {
        $this->update(
            'UPDATE reviewer_certificates SET status = ? WHERE certificate_id = ?',
            array($status, (int) $certificateId)
        );
    }

    /**
     * Get certificate statistics for a context
     * @param $contextId int
     * @return array Statistics array with 'total', 'downloads', and 'reviewers' counts
     */
    public function getStatisticsByContext($contextId) {
        $defaults = array(
            'total' => 0,
            'downloads' => 0,
            'reviewers' => 0,
        );

        try {
            $result = $this->retrieve(
                'SELECT COUNT(*) AS total FROM reviewer_certificates WHERE context_id = ?',
                array((int) $contextId)
            );
            $total = $this->getScalarFromResult($result, 'total');

            $result = $this->retrieve(
                'SELECT SUM(download_count) AS downloads FROM reviewer_certificates WHERE context_id = ?',
                array((int) $contextId)
            );
            $downloads = $this->getScalarFromResult($result, 'downloads');

            $result = $this->retrieve(
                'SELECT COUNT(DISTINCT reviewer_id) AS reviewers FROM reviewer_certificates WHERE context_id = ?',
                array((int) $contextId)
            );
            $reviewers = $this->getScalarFromResult($result, 'reviewers');

            return array(
                'total' => $total,
                'downloads' => $downloads,
                'reviewers' => $reviewers,
            );
        } catch (\Throwable $e) {
            error_log('AcademicCertificate: getStatisticsByContext failed: ' . $e->getMessage());
            return $defaults;
        }
    }

    /**
     * @param mixed $result
     * @param string $field
     * @return int
     */
    private function getScalarFromResult($result, $field) {
        if (!$result) {
            return 0;
        }
        $row = $result->current();
        if (!$row) {
            return 0;
        }
        if (is_array($row)) {
            return (int) ($row[$field] ?? 0);
        }
        return (int) ($row->$field ?? 0);
    }

    /**
     * Get the insert ID for the last inserted certificate
     * @return int
     */
    public function getInsertId(): int {
        // OJS 3.5 removed _getInsertId() from base DAO class
        // Use method_exists check with fallback to Laravel/PDO
        if (method_exists($this, '_getInsertId')) {
            return $this->_getInsertId('reviewer_certificates', 'certificate_id');
        }
        // Fallback for OJS 3.5+: use Illuminate DB facade
        // Wrap in try/catch to handle OJS 3.3.0-20+ where Laravel exists but DB isn't bootstrapped
        if (class_exists('Illuminate\Support\Facades\DB')) {
            try {
                $pdo = \Illuminate\Support\Facades\DB::getPdo();
                if ($pdo !== null) {
                    return (int) $pdo->lastInsertId();
                }
            } catch (\Throwable $e) {
                // Laravel DB not bootstrapped (OJS 3.3.0-20+), fall through
                error_log('AcademicCertificate: getInsertId() Laravel fallback failed: ' . $e->getMessage());
            }
        }
        return 0;
    }
}
