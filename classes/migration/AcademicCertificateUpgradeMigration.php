<?php
/**
 * @file plugins/generic/academicCertificate/classes/migration/AcademicCertificateUpgradeMigration.php
 *
 * Upgrades reviewer_certificates for Academic Certificate Manager (Phase 1).
 * Idempotent — safe to run multiple times.
 */

namespace APP\plugins\generic\academicCertificate\classes\migration;

if (class_exists('Illuminate\Database\Migrations\Migration')) {
    class AcademicCertificateUpgradeMigrationBase extends \Illuminate\Database\Migrations\Migration {}
} else {
    class AcademicCertificateUpgradeMigrationBase {}
}

class AcademicCertificateUpgradeMigration extends AcademicCertificateUpgradeMigrationBase {

    /**
     * @return void
     */
    public function up() {
        if (!$this->tableExists('reviewer_certificates')) {
            return;
        }

        if (class_exists('Illuminate\Support\Facades\Schema')) {
            try {
                $this->upWithSchema();
                $this->backfillData();
                return;
            } catch (\Throwable $e) {
                error_log('AcademicCertificate: Upgrade schema failed, falling back to SQL: ' . $e->getMessage());
            }
        }

        $this->upWithRawSQL();
        $this->backfillData();
    }

    /**
     * @return void
     */
    private function upWithSchema() {
        $schema = \Illuminate\Support\Facades\Schema::class;

        if (!$schema::hasColumn('reviewer_certificates', 'user_id')) {
            $schema::table('reviewer_certificates', function ($table) {
                $table->bigInteger('user_id')->nullable()->after('reviewer_id');
            });
        }
        if (!$schema::hasColumn('reviewer_certificates', 'certificate_type')) {
            $schema::table('reviewer_certificates', function ($table) {
                $table->string('certificate_type', 32)->default('reviewer')->after('context_id');
            });
        }
        if (!$schema::hasColumn('reviewer_certificates', 'role_type')) {
            $schema::table('reviewer_certificates', function ($table) {
                $table->string('role_type', 100)->nullable()->after('certificate_type');
            });
        }
        if (!$schema::hasColumn('reviewer_certificates', 'certificate_number')) {
            $schema::table('reviewer_certificates', function ($table) {
                $table->string('certificate_number', 64)->nullable()->after('certificate_code');
            });
        }
        if (!$schema::hasColumn('reviewer_certificates', 'status')) {
            $schema::table('reviewer_certificates', function ($table) {
                $table->string('status', 16)->default('valid')->after('certificate_number');
            });
        }
        if (!$schema::hasColumn('reviewer_certificates', 'locale')) {
            $schema::table('reviewer_certificates', function ($table) {
                $table->string('locale', 14)->nullable()->after('status');
            });
        }
        if (!$schema::hasColumn('reviewer_certificates', 'metadata_json')) {
            $schema::table('reviewer_certificates', function ($table) {
                $table->text('metadata_json')->nullable()->after('locale');
            });
        }
        if (!$schema::hasColumn('reviewer_certificates', 'generated_at')) {
            $schema::table('reviewer_certificates', function ($table) {
                $table->timestamp('generated_at')->nullable()->after('date_issued');
            });
        }
        if (!$schema::hasColumn('reviewer_certificates', 'notification_sent_at')) {
            $schema::table('reviewer_certificates', function ($table) {
                $table->timestamp('notification_sent_at')->nullable()->after('last_downloaded');
            });
        }

        $this->migrateIndexesRaw();
    }

    /**
     * @return void
     */
    private function upWithRawSQL() {
        $dao = $this->getDao();
        if (!$dao) {
            throw new \Exception('Cannot get database connection for upgrade migration');
        }

        $columns = array(
            'user_id' => 'ADD COLUMN user_id BIGINT NULL AFTER reviewer_id',
            'certificate_type' => "ADD COLUMN certificate_type VARCHAR(32) NOT NULL DEFAULT 'reviewer' AFTER context_id",
            'role_type' => 'ADD COLUMN role_type VARCHAR(100) NULL AFTER certificate_type',
            'certificate_number' => 'ADD COLUMN certificate_number VARCHAR(64) NULL AFTER certificate_code',
            'status' => "ADD COLUMN status VARCHAR(16) NOT NULL DEFAULT 'valid' AFTER certificate_number",
            'locale' => 'ADD COLUMN locale VARCHAR(14) NULL AFTER status',
            'metadata_json' => 'ADD COLUMN metadata_json TEXT NULL AFTER locale',
            'generated_at' => 'ADD COLUMN generated_at TIMESTAMP NULL AFTER date_issued',
            'notification_sent_at' => 'ADD COLUMN notification_sent_at TIMESTAMP NULL AFTER last_downloaded',
        );

        foreach ($columns as $name => $sqlFragment) {
            if (!$this->columnExists($name)) {
                $dao->update('ALTER TABLE reviewer_certificates ' . $sqlFragment);
            }
        }

        $this->migrateIndexesRaw();
    }

    /**
     * @return void
     */
    private function migrateIndexesRaw() {
        $dao = $this->getDao();
        if (!$dao) {
            return;
        }

        if ($this->indexExists('reviewer_certificates_review_id_unique')) {
            $dao->update('ALTER TABLE reviewer_certificates DROP INDEX reviewer_certificates_review_id_unique');
        }

        if (!$this->indexExists('rc_type_review_uidx')) {
            try {
                $dao->update(
                    'ALTER TABLE reviewer_certificates ADD UNIQUE KEY rc_type_review_uidx (certificate_type, review_id)'
                );
            } catch (\Throwable $e) {
                error_log('AcademicCertificate: rc_type_review_uidx may already exist: ' . $e->getMessage());
            }
        }

        if (!$this->indexExists('rc_certificate_number_uidx')) {
            try {
                $dao->update(
                    'ALTER TABLE reviewer_certificates ADD UNIQUE KEY rc_certificate_number_uidx (certificate_number)'
                );
            } catch (\Throwable $e) {
                error_log('AcademicCertificate: rc_certificate_number_uidx may already exist: ' . $e->getMessage());
            }
        }

        if (!$this->indexExists('rc_certificate_type_idx')) {
            try {
                $dao->update(
                    'ALTER TABLE reviewer_certificates ADD INDEX rc_certificate_type_idx (certificate_type)'
                );
            } catch (\Throwable $e) {
                error_log('AcademicCertificate: rc_certificate_type_idx may already exist: ' . $e->getMessage());
            }
        }

        if (!$this->indexExists('rc_user_id_idx')) {
            try {
                $dao->update(
                    'ALTER TABLE reviewer_certificates ADD INDEX rc_user_id_idx (user_id)'
                );
            } catch (\Throwable $e) {
                error_log('AcademicCertificate: rc_user_id_idx may already exist: ' . $e->getMessage());
            }
        }
    }

    /**
     * Backfill user_id, certificate_type, status, and certificate_number for legacy rows.
     * @return void
     */
    private function backfillData() {
        $dao = $this->getDao();
        if (!$dao) {
            return;
        }

        try {
            $dao->update(
                'UPDATE reviewer_certificates SET user_id = reviewer_id WHERE user_id IS NULL'
            );
            $dao->update(
                "UPDATE reviewer_certificates SET certificate_type = ? WHERE certificate_type IS NULL OR certificate_type = ''",
                array('reviewer')
            );
            $dao->update(
                "UPDATE reviewer_certificates SET status = ? WHERE status IS NULL OR status = ''",
                array('valid')
            );
            $dao->update(
                'UPDATE reviewer_certificates SET generated_at = date_issued WHERE generated_at IS NULL'
            );
        } catch (\Throwable $e) {
            error_log('AcademicCertificate: backfillData failed: ' . $e->getMessage());
        }

        $this->backfillCertificateNumbers();
    }

    /**
     * Assign certificate numbers to rows that lack them.
     * @return void
     */
    private function backfillCertificateNumbers() {
        $dao = $this->getDao();
        if (!$dao) {
            return;
        }

        try {
            $result = $dao->retrieve(
                "SELECT certificate_id, context_id, certificate_type, date_issued
                 FROM reviewer_certificates
                 WHERE certificate_number IS NULL OR certificate_number = ''
                 ORDER BY certificate_id ASC"
            );
            if (!$result) {
                return;
            }

            $sequences = array();
            foreach ($result as $row) {
                $row = (array) $row;
                $contextId = (int) ($row['context_id'] ?? 0);
                $type = $row['certificate_type'] ?? 'reviewer';
                $year = !empty($row['date_issued']) ? (int) date('Y', strtotime($row['date_issued'])) : (int) date('Y');
                $key = $contextId . '|' . $type . '|' . $year;

                if (!isset($sequences[$key])) {
                    $countResult = $dao->retrieve(
                        'SELECT COUNT(*) AS cnt FROM reviewer_certificates
                         WHERE context_id = ? AND certificate_type = ? AND YEAR(date_issued) = ?
                               AND certificate_number IS NOT NULL AND certificate_number != \'\'',
                        array($contextId, $type, $year)
                    );
                    $countRow = $countResult ? $countResult->current() : null;
                    $existing = 0;
                    if ($countRow) {
                        $countRow = (array) $countRow;
                        $existing = (int) ($countRow['cnt'] ?? 0);
                    }
                    $sequences[$key] = $existing;
                }
                $sequences[$key]++;
                $number = sprintf('ACM-%s-%d-%06d', $this->typeCode($type), $year, $sequences[$key]);

                $dao->update(
                    'UPDATE reviewer_certificates SET certificate_number = ? WHERE certificate_id = ?',
                    array($number, (int) $row['certificate_id'])
                );
            }
        } catch (\Throwable $e) {
            error_log('AcademicCertificate: backfillCertificateNumbers failed: ' . $e->getMessage());
        }
    }

    /**
     * @param string $type
     * @return string
     */
    private function typeCode($type) {
        switch ($type) {
            case 'editor':
                return 'EDT';
            case 'acceptance':
                return 'ACC';
            default:
                return 'REV';
        }
    }

    /**
     * @param string $table
     * @return bool
     */
    private function tableExists($table) {
        $dao = $this->getDao();
        if (!$dao) {
            return false;
        }
        $result = $dao->retrieve(
            'SELECT COUNT(*) AS cnt FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ?',
            array($table)
        );
        $row = $result ? $result->current() : null;
        if (!$row) {
            return false;
        }
        $row = (array) $row;
        return (int) ($row['cnt'] ?? 0) > 0;
    }

    /**
     * @param string $column
     * @return bool
     */
    private function columnExists($column) {
        $dao = $this->getDao();
        if (!$dao) {
            return false;
        }
        $result = $dao->retrieve(
            'SELECT COUNT(*) AS cnt FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            array('reviewer_certificates', $column)
        );
        $row = $result ? $result->current() : null;
        if (!$row) {
            return false;
        }
        $row = (array) $row;
        return (int) ($row['cnt'] ?? 0) > 0;
    }

    /**
     * @param string $indexName
     * @return bool
     */
    private function indexExists($indexName) {
        $dao = $this->getDao();
        if (!$dao) {
            return false;
        }
        $result = $dao->retrieve(
            'SELECT COUNT(*) AS cnt FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            array('reviewer_certificates', $indexName)
        );
        $row = $result ? $result->current() : null;
        if (!$row) {
            return false;
        }
        $row = (array) $row;
        return (int) ($row['cnt'] ?? 0) > 0;
    }

    /**
     * @return object|null
     */
    private function getDao() {
        if (class_exists('PKP\db\DAORegistry')) {
            return \PKP\db\DAORegistry::getDAO('UserDAO');
        }
        return \DAORegistry::getDAO('UserDAO');
    }
}
