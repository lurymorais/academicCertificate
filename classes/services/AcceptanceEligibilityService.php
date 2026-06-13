<?php
/**
 * @file plugins/generic/academicCertificate/classes/services/AcceptanceEligibilityService.php
 *
 * Detects accepted submissions for article acceptance certificates (OJS 3.3–3.5).
 */

namespace APP\plugins\generic\academicCertificate\classes\services;

class AcceptanceEligibilityService {

    /** @var int[] Editor decision IDs that mean "accepted for publication" */
    private static $acceptDecisionIds = array(1, 7);

    /**
     * @param int $submissionId
     * @param int $contextId
     * @param object $dao CertificateDAO or any DAO with retrieve()
     * @return bool
     */
    public static function isSubmissionAccepted($submissionId, $contextId, $dao) {
        if (!$dao || !$submissionId || !$contextId) {
            return false;
        }

        try {
            $decisions = self::getAcceptDecisionIds();
            $placeholders = implode(',', array_fill(0, count($decisions), '?'));
            $params = array_merge(
                array((int) $submissionId, (int) $contextId),
                $decisions
            );
            $result = $dao->retrieve(
                'SELECT ed.edit_decision_id FROM edit_decisions ed
                 INNER JOIN submissions s ON ed.submission_id = s.submission_id
                 WHERE ed.submission_id = ? AND s.context_id = ?
                 AND ed.decision IN (' . $placeholders . ')
                 LIMIT 1',
                $params
            );
            if (!$result) {
                return false;
            }
            return (bool) $result->current();
        } catch (\Throwable $e) {
            error_log('AcademicCertificate: isSubmissionAccepted failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Accepted submissions where the user is an author (by email match).
     *
     * @param object $user
     * @param int $contextId
     * @param object $dao
     * @return array[] Each row: submission_id, acceptance_date
     */
    public static function getAcceptedSubmissionsForAuthor($user, $contextId, $dao) {
        if (!$user || !$dao || !$contextId) {
            return array();
        }

        $email = $user->getEmail();
        if (!$email) {
            return array();
        }

        try {
            $decisions = self::getAcceptDecisionIds();
            $placeholders = implode(',', array_fill(0, count($decisions), '?'));
            $params = array_merge(
                array((int) $contextId, strtolower(trim($email))),
                $decisions
            );
            $result = $dao->retrieve(
                'SELECT s.submission_id, MAX(ed.date_decided) AS acceptance_date
                 FROM submissions s
                 INNER JOIN publications p ON p.publication_id = s.current_publication_id
                 INNER JOIN authors a ON a.publication_id = p.publication_id
                 INNER JOIN edit_decisions ed ON ed.submission_id = s.submission_id
                 WHERE s.context_id = ?
                 AND LOWER(a.email) = ?
                 AND ed.decision IN (' . $placeholders . ')
                 GROUP BY s.submission_id
                 ORDER BY acceptance_date DESC
                 LIMIT 200',
                $params
            );
            if (!$result) {
                return array();
            }

            $rows = array();
            foreach ($result as $row) {
                $row = (array) $row;
                $rows[] = array(
                    'submission_id' => (int) $row['submission_id'],
                    'acceptance_date' => $row['acceptance_date'] ?? null,
                );
            }
            return $rows;
        } catch (\Throwable $e) {
            error_log('AcademicCertificate: getAcceptedSubmissionsForAuthor failed: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * @return int[]
     */
    private static function getAcceptDecisionIds() {
        $ids = array();
        if (defined('SUBMISSION_EDITOR_DECISION_ACCEPT')) {
            $ids[] = (int) SUBMISSION_EDITOR_DECISION_ACCEPT;
        } else {
            $ids[] = 1;
        }
        if (defined('SUBMISSION_EDITOR_DECISION_SEND_TO_PRODUCTION')) {
            $prod = (int) SUBMISSION_EDITOR_DECISION_SEND_TO_PRODUCTION;
            if (!in_array($prod, $ids, true)) {
                $ids[] = $prod;
            }
        } else {
            if (!in_array(7, $ids, true)) {
                $ids[] = 7;
            }
        }
        return $ids;
    }

    /**
     * @param object $user
     * @param int $submissionId
     * @param int $contextId
     * @param object $dao
     * @return bool
     */
    public static function userIsAuthorOfSubmission($user, $submissionId, $contextId, $dao) {
        if (!$user || !$submissionId || !$dao) {
            return false;
        }

        $email = strtolower(trim((string) $user->getEmail()));
        if ($email === '') {
            return false;
        }

        try {
            $result = $dao->retrieve(
                'SELECT a.author_id FROM authors a
                 INNER JOIN publications p ON a.publication_id = p.publication_id
                 INNER JOIN submissions s ON s.current_publication_id = p.publication_id
                 WHERE s.submission_id = ? AND s.context_id = ? AND LOWER(a.email) = ?
                 LIMIT 1',
                array((int) $submissionId, (int) $contextId, $email)
            );
            return $result && $result->current();
        } catch (\Throwable $e) {
            error_log('AcademicCertificate: userIsAuthorOfSubmission failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @param int $submissionId
     * @param int $contextId
     * @param object $dao
     * @return string|null
     */
    public static function getAcceptanceDateForSubmission($submissionId, $contextId, $dao) {
        if (!$submissionId || !$dao) {
            return null;
        }

        try {
            $decisions = self::getAcceptDecisionIds();
            $placeholders = implode(',', array_fill(0, count($decisions), '?'));
            $params = array_merge(array((int) $submissionId, (int) $contextId), $decisions);
            $result = $dao->retrieve(
                'SELECT MAX(ed.date_decided) AS acceptance_date
                 FROM edit_decisions ed
                 INNER JOIN submissions s ON ed.submission_id = s.submission_id
                 WHERE ed.submission_id = ? AND s.context_id = ?
                 AND ed.decision IN (' . $placeholders . ')',
                $params
            );
            $row = $result ? $result->current() : null;
            if (!$row) {
                return null;
            }
            $row = (array) $row;
            return !empty($row['acceptance_date']) ? $row['acceptance_date'] : null;
        } catch (\Throwable $e) {
            error_log('AcademicCertificate: getAcceptanceDateForSubmission failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Resolve OJS user id for an author on a submission (by email).
     *
     * @param int $submissionId
     * @param int $contextId
     * @param object $dao
     * @return int|null
     */
    public static function resolveAuthorUserIdForSubmission($submissionId, $contextId, $dao) {
        if (!$dao || !$submissionId) {
            return null;
        }

        try {
            $result = $dao->retrieve(
                'SELECT u.user_id FROM authors a
                 INNER JOIN publications p ON a.publication_id = p.publication_id
                 INNER JOIN submissions s ON s.current_publication_id = p.publication_id
                 INNER JOIN users u ON LOWER(u.email) = LOWER(a.email)
                 WHERE s.submission_id = ? AND s.context_id = ?
                 ORDER BY a.seq ASC
                 LIMIT 1',
                array((int) $submissionId, (int) $contextId)
            );
            $row = $result ? $result->current() : null;
            if (!$row) {
                return null;
            }
            $row = (array) $row;
            return isset($row['user_id']) ? (int) $row['user_id'] : null;
        } catch (\Throwable $e) {
            error_log('AcademicCertificate: resolveAuthorUserIdForSubmission failed: ' . $e->getMessage());
            return null;
        }
    }
}
