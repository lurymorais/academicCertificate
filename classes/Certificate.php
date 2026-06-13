<?php
/**
 * @file plugins/generic/academicCertificate/classes/Certificate.php
 *
 * Copyright (c) 2024
 * Distributed under the GNU GPL v3.
 *
 * @class Certificate
 * @ingroup plugins_generic_academicCertificate
 *
 * @brief Certificate data model
 */

namespace APP\plugins\generic\academicCertificate\classes;

use PKP\core\DataObject;
use PKP\core\Core;

class Certificate extends DataObject {

    const TYPE_REVIEWER = 'reviewer';
    const TYPE_EDITOR = 'editor';
    const TYPE_ACCEPTANCE = 'acceptance';
    const TYPE_AUTHOR = 'author';

    const STATUS_VALID = 'valid';
    const STATUS_REVOKED = 'revoked';

    /**
     * Generate a random 16-character hex certificate code
     * @return string
     */
    public static function generateCode() {
        return strtoupper(bin2hex(random_bytes(8)));
    }

    /**
     * Get certificate ID
     * @return int
     */
    public function getCertificateId() {
        return $this->getData('certificateId');
    }

    /**
     * Set certificate ID
     * @param $certificateId int
     */
    public function setCertificateId($certificateId) {
        $this->setData('certificateId', $certificateId);
    }

    /**
     * Get reviewer ID
     * @return int
     */
    public function getReviewerId() {
        return $this->getData('reviewerId');
    }

    /**
     * Set reviewer ID
     * @param $reviewerId int
     */
    public function setReviewerId($reviewerId) {
        $this->setData('reviewerId', $reviewerId);
    }

    /**
     * Get submission ID
     * @return int
     */
    public function getSubmissionId() {
        return $this->getData('submissionId');
    }

    /**
     * Set submission ID
     * @param $submissionId int
     */
    public function setSubmissionId($submissionId) {
        $this->setData('submissionId', $submissionId);
    }

    /**
     * Get review ID
     * @return int
     */
    public function getReviewId() {
        return $this->getData('reviewId');
    }

    /**
     * Set review ID
     * @param $reviewId int
     */
    public function setReviewId($reviewId) {
        $this->setData('reviewId', $reviewId);
    }

    /**
     * Get context ID
     * @return int
     */
    public function getContextId() {
        return $this->getData('contextId');
    }

    /**
     * Set context ID
     * @param $contextId int
     */
    public function setContextId($contextId) {
        $this->setData('contextId', $contextId);
    }

    /**
     * Get template ID
     * @return int
     */
    public function getTemplateId() {
        return $this->getData('templateId');
    }

    /**
     * Set template ID
     * @param $templateId int
     */
    public function setTemplateId($templateId) {
        $this->setData('templateId', $templateId);
    }

    /**
     * Get date issued
     * @return string
     */
    public function getDateIssued() {
        return $this->getData('dateIssued');
    }

    /**
     * Set date issued
     * @param $dateIssued string
     */
    public function setDateIssued($dateIssued) {
        $this->setData('dateIssued', $dateIssued);
    }

    /**
     * Get certificate code
     * @return string
     */
    public function getCertificateCode() {
        return $this->getData('certificateCode');
    }

    /**
     * Set certificate code
     * @param $certificateCode string
     */
    public function setCertificateCode($certificateCode) {
        $this->setData('certificateCode', $certificateCode);
    }

    /**
     * Get download count
     * @return int
     */
    public function getDownloadCount() {
        return $this->getData('downloadCount');
    }

    /**
     * Set download count
     * @param $downloadCount int
     */
    public function setDownloadCount($downloadCount) {
        $this->setData('downloadCount', $downloadCount);
    }

    /**
     * Get last downloaded date
     * @return string
     */
    public function getLastDownloaded() {
        return $this->getData('lastDownloaded');
    }

    /**
     * Set last downloaded date
     * @param $lastDownloaded string
     */
    public function setLastDownloaded($lastDownloaded) {
        $this->setData('lastDownloaded', $lastDownloaded);
    }

    /**
     * Increment download count
     */
    public function incrementDownloadCount() {
        $this->setDownloadCount($this->getDownloadCount() + 1);
        // OJS 3.4+/3.3 compatibility
        if (class_exists('PKP\core\Core')) {
            $this->setLastDownloaded(Core::getCurrentDate());
        } elseif (function_exists('import')) {
            import('lib.pkp.classes.core.Core');
            $this->setLastDownloaded(\Core::getCurrentDate());
        } else {
            $this->setLastDownloaded(date('Y-m-d H:i:s'));
        }
    }

    /**
     * @return int|null
     */
    public function getUserId() {
        $userId = $this->getData('userId');
        if ($userId) {
            return (int) $userId;
        }
        return $this->getReviewerId() ? (int) $this->getReviewerId() : null;
    }

    /**
     * @param int|null $userId
     */
    public function setUserId($userId) {
        $this->setData('userId', $userId !== null ? (int) $userId : null);
    }

    /**
     * @return string
     */
    public function getCertificateType() {
        return $this->getData('certificateType') ?: self::TYPE_REVIEWER;
    }

    /**
     * @param string $certificateType
     */
    public function setCertificateType($certificateType) {
        $this->setData('certificateType', $certificateType);
    }

    /**
     * @return string|null
     */
    public function getRoleType() {
        return $this->getData('roleType');
    }

    /**
     * @param string|null $roleType
     */
    public function setRoleType($roleType) {
        $this->setData('roleType', $roleType);
    }

    /**
     * @return string|null
     */
    public function getCertificateNumber() {
        return $this->getData('certificateNumber');
    }

    /**
     * @param string|null $certificateNumber
     */
    public function setCertificateNumber($certificateNumber) {
        $this->setData('certificateNumber', $certificateNumber);
    }

    /**
     * @return string
     */
    public function getStatus() {
        return $this->getData('status') ?: self::STATUS_VALID;
    }

    /**
     * @param string $status
     */
    public function setStatus($status) {
        $this->setData('status', $status);
    }

    /**
     * @return string|null
     */
    public function getLocale() {
        return $this->getData('locale');
    }

    /**
     * @param string|null $locale
     */
    public function setLocale($locale) {
        $this->setData('locale', $locale);
    }

    /**
     * @return string|null
     */
    public function getMetadataJson() {
        return $this->getData('metadataJson');
    }

    /**
     * @param string|null $metadataJson
     */
    public function setMetadataJson($metadataJson) {
        $this->setData('metadataJson', $metadataJson);
    }

    /**
     * @return array
     */
    public function getMetadata() {
        $raw = $this->getMetadataJson();
        if (!$raw) {
            return array();
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : array();
    }

    /**
     * @param array $metadata
     */
    public function setMetadata(array $metadata) {
        $this->setMetadataJson(json_encode($metadata));
    }

    /**
     * @return string|null
     */
    public function getGeneratedAt() {
        return $this->getData('generatedAt');
    }

    /**
     * @param string|null $generatedAt
     */
    public function setGeneratedAt($generatedAt) {
        $this->setData('generatedAt', $generatedAt);
    }

    /**
     * @return string|null
     */
    public function getNotificationSentAt() {
        return $this->getData('notificationSentAt');
    }

    /**
     * @param string|null $notificationSentAt
     */
    public function setNotificationSentAt($notificationSentAt) {
        $this->setData('notificationSentAt', $notificationSentAt);
    }

    /**
     * @return bool
     */
    public function isValid() {
        return $this->getStatus() === self::STATUS_VALID;
    }

    /**
     * @return bool
     */
    public function isRevoked() {
        return $this->getStatus() === self::STATUS_REVOKED;
    }

    /**
     * review_id stored in DB for submission-scoped certificate types (unique per type+review_id).
     *
     * @param int $submissionId
     * @param string $certificateType
     * @return int|null
     */
    public static function getStorageReviewId($submissionId, $certificateType) {
        $submissionId = (int) $submissionId;
        switch ($certificateType) {
            case self::TYPE_AUTHOR:
                return -$submissionId;
            case self::TYPE_ACCEPTANCE:
            case self::TYPE_EDITOR:
                return $submissionId;
            default:
                return null;
        }
    }
}
