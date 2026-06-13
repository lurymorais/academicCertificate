<?php
/**
 * @file plugins/generic/academicCertificate/classes/services/PublicationEligibilityService.php
 *
 * Detects published submissions for author publication certificates (OJS 3.3–3.5).
 */

namespace APP\plugins\generic\academicCertificate\classes\services;

class PublicationEligibilityService {

    /**
     * @return int
     */
    private static function getPublishedStatusId() {
        if (defined('STATUS_PUBLISHED')) {
            return (int) STATUS_PUBLISHED;
        }
        return 3;
    }

    /**
     * @param int $submissionId
     * @param int $contextId
     * @param object $dao
     * @return bool
     */
    public static function isSubmissionPublished($submissionId, $contextId, $dao) {
        if (!$dao || !$submissionId || !$contextId) {
            return false;
        }

        try {
            $publishedStatus = self::getPublishedStatusId();
            $result = $dao->retrieve(
                'SELECT s.submission_id FROM submissions s
                 INNER JOIN publications p ON p.publication_id = s.current_publication_id
                 WHERE s.submission_id = ? AND s.context_id = ?
                 AND (s.status = ? OR p.date_published IS NOT NULL)
                 LIMIT 1',
                array((int) $submissionId, (int) $contextId, $publishedStatus)
            );
            return $result && $result->current();
        } catch (\Throwable $e) {
            error_log('AcademicCertificate: isSubmissionPublished failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Published submissions where the user is an author (by email match).
     *
     * @param object $user
     * @param int $contextId
     * @param object $dao
     * @return array[] Each row: submission_id, publication_date
     */
    public static function getPublishedSubmissionsForAuthor($user, $contextId, $dao) {
        if (!$user || !$dao || !$contextId) {
            return array();
        }

        $email = $user->getEmail();
        if (!$email) {
            return array();
        }

        try {
            $publishedStatus = self::getPublishedStatusId();
            $result = $dao->retrieve(
                'SELECT s.submission_id,
                        COALESCE(p.date_published, s.date_last_activity) AS publication_date
                 FROM submissions s
                 INNER JOIN publications p ON p.publication_id = s.current_publication_id
                 INNER JOIN authors a ON a.publication_id = p.publication_id
                 WHERE s.context_id = ?
                 AND LOWER(a.email) = ?
                 AND (s.status = ? OR p.date_published IS NOT NULL)
                 GROUP BY s.submission_id, p.date_published, s.date_last_activity
                 ORDER BY publication_date DESC
                 LIMIT 200',
                array((int) $contextId, strtolower(trim($email)), $publishedStatus)
            );
            if (!$result) {
                return array();
            }

            $rows = array();
            foreach ($result as $row) {
                $row = (array) $row;
                $rows[] = array(
                    'submission_id' => (int) $row['submission_id'],
                    'publication_date' => $row['publication_date'] ?? null,
                );
            }
            return $rows;
        } catch (\Throwable $e) {
            error_log('AcademicCertificate: getPublishedSubmissionsForAuthor failed: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * @param int $submissionId
     * @param int $contextId
     * @param object $dao
     * @return string|null
     */
    public static function getPublicationDateForSubmission($submissionId, $contextId, $dao) {
        if (!$submissionId || !$dao) {
            return null;
        }

        try {
            $result = $dao->retrieve(
                'SELECT p.date_published
                 FROM submissions s
                 INNER JOIN publications p ON p.publication_id = s.current_publication_id
                 WHERE s.submission_id = ? AND s.context_id = ?
                 LIMIT 1',
                array((int) $submissionId, (int) $contextId)
            );
            $row = $result ? $result->current() : null;
            if (!$row) {
                return null;
            }
            $row = (array) $row;
            return !empty($row['date_published']) ? $row['date_published'] : null;
        } catch (\Throwable $e) {
            error_log('AcademicCertificate: getPublicationDateForSubmission failed: ' . $e->getMessage());
            return null;
        }
    }
}
