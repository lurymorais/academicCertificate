<?php
/**
 * @file plugins/generic/academicCertificate/classes/services/MyCertificateListService.php
 *
 * Role-aware certificate listing for the My Certificates / Belgelerim page (Phase 2).
 */

namespace APP\plugins\generic\academicCertificate\classes\services;

use APP\plugins\generic\academicCertificate\classes\Certificate;

// OJS 3.3 does not autoload plugin service classes; load explicitly.
require_once(dirname(__FILE__) . '/AcceptanceEligibilityService.php');
require_once(dirname(__FILE__) . '/PublicationEligibilityService.php');

class MyCertificateListService {

    /**
     * @param object $plugin AcademicCertificatePlugin
     * @param object $user
     * @param object $context
     * @param object $request
     * @param object $dao CertificateDAO
     * @param array $options type filter, locale
     * @return array
     */
    public function getForUser($plugin, $user, $context, $request, $dao, $options = array()) {
        $contextId = (int) $context->getId();
        $userId = (int) $user->getId();
        $locale = $options['locale'] ?? 'en_US';
        $typeFilter = isset($options['type']) ? $options['type'] : null;

        $items = array();

        if ($this->isTypeEnabled($plugin, $contextId, Certificate::TYPE_REVIEWER, $typeFilter)) {
            $items = array_merge($items, $this->getReviewerItems($plugin, $userId, $contextId, $context, $request, $dao, $locale));
        }

        if ($this->isTypeEnabled($plugin, $contextId, Certificate::TYPE_ACCEPTANCE, $typeFilter)) {
            $items = array_merge($items, $this->getAcceptanceItems($plugin, $user, $contextId, $context, $request, $dao, $locale));
        }

        if ($this->isTypeEnabled($plugin, $contextId, Certificate::TYPE_AUTHOR, $typeFilter)) {
            $items = array_merge($items, $this->getAuthorItems($plugin, $user, $contextId, $context, $request, $dao, $locale));
        }

        if ($this->isTypeEnabled($plugin, $contextId, Certificate::TYPE_EDITOR, $typeFilter)) {
            $items = array_merge($items, $this->getEditorItems($plugin, $userId, $contextId, $context, $request, $dao, $locale));
        }

        usort($items, function ($a, $b) {
            return strcmp($b['sortDate'] ?? '', $a['sortDate'] ?? '');
        });

        return $items;
    }

    /**
     * @param object $plugin
     * @param int $contextId
     * @param string $type
     * @param string|null $typeFilter
     * @return bool
     */
    private function isTypeEnabled($plugin, $contextId, $type, $typeFilter) {
        if ($typeFilter && $typeFilter !== $type) {
            return false;
        }

        switch ($type) {
            case Certificate::TYPE_REVIEWER:
                return $this->getBoolSetting($plugin, $contextId, 'enableReviewerCertificates', true);
            case Certificate::TYPE_ACCEPTANCE:
                return $this->getBoolSetting($plugin, $contextId, 'enableAcceptanceCertificates', true);
            case Certificate::TYPE_AUTHOR:
                return $this->getBoolSetting($plugin, $contextId, 'enableAuthorCertificates', true);
            case Certificate::TYPE_EDITOR:
                return $this->getBoolSetting($plugin, $contextId, 'enableEditorCertificates', true);
            default:
                return false;
        }
    }

    /**
     * @return array
     */
    private function getReviewerItems($plugin, $userId, $contextId, $context, $request, $dao, $locale) {
        $items = array();
        $hideTitle = $this->shouldHideReviewerSubmissionTitle($plugin, $contextId);

        try {
            $result = $dao->retrieve(
                'SELECT rc.*, ra.date_completed AS review_date_completed
                 FROM reviewer_certificates rc
                 INNER JOIN submissions s ON rc.submission_id = s.submission_id
                 LEFT JOIN review_assignments ra ON rc.review_id = ra.review_id
                 WHERE rc.context_id = ? AND s.context_id = ?
                 AND rc.certificate_type = ?
                 AND (rc.user_id = ? OR rc.reviewer_id = ?)
                 ORDER BY COALESCE(ra.date_completed, rc.date_issued) DESC
                 LIMIT 500',
                array($contextId, $contextId, Certificate::TYPE_REVIEWER, $userId, $userId)
            );

            if ($result) {
                foreach ($result as $row) {
                    $row = (array) $row;
                    $displayDate = !empty($row['review_date_completed'])
                        ? $row['review_date_completed']
                        : ($row['date_issued'] ?? null);
                    $submissionTitle = $hideTitle
                        ? ''
                        : $this->getSubmissionTitle($dao, (int) $row['submission_id'], $locale);

                    $items[] = $this->buildItem(
                        $row,
                        Certificate::TYPE_REVIEWER,
                        $context,
                        $request,
                        $displayDate,
                        $submissionTitle,
                        $request->url(null, 'certificate', 'download', array((int) $row['review_id']))
                    );
                }
            }
        } catch (\Throwable $e) {
            error_log('AcademicCertificate: getReviewerItems failed: ' . $e->getMessage());
        }

        return $items;
    }

    /**
     * @return array
     */
    private function getEditorItems($plugin, $userId, $contextId, $context, $request, $dao, $locale) {
        $items = array();

        try {
            $result = $dao->retrieve(
                'SELECT rc.*
                 FROM reviewer_certificates rc
                 INNER JOIN submissions s ON rc.submission_id = s.submission_id
                 WHERE rc.context_id = ? AND s.context_id = ?
                 AND rc.certificate_type = ? AND rc.user_id = ?
                 ORDER BY COALESCE(rc.generated_at, rc.date_issued) DESC
                 LIMIT 200',
                array($contextId, $contextId, Certificate::TYPE_EDITOR, $userId)
            );

            if ($result) {
                foreach ($result as $row) {
                    $row = (array) $row;
                    $displayDate = $row['date_issued'] ?? null;
                    $submissionTitle = $this->getSubmissionTitle($dao, (int) $row['submission_id'], $locale);
                    $downloadUrl = !empty($row['certificate_id'])
                        ? $request->url(null, 'certificate', 'downloadCertificate', array((int) $row['certificate_id']))
                        : null;

                    $items[] = $this->buildItem(
                        $row,
                        Certificate::TYPE_EDITOR,
                        $context,
                        $request,
                        $displayDate,
                        $submissionTitle,
                        $downloadUrl
                    );
                }
            }
        } catch (\Throwable $e) {
            error_log('AcademicCertificate: getEditorItems failed: ' . $e->getMessage());
        }

        return $items;
    }

    /**
     * Lists issued acceptance certificates and eligible accepted submissions (pending issuance).
     *
     * @return array
     */
    private function getAcceptanceItems($plugin, $user, $contextId, $context, $request, $dao, $locale) {
        $items = array();
        $issuedBySubmission = array();

        try {
            $result = $dao->retrieve(
                'SELECT rc.*
                 FROM reviewer_certificates rc
                 INNER JOIN submissions s ON rc.submission_id = s.submission_id
                 WHERE rc.context_id = ? AND s.context_id = ?
                 AND rc.certificate_type = ? AND rc.user_id = ?
                 ORDER BY COALESCE(rc.generated_at, rc.date_issued) DESC
                 LIMIT 200',
                array($contextId, $contextId, Certificate::TYPE_ACCEPTANCE, (int) $user->getId())
            );

            if ($result) {
                foreach ($result as $row) {
                    $row = (array) $row;
                    $submissionId = (int) $row['submission_id'];
                    $issuedBySubmission[$submissionId] = true;
                    $displayDate = $row['date_issued'] ?? null;
                    $submissionTitle = $this->getSubmissionTitle($dao, $submissionId, $locale);
                    $downloadUrl = !empty($row['certificate_id'])
                        ? $request->url(null, 'certificate', 'downloadCertificate', array((int) $row['certificate_id']))
                        : null;

                    $items[] = $this->buildItem(
                        $row,
                        Certificate::TYPE_ACCEPTANCE,
                        $context,
                        $request,
                        $displayDate,
                        $submissionTitle,
                        $downloadUrl
                    );
                }
            }

            $accepted = AcceptanceEligibilityService::getAcceptedSubmissionsForAuthor($user, $contextId, $dao);
            foreach ($accepted as $acceptedRow) {
                $submissionId = (int) $acceptedRow['submission_id'];
                if (isset($issuedBySubmission[$submissionId])) {
                    continue;
                }
                $displayDate = $acceptedRow['acceptance_date'] ?? null;
                $submissionTitle = $this->getSubmissionTitle($dao, $submissionId, $locale);
                $downloadUrl = $request->url(null, 'certificate', 'downloadAcceptance', array($submissionId));

                $items[] = array(
                    'certificateId' => null,
                    'certificateType' => Certificate::TYPE_ACCEPTANCE,
                    'certificateTypeLabel' => __('plugins.generic.academicCertificate.certificateType.acceptance'),
                    'journalName' => $context->getLocalizedName(),
                    'submissionId' => $submissionId,
                    'submissionTitle' => $submissionTitle,
                    'dateIssued' => $displayDate ? date('F j, Y', strtotime($displayDate)) : '',
                    'sortDate' => $displayDate ?: '1970-01-01',
                    'certificateNumber' => '',
                    'certificateCode' => '',
                    'status' => 'pending',
                    'statusLabel' => __('plugins.generic.academicCertificate.status.pending'),
                    'downloadUrl' => $downloadUrl,
                    'verifyUrl' => null,
                    'downloadCount' => 0,
                    'canDownload' => true,
                    'roleType' => null,
                );
            }
        } catch (\Throwable $e) {
            error_log('AcademicCertificate: getAcceptanceItems failed: ' . $e->getMessage());
        }

        return $items;
    }

    /**
     * Lists issued author publication certificates and eligible published submissions (pending).
     *
     * @return array
     */
    private function getAuthorItems($plugin, $user, $contextId, $context, $request, $dao, $locale) {
        $items = array();
        $issuedBySubmission = array();

        try {
            $result = $dao->retrieve(
                'SELECT rc.*
                 FROM reviewer_certificates rc
                 INNER JOIN submissions s ON rc.submission_id = s.submission_id
                 WHERE rc.context_id = ? AND s.context_id = ?
                 AND rc.certificate_type = ? AND rc.user_id = ?
                 ORDER BY COALESCE(rc.generated_at, rc.date_issued) DESC
                 LIMIT 200',
                array($contextId, $contextId, Certificate::TYPE_AUTHOR, (int) $user->getId())
            );

            if ($result) {
                foreach ($result as $row) {
                    $row = (array) $row;
                    $submissionId = (int) $row['submission_id'];
                    $issuedBySubmission[$submissionId] = true;
                    $displayDate = $row['date_issued'] ?? null;
                    $submissionTitle = $this->getSubmissionTitle($dao, $submissionId, $locale);
                    $downloadUrl = !empty($row['certificate_id'])
                        ? $request->url(null, 'certificate', 'downloadCertificate', array((int) $row['certificate_id']))
                        : null;

                    $items[] = $this->buildItem(
                        $row,
                        Certificate::TYPE_AUTHOR,
                        $context,
                        $request,
                        $displayDate,
                        $submissionTitle,
                        $downloadUrl
                    );
                }
            }

            $published = PublicationEligibilityService::getPublishedSubmissionsForAuthor($user, $contextId, $dao);
            foreach ($published as $publishedRow) {
                $submissionId = (int) $publishedRow['submission_id'];
                if (isset($issuedBySubmission[$submissionId])) {
                    continue;
                }
                $displayDate = $publishedRow['publication_date'] ?? null;
                $submissionTitle = $this->getSubmissionTitle($dao, $submissionId, $locale);
                $downloadUrl = $request->url(null, 'certificate', 'downloadAuthor', array($submissionId));

                $items[] = array(
                    'certificateId' => null,
                    'certificateType' => Certificate::TYPE_AUTHOR,
                    'certificateTypeLabel' => __('plugins.generic.academicCertificate.certificateType.author'),
                    'journalName' => $context->getLocalizedName(),
                    'submissionId' => $submissionId,
                    'submissionTitle' => $submissionTitle,
                    'dateIssued' => $displayDate ? date('F j, Y', strtotime($displayDate)) : '',
                    'sortDate' => $displayDate ?: '1970-01-01',
                    'certificateNumber' => '',
                    'certificateCode' => '',
                    'status' => 'pending',
                    'statusLabel' => __('plugins.generic.academicCertificate.status.pending'),
                    'downloadUrl' => $downloadUrl,
                    'verifyUrl' => null,
                    'downloadCount' => 0,
                    'canDownload' => true,
                    'roleType' => null,
                );
            }
        } catch (\Throwable $e) {
            error_log('AcademicCertificate: getAuthorItems failed: ' . $e->getMessage());
        }

        return $items;
    }

    /**
     * @param array $row
     * @param string $type
     * @param object $context
     * @param object $request
     * @param string|null $displayDate
     * @param string $submissionTitle
     * @param string|null $downloadUrl
     * @return array
     */
    private function buildItem($row, $type, $context, $request, $displayDate, $submissionTitle, $downloadUrl) {
        $status = $row['status'] ?? Certificate::STATUS_VALID;
        $code = $row['certificate_code'] ?? '';
        $verifyUrl = $code
            ? $request->url(null, 'certificate', 'verify', array($code))
            : null;

        return array(
            'certificateId' => isset($row['certificate_id']) ? (int) $row['certificate_id'] : null,
            'certificateType' => $type,
            'certificateTypeLabel' => $this->getTypeLabel($type),
            'journalName' => $context->getLocalizedName(),
            'submissionId' => isset($row['submission_id']) ? (int) $row['submission_id'] : null,
            'submissionTitle' => $submissionTitle,
            'dateIssued' => $displayDate ? date('F j, Y', strtotime($displayDate)) : '',
            'sortDate' => $displayDate ?: ($row['date_issued'] ?? '1970-01-01'),
            'certificateNumber' => $row['certificate_number'] ?? '',
            'certificateCode' => $code,
            'status' => $status,
            'statusLabel' => $this->getStatusLabel($status),
            'downloadUrl' => ($status === Certificate::STATUS_REVOKED) ? null : $downloadUrl,
            'verifyUrl' => $verifyUrl,
            'downloadCount' => isset($row['download_count']) ? (int) $row['download_count'] : 0,
            'canDownload' => ($status !== Certificate::STATUS_REVOKED && !empty($downloadUrl)),
            'roleType' => $row['role_type'] ?? null,
        );
    }

    /**
     * @param string $type
     * @return string
     */
    private function getTypeLabel($type) {
        switch ($type) {
            case Certificate::TYPE_EDITOR:
                return __('plugins.generic.academicCertificate.certificateType.editor');
            case Certificate::TYPE_ACCEPTANCE:
                return __('plugins.generic.academicCertificate.certificateType.acceptance');
            case Certificate::TYPE_AUTHOR:
                return __('plugins.generic.academicCertificate.certificateType.author');
            case Certificate::TYPE_REVIEWER:
            default:
                return __('plugins.generic.academicCertificate.certificateType.reviewer');
        }
    }

    /**
     * @param string $status
     * @return string
     */
    private function getStatusLabel($status) {
        if ($status === Certificate::STATUS_REVOKED) {
            return __('plugins.generic.academicCertificate.status.revoked');
        }
        if ($status === 'pending') {
            return __('plugins.generic.academicCertificate.status.pending');
        }
        return __('plugins.generic.academicCertificate.status.valid');
    }

    /**
     * @param object $plugin
     * @param int $contextId
     * @param string $key
     * @param bool $default
     * @return bool
     */
    private function getBoolSetting($plugin, $contextId, $key, $default) {
        $value = $plugin->getSetting($contextId, $key);
        if ($value === null || $value === '') {
            return $default;
        }
        return (bool) $value;
    }

    /**
     * @param object $plugin
     * @param int $contextId
     * @return bool
     */
    private function shouldHideReviewerSubmissionTitle($plugin, $contextId) {
        $value = $plugin->getSetting($contextId, 'hideReviewerSubmissionTitle');
        if ($value === null || $value === '') {
            return true;
        }
        return (bool) $value;
    }

    /**
     * @param object $dao
     * @param int $submissionId
     * @param string $locale
     * @return string
     */
    private function getSubmissionTitle($dao, $submissionId, $locale) {
        try {
            $result = $dao->retrieve(
                'SELECT ps.setting_value FROM publication_settings ps
                 JOIN publications p ON p.publication_id = ps.publication_id
                 WHERE p.submission_id = ? AND ps.setting_name = ?
                 AND ps.setting_value IS NOT NULL AND ps.setting_value != ?
                 ORDER BY CASE WHEN ps.locale = ? THEN 0 ELSE 1 END, ps.locale
                 LIMIT 1',
                array((int) $submissionId, 'title', '', $locale)
            );
            if ($result) {
                foreach ($result as $row) {
                    $row = (array) $row;
                    return strip_tags($row['setting_value'] ?? '');
                }
            }
        } catch (\Throwable $e) {
            // fall through
        }

        try {
            $result = $dao->retrieve(
                'SELECT setting_value FROM submission_settings
                 WHERE submission_id = ? AND setting_name = ?
                 AND setting_value IS NOT NULL AND setting_value != ?
                 LIMIT 1',
                array((int) $submissionId, 'title', '')
            );
            if ($result) {
                foreach ($result as $row) {
                    $row = (array) $row;
                    return strip_tags($row['setting_value'] ?? '');
                }
            }
        } catch (\Throwable $e) {
            error_log('AcademicCertificate: getSubmissionTitle failed: ' . $e->getMessage());
        }
        return '';
    }

    /**
     * Verify the user may access a certificate record.
     *
     * @param Certificate $certificate
     * @param object $user
     * @param object $context
     * @param object $plugin
     * @return bool
     */
    public function userCanAccessCertificate($certificate, $user, $context, $plugin) {
        if (!$certificate || !$user || !$context) {
            return false;
        }

        if ((int) $certificate->getContextId() !== (int) $context->getId()) {
            return false;
        }

        $userId = (int) $user->getId();
        if ($this->userHasManagerAccess($user, $context)) {
            return true;
        }

        $ownerId = (int) $certificate->getUserId();
        if ($ownerId && $ownerId === $userId) {
            return true;
        }

        if ($certificate->getCertificateType() === Certificate::TYPE_REVIEWER
            && (int) $certificate->getReviewerId() === $userId) {
            return true;
        }

        if ($certificate->getCertificateType() === Certificate::TYPE_ACCEPTANCE) {
            $dao = $plugin ? $plugin->getCertificateDao() : null;
            if ($dao && AcceptanceEligibilityService::userIsAuthorOfSubmission(
                $user,
                (int) $certificate->getSubmissionId(),
                (int) $context->getId(),
                $dao
            )) {
                return true;
            }
        }

        if ($certificate->getCertificateType() === Certificate::TYPE_AUTHOR) {
            $dao = $plugin ? $plugin->getCertificateDao() : null;
            if ($dao && AcceptanceEligibilityService::userIsAuthorOfSubmission(
                $user,
                (int) $certificate->getSubmissionId(),
                (int) $context->getId(),
                $dao
            )) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param object $user
     * @param object $context
     * @return bool
     */
    public function userHasManagerAccess($user, $context) {
        if (!$user || !$context) {
            return false;
        }

        $contextId = (int) $context->getId();
        if (!method_exists($user, 'hasRole')) {
            return false;
        }

        if (defined('ROLE_ID_SITE_ADMIN') && $user->hasRole(ROLE_ID_SITE_ADMIN, $contextId)) {
            return true;
        }
        if (defined('ROLE_ID_MANAGER') && $user->hasRole(ROLE_ID_MANAGER, $contextId)) {
            return true;
        }

        // OJS 3.4+ namespaced Role constants (only when globals are not defined)
        if (!defined('ROLE_ID_SITE_ADMIN') && class_exists('PKP\security\Role')) {
            if ($user->hasRole(\PKP\security\Role::ROLE_ID_SITE_ADMIN, $contextId)) {
                return true;
            }
            if ($user->hasRole(\PKP\security\Role::ROLE_ID_MANAGER, $contextId)) {
                return true;
            }
        }

        return false;
    }
}
