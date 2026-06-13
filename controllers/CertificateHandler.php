<?php
/**
 * @file plugins/generic/academicCertificate/controllers/CertificateHandler.php
 *
 * Copyright (c) 2024
 * Distributed under the GNU GPL v3.
 *
 * @class CertificateHandler
 * @ingroup plugins_generic_academicCertificate
 *
 * @brief Handle requests for certificate operations
 */

namespace APP\plugins\generic\academicCertificate\controllers;

use APP\handler\Handler;
use APP\core\Application;
use PKP\security\Role;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\db\DAORegistry;
use PKP\core\JSONMessage;
use PKP\plugins\PluginRegistry;
use APP\template\TemplateManager;
use Exception;

class CertificateHandler extends Handler {

    /** @var AcademicCertificatePlugin */
    private $plugin;

    /** @var AcademicCertificatePlugin|null OJS 3.3/3.4 HANDLER_CLASS bootstrap */
    private static $pluginInstance;

    /**
     * Constructor
     * @param mixed $request OJS 3.3 page router passes Request (ignored, kept for signature compatibility)
     */
    public function __construct($request = null) {
        parent::__construct();
        if (self::$pluginInstance && !$this->plugin) {
            $this->plugin = self::$pluginInstance;
        }
        // OJS 3.3 defines role IDs as global constants via define();
        // OJS 3.4+ defines them as class constants on PKP\security\Role.
        // Note: class_exists('PKP\security\Role') returns true on OJS 3.3 due
        // to compat_autoloader aliasing, so check for the global constant instead.
        if (defined('ROLE_ID_REVIEWER')) {
            $myCertificatesRoles = array(
                ROLE_ID_REVIEWER,
                ROLE_ID_AUTHOR,
                ROLE_ID_SUB_EDITOR,
                ROLE_ID_ASSISTANT,
                ROLE_ID_MANAGER,
                ROLE_ID_SITE_ADMIN,
            );
            $this->addRoleAssignment($myCertificatesRoles, array('myCertificates', 'download', 'downloadCertificate', 'downloadAcceptance', 'downloadAuthor'));
            $this->addRoleAssignment(
                array(ROLE_ID_REVIEWER, ROLE_ID_SUB_EDITOR, ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN),
                array('preview')
            );
            $this->addRoleAssignment(
                array(ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN),
                array('manage', 'generateBatch')
            );
        } else {
            $myCertificatesRoles = array(
                Role::ROLE_ID_REVIEWER,
                Role::ROLE_ID_AUTHOR,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SITE_ADMIN,
            );
            $this->addRoleAssignment($myCertificatesRoles, array('myCertificates', 'download', 'downloadCertificate', 'downloadAcceptance', 'downloadAuthor'));
            $this->addRoleAssignment(
                array(Role::ROLE_ID_REVIEWER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN),
                array('preview')
            );
            $this->addRoleAssignment(
                array(Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN),
                array('manage', 'generateBatch')
            );
        }
        // Make verify publicly accessible (no role restriction)
    }

    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments) {
        $op = $request->getRequestedOp();

        // Allow public access to verify operation (no authentication required)
        if ($op === 'verify') {
            // Skip all authorization for verify - it's a public endpoint
            return true;
        }

        // For all other operations, require context access - OJS 3.4+/3.3 compatibility
        if (class_exists('PKP\security\authorization\ContextAccessPolicy')) {
            $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        } elseif (function_exists('import')) {
            import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
            $this->addPolicy(new \ContextAccessPolicy($request, $roleAssignments));
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Set the plugin
     * @param $plugin AcademicCertificatePlugin
     */
    public function setPlugin($plugin) {
        $this->plugin = $plugin;
        self::$pluginInstance = $plugin;
    }

    /**
     * OJS 3.3/3.4: page router instantiates HANDLER_CLASS without calling setPlugin().
     * @param object $plugin AcademicCertificatePlugin
     */
    public static function setPluginInstance($plugin) {
        self::$pluginInstance = $plugin;
    }

    /**
     * Get the plugin instance
     * @return AcademicCertificatePlugin|null
     */
    private function getPlugin() {
        if ($this->plugin) {
            return $this->plugin;
        }
        if (self::$pluginInstance) {
            $this->plugin = self::$pluginInstance;
            return $this->plugin;
        }

        $registryClass = class_exists('PKP\plugins\PluginRegistry')
            ? 'PKP\\plugins\\PluginRegistry'
            : 'PluginRegistry';

        foreach (array('academiccertificateplugin', 'academicCertificate') as $name) {
            $plugin = $registryClass::getPlugin('generic', $name);
            if ($plugin) {
                $this->plugin = $plugin;
                return $this->plugin;
            }
        }

        if (method_exists($registryClass, 'loadPlugin')) {
            $this->plugin = $registryClass::loadPlugin('generic', 'academicCertificate');
        }

        return $this->plugin;
    }

    /**
     * Render a plugin Smarty template with file-path fallback (OJS 3.3 rename-safe).
     * @param object $templateMgr TemplateManager
     * @param string $templateName e.g. myCertificates.tpl
     */
    private function displayPluginTemplate($templateMgr, $templateName) {
        $templateFile = dirname(__DIR__) . '/templates/' . $templateName;

        $plugin = $this->getPlugin();
        if ($plugin) {
            try {
                $templateMgr->display($plugin->getTemplateResource($templateName));
                return;
            } catch (\Throwable $e) {
                error_log('AcademicCertificate: template resource failed (' . $templateName . '): ' . $e->getMessage());
            }
        }

        if (file_exists($templateFile)) {
            $templateMgr->display('file:' . $templateFile);
            return;
        }

        error_log('AcademicCertificate: template not found: ' . $templateFile);
        echo '<p>Error: Certificate template not found.</p>';
    }

    /**
     * Ensure plugin locale data is loaded for the current request.
     * OJS 3.3 may fail to load locale files on public pages when registered
     * with relative paths during plugin bootstrap.
     */
    private function ensurePluginLocaleLoaded() {
        $plugin = $this->getPlugin();
        if (!$plugin) {
            return;
        }

        // Standard reload attempt
        if (method_exists($plugin, 'addLocaleData')) {
            $plugin->addLocaleData();
        }

        // Check if translations are actually available
        $testKey = 'plugins.generic.academicCertificate.verify.title';
        $translated = __($testKey);
        if ($translated === '##' . $testKey . '##') {
            // Translations still missing — manually register with absolute path.
            // OJS 3.3.0-22 uses .po files (via Gettext), not .xml.
            $localeDir = dirname(__DIR__) . '/locale';

            // Determine current locale
            $locale = 'en_US';
            if (class_exists('AppLocale', false)) {
                $currentLocale = \AppLocale::getLocale();
                if ($currentLocale) {
                    $locale = $currentLocale;
                }
            }

            // Register .po file with absolute path
            $localeFile = $localeDir . '/' . $locale . '/locale.po';
            if (file_exists($localeFile) && class_exists('AppLocale', false)) {
                \AppLocale::registerLocaleFile($locale, $localeFile);
            }

            // If locale was 'en', also try 'en_US' (or vice versa)
            if (strpos($locale, '_') === false) {
                $altLocale = $locale . '_US';
            } else {
                $altLocale = substr($locale, 0, 2);
            }
            $altFile = $localeDir . '/' . $altLocale . '/locale.po';
            if (file_exists($altFile) && class_exists('AppLocale', false)) {
                \AppLocale::registerLocaleFile($altLocale, $altFile);
            }
        }
    }

    /**
     * Download certificate
     * @param $args array
     * @param $request Request
     */
    public function download($args, $request) {
        $reviewId = isset($args[0]) ? (int) $args[0] : null;
        $user = $request->getUser();
        $context = $request->getContext();

        if (!$reviewId || !$user) {
            error_log('Certificate download failed: Missing review ID or user');
            http_response_code(404);
            throw new Exception('Not found', 404);
        }

        // Get review assignment using direct SQL for OJS 3.5 compatibility
        $certificateDao = $this->getPlugin()->getCertificateDao();
        if (!$certificateDao) {
            error_log('Certificate download failed: CertificateDAO not available');
            http_response_code(500);
            throw new Exception('Internal error', 500);
        }
        $result = $certificateDao->retrieve(
            'SELECT ra.* FROM review_assignments ra
             INNER JOIN submissions s ON ra.submission_id = s.submission_id
             WHERE ra.review_id = ? AND s.context_id = ?',
            array((int) $reviewId, (int) $context->getId())
        );

        $reviewAssignment = null;
        if ($result) {
            $row = $result->current();
            if ($row) {
                $reviewAssignment = $certificateDao->reviewAssignmentFromRow($row);
            }
        }

        if (!$reviewAssignment) {
            error_log('Certificate download failed: Review assignment not found');
            http_response_code(404);
            throw new Exception('Review assignment not found', 404);
        }

        // Validate access - user must be the reviewer
        if ((int)$reviewAssignment->getReviewerId() !== (int)$user->getId()) {
            error_log('Certificate download failed: Access denied for user ' . $user->getId() . ', review belongs to reviewer ' . $reviewAssignment->getReviewerId());
            http_response_code(403);
            throw new Exception(__('plugins.generic.academicCertificate.error.accessDenied'), 403);
        }

        // Check if review is completed
        if (!$reviewAssignment->getDateCompleted()) {
            error_log('Certificate download failed: Review not completed');
            http_response_code(400);
            throw new Exception(__('plugins.generic.academicCertificate.error.reviewNotCompleted'), 400);
        }

        // Get or create certificate
        $certificateDao = $this->getPlugin()->getCertificateDao();
        if (!$certificateDao) {
            error_log('Certificate download failed: CertificateDAO not available');
            http_response_code(500);
            throw new Exception('Internal error', 500);
        }
        $certificate = $certificateDao->getByReviewIdAndContext($reviewId, $context->getId());

        if (!$certificate) {
            try {
                $certificate = $this->createReviewerCertificateRecord(
                    $this->getPlugin(),
                    $reviewAssignment,
                    (int) $context->getId()
                );
                $certificateDao->insertObject($certificate);
            } catch (\Throwable $e) {
                if (strpos($e->getMessage(), 'Duplicate') !== false) {
                    $certificate = $certificateDao->getByReviewId($reviewId);
                } else {
                    throw $e;
                }
            }
        }

        if ($certificate && $certificate->isRevoked()) {
            error_log('Certificate download failed: Certificate revoked for review ' . $reviewId);
            http_response_code(403);
            throw new Exception(__('plugins.generic.academicCertificate.error.certificateRevoked'), 403);
        }

        // Update download statistics
        $certificate->incrementDownloadCount();
        $certificateDao->updateObject($certificate);

        // Generate PDF
        $this->generateAndOutputPDF($reviewAssignment, $certificate, $context);
    }

    /**
     * Preview certificate (for editors/managers)
     * @param $args array
     * @param $request Request
     */
    public function preview($args, $request) {
        $context = $request->getContext();
        $user = $request->getUser();

        // Generate preview with sample data
        $this->generatePreviewPDF($context);
    }

    /**
     * Verify certificate
     * @param $args array
     * @param $request Request
     */
    public function verify($args, $request) {
        // OJS 3.3 compatibility: ensure plugin locale is loaded for public pages
        $this->ensurePluginLocaleLoaded();

        // Get certificate code from URL path or query parameter.
        // Priority: $args[0] (path-based) → getUserVar('code') (query string) → URL path fallback.
        // The URL path fallback handles OJS configurations where $args is not populated
        // correctly (e.g., certain mod_rewrite setups on OJS 3.4).
        $certificateCode = isset($args[0]) ? $args[0] : $request->getUserVar('code');

        // Fallback: parse code from the request URI directly.
        // Some OJS 3.4 configurations with non-standard PATH_INFO don't populate $args.
        if (!$certificateCode) {
            $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            if (preg_match('#/certificate/verify/([A-Fa-f0-9]{8,32})(?:[/?#]|$)#', $requestUri, $matches)) {
                $certificateCode = $matches[1];
            }
        }

        // Also check $_GET and $_REQUEST as final fallback for query-string 'code' parameter
        if (!$certificateCode && !empty($_GET['code'])) {
            $certificateCode = $_GET['code'];
        }

        // Sanitize: certificate codes are uppercase hex characters (8-32 chars).
        // Older plugin versions generated 12-char codes; current version generates 16.
        if ($certificateCode) {
            $certificateCode = strtoupper(trim($certificateCode));
            if (!preg_match('/^[A-F0-9]{8,32}$/', $certificateCode)) {
                $certificateCode = null;
            }
        }

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('certificateCode', $certificateCode);

        if ($certificateCode) {
            // Lookup certificate
            $certificateDao = $this->getPlugin()->getCertificateDao();
            if (!$certificateDao) {
                $templateMgr->assign('isValid', false);
            } else {
                $certificate = $certificateDao->getByCertificateCode($certificateCode);

                // Verify certificate belongs to current journal context
                if ($certificate) {
                    $context = $request->getContext();
                    if ($context && (int)$certificate->getContextId() !== (int)$context->getId()) {
                        $certificate = null; // Not from this journal
                    }
                }

                if ($certificate) {
                    if ($certificate->isRevoked()) {
                        $templateMgr->assign('isValid', false);
                        $templateMgr->assign('isRevoked', true);
                    } else {
                    // Get reviewer and context information - OJS 3.3 compatibility
                    if (class_exists('APP\facades\Repo')) {
                        $reviewer = \APP\facades\Repo::user()->get($certificate->getReviewerId());
                    } else {
                        $userDao = DAORegistry::getDAO('UserDAO');
                        $reviewer = $userDao->getById($certificate->getReviewerId());
                    }

                    // OJS 3.4+/3.3 compatibility
                    if (class_exists('APP\core\Application')) {
                        $contextDao = Application::getContextDAO();
                    } else {
                        $contextDao = \Application::getContextDAO();
                    }
                    $certContext = $contextDao->getById($certificate->getContextId());

                    if ($reviewer && $certContext) {
                        // Assign valid certificate data to template
                        $templateMgr->assign('isValid', true);
                        $templateMgr->assign('reviewerName', $reviewer->getFullName());
                        // Use review completion date (matches PDF content), fall back to certificate issuance date.
                        // date_issued is the DB row creation time which may be identical for batch-generated certs.
                        $displayDate = $certificate->getDateIssued();
                        $reviewId = $certificate->getReviewId();
                        if ($reviewId) {
                            $raResult = $certificateDao->retrieve(
                                'SELECT date_completed FROM review_assignments WHERE review_id = ?',
                                array((int) $reviewId)
                            );
                            $raRow = $raResult->current();
                            if ($raRow) {
                                $raRow = (array) $raRow;
                                if (!empty($raRow['date_completed'])) {
                                    $displayDate = $raRow['date_completed'];
                                }
                            }
                        }
                        $formattedDate = date('F j, Y', strtotime($displayDate));
                        $templateMgr->assign('dateIssued', $formattedDate);
                        $templateMgr->assign('journalName', $certContext->getLocalizedName());
                    } else {
                        $templateMgr->assign('isValid', false);
                    }
                    }
                } else {
                    // Invalid certificate
                    $templateMgr->assign('isValid', false);
                }
            }
        }

        // Display verification page
        $this->displayPluginTemplate($templateMgr, 'verify.tpl');
    }

    /**
     * List all certificates for the current reviewer.
     * @param $args array
     * @param $request Request
     */
    public function myCertificates($args, $request) {
        $this->ensurePluginLocaleLoaded();

        $user = $request->getUser();
        $context = $request->getContext();

        if (!$user || !$context) {
            $request->redirect(null, 'login');
            return;
        }

        $plugin = $this->getPlugin();
        if (!$plugin) {
            echo '<p>Error: Academic Certificate plugin not loaded.</p>';
            return;
        }

        $certificateDao = $plugin->getCertificateDao();
        if (!$certificateDao) {
            echo '<p>Error: Certificate system not available.</p>';
            return;
        }

        require_once(dirname(__FILE__) . '/../classes/services/MyCertificateListService.php');
        $service = new \APP\plugins\generic\academicCertificate\classes\services\MyCertificateListService();
        $typeFilter = $request->getUserVar('type');
        if ($typeFilter && !in_array($typeFilter, array(
            \APP\plugins\generic\academicCertificate\classes\Certificate::TYPE_REVIEWER,
            \APP\plugins\generic\academicCertificate\classes\Certificate::TYPE_EDITOR,
            \APP\plugins\generic\academicCertificate\classes\Certificate::TYPE_ACCEPTANCE,
            \APP\plugins\generic\academicCertificate\classes\Certificate::TYPE_AUTHOR,
        ), true)) {
            $typeFilter = null;
        }

        $certificates = $service->getForUser(
            $plugin,
            $user,
            $context,
            $request,
            $certificateDao,
            array(
                'locale' => $this->getCurrentLocale(),
                'type' => $typeFilter,
            )
        );

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('certificates', $certificates);
        $templateMgr->assign('typeFilter', $typeFilter);
        $templateMgr->assign('certificateTypeOptions', array(
            '' => __('plugins.generic.academicCertificate.myCertificates.filterAll'),
            \APP\plugins\generic\academicCertificate\classes\Certificate::TYPE_REVIEWER =>
                __('plugins.generic.academicCertificate.certificateType.reviewer'),
            \APP\plugins\generic\academicCertificate\classes\Certificate::TYPE_ACCEPTANCE =>
                __('plugins.generic.academicCertificate.certificateType.acceptance'),
            \APP\plugins\generic\academicCertificate\classes\Certificate::TYPE_AUTHOR =>
                __('plugins.generic.academicCertificate.certificateType.author'),
            \APP\plugins\generic\academicCertificate\classes\Certificate::TYPE_EDITOR =>
                __('plugins.generic.academicCertificate.certificateType.editor'),
        ));

        $templateMgr->addStyleSheet(
            'academicCertificateCSS',
            $request->getBaseUrl() . '/' . $plugin->getPluginPath() . '/css/certificate.css',
            array('contexts' => 'frontend')
        );
        $this->displayPluginTemplate($templateMgr, 'myCertificates.tpl');
    }

    /**
     * Download certificate by certificate_id (editor / acceptance types).
     * @param $args array
     * @param $request Request
     */
    public function downloadCertificate($args, $request) {
        $certificateId = isset($args[0]) ? (int) $args[0] : null;
        $user = $request->getUser();
        $context = $request->getContext();

        if (!$certificateId || !$user || !$context) {
            http_response_code(404);
            throw new Exception('Not found', 404);
        }

        $certificateDao = $this->getPlugin()->getCertificateDao();
        if (!$certificateDao) {
            http_response_code(500);
            throw new Exception('Internal error', 500);
        }

        $certificate = $certificateDao->getById($certificateId);
        if (!$certificate) {
            http_response_code(404);
            throw new Exception('Not found', 404);
        }

        require_once(dirname(__FILE__) . '/../classes/services/MyCertificateListService.php');
        $listService = new \APP\plugins\generic\academicCertificate\classes\services\MyCertificateListService();
        if (!$listService->userCanAccessCertificate($certificate, $user, $context, $this->getPlugin())) {
            http_response_code(403);
            throw new Exception(__('plugins.generic.academicCertificate.error.accessDenied'), 403);
        }

        if ($certificate->isRevoked()) {
            http_response_code(403);
            throw new Exception(__('plugins.generic.academicCertificate.error.certificateRevoked'), 403);
        }

        $plugin = $this->getPlugin();
        $type = $certificate->getCertificateType();
        if ($type === \APP\plugins\generic\academicCertificate\classes\Certificate::TYPE_REVIEWER) {
            $request->redirect(null, 'certificate', 'download', array((int) $certificate->getReviewId()));
            return;
        }

        if ($type === \APP\plugins\generic\academicCertificate\classes\Certificate::TYPE_ACCEPTANCE) {
            if (!$this->getBoolPluginSetting($plugin, (int) $context->getId(), 'enableAcceptanceCertificates', true)) {
                http_response_code(403);
                throw new Exception(__('plugins.generic.academicCertificate.error.acceptanceDisabled'), 403);
            }

            $submissionId = (int) $certificate->getSubmissionId();
            if (!$submissionId) {
                http_response_code(404);
                throw new Exception('Not found', 404);
            }

            $certificate->incrementDownloadCount();
            $certificateDao->updateObject($certificate);
            $this->generateAndOutputAcceptancePDF($submissionId, $certificate, $context);
            return;
        }

        if ($type === \APP\plugins\generic\academicCertificate\classes\Certificate::TYPE_AUTHOR) {
            if (!$this->getBoolPluginSetting($plugin, (int) $context->getId(), 'enableAuthorCertificates', true)) {
                http_response_code(403);
                throw new Exception(__('plugins.generic.academicCertificate.error.authorDisabled'), 403);
            }

            $submissionId = (int) $certificate->getSubmissionId();
            if (!$submissionId) {
                http_response_code(404);
                throw new Exception('Not found', 404);
            }

            $certificate->incrementDownloadCount();
            $certificateDao->updateObject($certificate);
            $this->generateAndOutputAuthorPDF($submissionId, $certificate, $context);
            return;
        }

        http_response_code(501);
        throw new Exception(__('plugins.generic.academicCertificate.error.certificateTypeNotAvailable'), 501);
    }

    /**
     * Download acceptance certificate by submission_id (creates record on first download).
     * @param $args array
     * @param $request Request
     */
    public function downloadAcceptance($args, $request) {
        $submissionId = isset($args[0]) ? (int) $args[0] : 0;
        $user = $request->getUser();
        $context = $request->getContext();

        if (!$submissionId || !$user || !$context) {
            http_response_code(404);
            throw new Exception('Not found', 404);
        }

        $plugin = $this->getPlugin();
        if (!$this->getBoolPluginSetting($plugin, (int) $context->getId(), 'enableAcceptanceCertificates', true)) {
            http_response_code(403);
            throw new Exception(__('plugins.generic.academicCertificate.error.acceptanceDisabled'), 403);
        }

        $certificateDao = $plugin->getCertificateDao();
        if (!$certificateDao) {
            http_response_code(500);
            throw new Exception('Internal error', 500);
        }

        $subResult = $certificateDao->retrieve(
            'SELECT submission_id FROM submissions WHERE submission_id = ? AND context_id = ? LIMIT 1',
            array($submissionId, (int) $context->getId())
        );
        if (!$subResult || !$subResult->current()) {
            http_response_code(404);
            throw new Exception('Not found', 404);
        }

        require_once(dirname(__FILE__) . '/../classes/services/MyCertificateListService.php');
        require_once(dirname(__FILE__) . '/../classes/services/AcceptanceEligibilityService.php');
        $listService = new \APP\plugins\generic\academicCertificate\classes\services\MyCertificateListService();

        if (!$listService->userHasManagerAccess($user, $context)) {
            if (!\APP\plugins\generic\academicCertificate\classes\services\AcceptanceEligibilityService::isSubmissionAccepted(
                $submissionId,
                (int) $context->getId(),
                $certificateDao
            )) {
                http_response_code(400);
                throw new Exception(__('plugins.generic.academicCertificate.error.submissionNotAccepted'), 400);
            }
            if (!\APP\plugins\generic\academicCertificate\classes\services\AcceptanceEligibilityService::userIsAuthorOfSubmission(
                $user,
                $submissionId,
                (int) $context->getId(),
                $certificateDao
            )) {
                http_response_code(403);
                throw new Exception(__('plugins.generic.academicCertificate.error.accessDenied'), 403);
            }
        }

        $certificate = $this->getOrCreateAcceptanceCertificate($plugin, $submissionId, $context, $user, $certificateDao);
        if ($certificate->isRevoked()) {
            http_response_code(403);
            throw new Exception(__('plugins.generic.academicCertificate.error.certificateRevoked'), 403);
        }

        $certificate->incrementDownloadCount();
        $certificateDao->updateObject($certificate);
        $this->generateAndOutputAcceptancePDF($submissionId, $certificate, $context);
    }

    /**
     * Download author publication certificate by submission_id (creates record on first download).
     * @param $args array
     * @param $request Request
     */
    public function downloadAuthor($args, $request) {
        $submissionId = isset($args[0]) ? (int) $args[0] : 0;
        $user = $request->getUser();
        $context = $request->getContext();

        if (!$submissionId || !$user || !$context) {
            http_response_code(404);
            throw new Exception('Not found', 404);
        }

        $plugin = $this->getPlugin();
        if (!$this->getBoolPluginSetting($plugin, (int) $context->getId(), 'enableAuthorCertificates', true)) {
            http_response_code(403);
            throw new Exception(__('plugins.generic.academicCertificate.error.authorDisabled'), 403);
        }

        $certificateDao = $plugin->getCertificateDao();
        if (!$certificateDao) {
            http_response_code(500);
            throw new Exception('Internal error', 500);
        }

        $subResult = $certificateDao->retrieve(
            'SELECT submission_id FROM submissions WHERE submission_id = ? AND context_id = ? LIMIT 1',
            array($submissionId, (int) $context->getId())
        );
        if (!$subResult || !$subResult->current()) {
            http_response_code(404);
            throw new Exception('Not found', 404);
        }

        require_once(dirname(__FILE__) . '/../classes/services/MyCertificateListService.php');
        require_once(dirname(__FILE__) . '/../classes/services/AcceptanceEligibilityService.php');
        require_once(dirname(__FILE__) . '/../classes/services/PublicationEligibilityService.php');
        $listService = new \APP\plugins\generic\academicCertificate\classes\services\MyCertificateListService();

        if (!$listService->userHasManagerAccess($user, $context)) {
            if (!\APP\plugins\generic\academicCertificate\classes\services\PublicationEligibilityService::isSubmissionPublished(
                $submissionId,
                (int) $context->getId(),
                $certificateDao
            )) {
                http_response_code(400);
                throw new Exception(__('plugins.generic.academicCertificate.error.submissionNotPublished'), 400);
            }
            if (!\APP\plugins\generic\academicCertificate\classes\services\AcceptanceEligibilityService::userIsAuthorOfSubmission(
                $user,
                $submissionId,
                (int) $context->getId(),
                $certificateDao
            )) {
                http_response_code(403);
                throw new Exception(__('plugins.generic.academicCertificate.error.accessDenied'), 403);
            }
        }

        $certificate = $this->getOrCreateAuthorCertificate($plugin, $submissionId, $context, $user, $certificateDao);
        if ($certificate->isRevoked()) {
            http_response_code(403);
            throw new Exception(__('plugins.generic.academicCertificate.error.certificateRevoked'), 403);
        }

        $certificate->incrementDownloadCount();
        $certificateDao->updateObject($certificate);
        $this->generateAndOutputAuthorPDF($submissionId, $certificate, $context);
    }

    /**
     * Get current locale with fallback
     * @return string
     */
    private function getCurrentLocale() {
        // OJS 3.4+/3.5: PKP\facades\Locale (Laravel facade)
        if (class_exists('PKP\facades\Locale')) {
            try {
                $locale = \PKP\facades\Locale::getLocale();
                if ($locale) {
                    return $locale;
                }
            } catch (\Throwable $e) {
                // ignore — facade not yet bootstrapped
            }
        }
        // OJS 3.3: AppLocale (global class, loaded during bootstrap)
        if (class_exists('AppLocale')) {
            try {
                $locale = \AppLocale::getLocale();
                if ($locale) {
                    return $locale;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }
        try {
            $request = Application::get()->getRequest();
            if (method_exists($request, 'getLocale')) {
                $locale = $request->getLocale();
                if ($locale) {
                    return $locale;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return 'en_US';
    }

    /**
     * Get submission title for the listing page, with locale fallback.
     * @param $dao CertificateDAO
     * @param int $submissionId
     * @param string $locale
     * @return string
     */
    private function getSubmissionTitleForListing($dao, $submissionId, $locale) {
        try {
            // Try the requested locale first, then fall back to any locale
            $result = $dao->retrieve(
                'SELECT ps.setting_value, ps.locale FROM publication_settings ps
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
                    return strip_tags($row['setting_value']);
                }
            }
        } catch (\Throwable $e) {
            // Fallback if publication_settings query fails (e.g. OJS 3.3 schema differences)
        }

        // Last resort: try submission_settings (OJS 3.3 stores titles differently)
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
                    return strip_tags($row['setting_value']);
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return '(Untitled)';
    }

    /**
     * Generate and output PDF
     * @param $reviewAssignment ReviewAssignment
     * @param $certificate Certificate
     * @param $context Context
     */
    private function generateAndOutputPDF($reviewAssignment, $certificate, $context) {
        // Load generator
        $plugin = $this->getPlugin();
        require_once(dirname(__FILE__) . '/../classes/CertificateGenerator.php');
        $generator = new \APP\plugins\generic\academicCertificate\classes\CertificateGenerator();

        // Set up generator
        $generator->setReviewAssignment($reviewAssignment);
        $generator->setCertificate($certificate);
        $generator->setContext($context);
        $generator->setLocale($this->getCurrentLocale());

        // Load template settings
        $templateSettings = $this->getTemplateSettings($context);
        $generator->setTemplateSettings($templateSettings);

        // Generate PDF
        try {
            $pdfContent = $generator->generatePDF();
        } catch (\Throwable $e) {
            error_log(sprintf(
                'AcademicCertificate: PDF generation failed [%s]: %s in %s:%d | review_id=%s reviewer_id=%s',
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $reviewAssignment ? $reviewAssignment->getId() : 'null',
                $reviewAssignment ? $reviewAssignment->getReviewerId() : 'null'
            ));
            http_response_code(500);
            echo 'An error occurred generating the certificate. Please try again later.';
            exit;
        }

        // Output PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="reviewer_certificate_' . $certificate->getCertificateId() . '.pdf"');
        header('Content-Length: ' . strlen($pdfContent));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        echo $pdfContent;
        exit;
    }

    /**
     * Generate preview PDF
     * @param $context Context
     */
    private function generatePreviewPDF($context) {
        $plugin = $this->getPlugin();
        require_once(dirname(__FILE__) . '/../classes/CertificateGenerator.php');
        $generator = new \APP\plugins\generic\academicCertificate\classes\CertificateGenerator();

        // Create mock objects for preview
        $mockReviewAssignment = new \stdClass();
        $mockReviewAssignment->dateCompleted = date('Y-m-d H:i:s');

        $mockReviewer = new \stdClass();
        $mockReviewer->fullName = 'Dr. Jane Smith';

        $mockSubmission = new \stdClass();
        $mockSubmission->title = 'Sample Article Title: A Comprehensive Study';

        // Note: This is a simplified preview. In production, you'd want to create proper mock objects
        // or modify CertificateGenerator to handle preview mode

        $generator->setContext($context);
        $generator->setLocale($this->getCurrentLocale());
        $templateSettings = $this->getTemplateSettings($context);
        $generator->setTemplateSettings($templateSettings);

        try {
            $pdfContent = $generator->generatePDF();
        } catch (\Throwable $e) {
            error_log('AcademicCertificate: Preview PDF generation failed: ' . $e->getMessage());
            http_response_code(500);
            echo 'An error occurred generating the preview. Please try again later.';
            exit;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="certificate_preview.pdf"');
        header('Content-Length: ' . strlen($pdfContent));

        echo $pdfContent;
        exit;
    }

    /**
     * Read a boolean plugin setting with default when unset (OJS returns false for missing keys).
     *
     * @param object $plugin
     * @param int $contextId
     * @param string $key
     * @param bool $default
     * @return bool
     */
    private function getBoolPluginSetting($plugin, $contextId, $key, $default = true) {
        $value = $plugin->getSetting($contextId, $key);
        if ($value === null || $value === '') {
            return $default;
        }
        return (bool) $value;
    }

    /**
     * Get template settings for context
     * @param $context Context
     * @return array
     */
    private function getTemplateSettings($context) {
        $settings = array();
        $plugin = $this->getPlugin();

        if (!$plugin) {
            error_log('AcademicCertificate: getTemplateSettings() called but plugin instance is null');
            return $settings;
        }

        $settingNames = array(
            'backgroundImage',
            'reviewerBackgroundImage',
            'acceptanceBackgroundImage',
            'authorBackgroundImage',
            'editorBackgroundImage',
            'headerText',
            'bodyTemplate',
            'footerText',
            'acceptanceHeaderText',
            'acceptanceBodyTemplate',
            'acceptanceFooterText',
            'authorHeaderText',
            'authorBodyTemplate',
            'authorFooterText',
            'fontFamily',
            'fontSize',
            'textColorR',
            'textColorG',
            'textColorB',
            'includeQRCode',
            'minimumReviews',
            'pageOrientation'
        );

        foreach ($settingNames as $name) {
            $settings[$name] = $plugin->getSetting($context->getId(), $name);
        }

        return $settings;
    }

    /**
     * Generate batch certificates
     * @param $args array
     * @param $request Request
     */
    public function generateBatch($args, $request) {
        $context = $request->getContext();
        $reviewerIds = $request->getUserVar('reviewerIds');

        if (!is_array($reviewerIds) || empty($reviewerIds)) {
            return $this->getPlugin()->createJSONMessage(false, __('plugins.generic.academicCertificate.error.noReviewersSelected'));
        }

        $generated = 0;
        $errors = array();

        $certificateDao = $this->getPlugin()->getCertificateDao();
        if (!$certificateDao) {
            return $this->getPlugin()->createJSONMessage(false, 'Internal error: database not available');
        }

        foreach ($reviewerIds as $reviewerId) {
            try {
                // Get completed reviews for this reviewer, scoped to current context
                $result = $certificateDao->retrieve(
                    'SELECT ra.* FROM review_assignments ra
                     INNER JOIN submissions s ON ra.submission_id = s.submission_id
                     LEFT JOIN reviewer_certificates rc ON ra.review_id = rc.review_id AND rc.certificate_type = ?
                     WHERE ra.reviewer_id = ? AND s.context_id = ?
                     AND ra.date_completed IS NOT NULL AND rc.certificate_id IS NULL
                     LIMIT 500',
                    array(\APP\plugins\generic\academicCertificate\classes\Certificate::TYPE_REVIEWER, (int) $reviewerId, (int) $context->getId())
                );

                if ($result) {
                    foreach ($result as $row) {
                        $reviewAssignment = $certificateDao->reviewAssignmentFromRow($row);

                        $certificate = $this->createReviewerCertificateRecord(
                            $this->getPlugin(),
                            $reviewAssignment,
                            (int) $context->getId()
                        );

                        $certificateDao->insertObject($certificate);
                        $generated++;
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = "Reviewer ID $reviewerId: " . $e->getMessage();
            }
        }

        return $this->getPlugin()->createJSONMessage(true, array(
            'generated' => $generated,
            'errors' => $errors
        ));
    }

    /**
     * Build a reviewer certificate record (not persisted).
     *
     * @param object $plugin
     * @param object $reviewAssignment
     * @param int $contextId
     * @return \APP\plugins\generic\academicCertificate\classes\Certificate
     */
    private function createReviewerCertificateRecord($plugin, $reviewAssignment, $contextId) {
        require_once(dirname(__FILE__) . '/../classes/services/CertificateRecordService.php');
        return \APP\plugins\generic\academicCertificate\classes\services\CertificateRecordService::createReviewerCertificate(
            $plugin,
            $reviewAssignment->getReviewerId(),
            $reviewAssignment->getSubmissionId(),
            $reviewAssignment->getId(),
            $contextId
        );
    }

    /**
     * @param object $plugin
     * @param int $userId
     * @param int $submissionId
     * @param int $contextId
     * @param string|null $locale
     * @param string|null $acceptanceDate
     * @return \APP\plugins\generic\academicCertificate\classes\Certificate
     */
    private function createAcceptanceCertificateRecord($plugin, $userId, $submissionId, $contextId, $locale = null, $acceptanceDate = null) {
        require_once(dirname(__FILE__) . '/../classes/services/CertificateRecordService.php');
        return \APP\plugins\generic\academicCertificate\classes\services\CertificateRecordService::createAcceptanceCertificate(
            $plugin,
            $userId,
            $submissionId,
            $contextId,
            $locale,
            $acceptanceDate
        );
    }

    /**
     * @param object $plugin
     * @param int $submissionId
     * @param object $context
     * @param object $user
     * @param object $certificateDao
     * @return \APP\plugins\generic\academicCertificate\classes\Certificate
     */
    private function getOrCreateAcceptanceCertificate($plugin, $submissionId, $context, $user, $certificateDao) {
        require_once(dirname(__FILE__) . '/../classes/services/AcceptanceEligibilityService.php');
        $type = \APP\plugins\generic\academicCertificate\classes\Certificate::TYPE_ACCEPTANCE;

        $certificate = $certificateDao->getBySubmissionIdAndType($submissionId, (int) $context->getId(), $type);
        if ($certificate) {
            return $certificate;
        }

        $authorUserId = \APP\plugins\generic\academicCertificate\classes\services\AcceptanceEligibilityService::resolveAuthorUserIdForSubmission(
            $submissionId,
            (int) $context->getId(),
            $certificateDao
        );
        if (!$authorUserId) {
            $authorUserId = (int) $user->getId();
        }

        $acceptanceDate = \APP\plugins\generic\academicCertificate\classes\services\AcceptanceEligibilityService::getAcceptanceDateForSubmission(
            $submissionId,
            (int) $context->getId(),
            $certificateDao
        );

        $certificate = $this->createAcceptanceCertificateRecord(
            $plugin,
            $authorUserId,
            $submissionId,
            (int) $context->getId(),
            $this->getCurrentLocale(),
            $acceptanceDate
        );

        try {
            $certificateDao->insertObject($certificate);
        } catch (\Throwable $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                $existing = $certificateDao->getBySubmissionIdAndType($submissionId, (int) $context->getId(), $type);
                if ($existing) {
                    return $existing;
                }
            }
            throw $e;
        }

        return $certificate;
    }

    /**
     * @param int $submissionId
     * @param object $certificate
     * @param object $context
     */
    private function generateAndOutputAcceptancePDF($submissionId, $certificate, $context) {
        require_once(dirname(__FILE__) . '/../classes/services/AcceptanceEligibilityService.php');
        require_once(dirname(__FILE__) . '/../classes/CertificateGenerator.php');

        $certificateDao = $this->getPlugin()->getCertificateDao();
        $generator = new \APP\plugins\generic\academicCertificate\classes\CertificateGenerator();
        $generator->setCertificate($certificate);
        $generator->setCertificateType(\APP\plugins\generic\academicCertificate\classes\Certificate::TYPE_ACCEPTANCE);
        $generator->setContext($context);
        $generator->setLocale($this->getCurrentLocale());
        $generator->setSubmissionById((int) $submissionId);

        $acceptanceDate = \APP\plugins\generic\academicCertificate\classes\services\AcceptanceEligibilityService::getAcceptanceDateForSubmission(
            (int) $submissionId,
            (int) $context->getId(),
            $certificateDao
        );
        if (!$acceptanceDate) {
            $acceptanceDate = $certificate->getDateIssued();
        }
        $generator->setAcceptanceDate($acceptanceDate);

        $templateSettings = $this->getTemplateSettings($context);
        $generator->setTemplateSettings($templateSettings);

        try {
            $pdfContent = $generator->generatePDF();
        } catch (\Throwable $e) {
            error_log(sprintf(
                'AcademicCertificate: Acceptance PDF generation failed [%s]: %s in %s:%d | submission_id=%s',
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $submissionId
            ));
            http_response_code(500);
            echo 'An error occurred generating the certificate. Please try again later.';
            exit;
        }

        $filename = 'acceptance_certificate_' . (int) $submissionId . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfContent));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        echo $pdfContent;
        exit;
    }

    /**
     * @param object $plugin
     * @param int $userId
     * @param int $submissionId
     * @param int $contextId
     * @param string|null $locale
     * @param string|null $publicationDate
     * @return \APP\plugins\generic\academicCertificate\classes\Certificate
     */
    private function createAuthorCertificateRecord($plugin, $userId, $submissionId, $contextId, $locale = null, $publicationDate = null) {
        require_once(dirname(__FILE__) . '/../classes/services/CertificateRecordService.php');
        return \APP\plugins\generic\academicCertificate\classes\services\CertificateRecordService::createAuthorCertificate(
            $plugin,
            $userId,
            $submissionId,
            $contextId,
            $locale,
            $publicationDate
        );
    }

    /**
     * @param object $plugin
     * @param int $submissionId
     * @param object $context
     * @param object $user
     * @param object $certificateDao
     * @return \APP\plugins\generic\academicCertificate\classes\Certificate
     */
    private function getOrCreateAuthorCertificate($plugin, $submissionId, $context, $user, $certificateDao) {
        require_once(dirname(__FILE__) . '/../classes/services/AcceptanceEligibilityService.php');
        require_once(dirname(__FILE__) . '/../classes/services/PublicationEligibilityService.php');
        $type = \APP\plugins\generic\academicCertificate\classes\Certificate::TYPE_AUTHOR;

        $certificate = $certificateDao->getBySubmissionIdAndType($submissionId, (int) $context->getId(), $type);
        if ($certificate) {
            return $certificate;
        }

        $authorUserId = \APP\plugins\generic\academicCertificate\classes\services\AcceptanceEligibilityService::resolveAuthorUserIdForSubmission(
            $submissionId,
            (int) $context->getId(),
            $certificateDao
        );
        if (!$authorUserId) {
            $authorUserId = (int) $user->getId();
        }

        $publicationDate = \APP\plugins\generic\academicCertificate\classes\services\PublicationEligibilityService::getPublicationDateForSubmission(
            $submissionId,
            (int) $context->getId(),
            $certificateDao
        );

        $certificate = $this->createAuthorCertificateRecord(
            $plugin,
            $authorUserId,
            $submissionId,
            (int) $context->getId(),
            $this->getCurrentLocale(),
            $publicationDate
        );

        try {
            $certificateDao->insertObject($certificate);
        } catch (\Throwable $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                $existing = $certificateDao->getBySubmissionIdAndType($submissionId, (int) $context->getId(), $type);
                if ($existing) {
                    return $existing;
                }
            }
            throw $e;
        }

        return $certificate;
    }

    /**
     * @param int $submissionId
     * @param object $certificate
     * @param object $context
     */
    private function generateAndOutputAuthorPDF($submissionId, $certificate, $context) {
        require_once(dirname(__FILE__) . '/../classes/services/PublicationEligibilityService.php');
        require_once(dirname(__FILE__) . '/../classes/CertificateGenerator.php');

        $certificateDao = $this->getPlugin()->getCertificateDao();
        $generator = new \APP\plugins\generic\academicCertificate\classes\CertificateGenerator();
        $generator->setCertificate($certificate);
        $generator->setCertificateType(\APP\plugins\generic\academicCertificate\classes\Certificate::TYPE_AUTHOR);
        $generator->setContext($context);
        $generator->setLocale($this->getCurrentLocale());
        $generator->setSubmissionById((int) $submissionId);

        $publicationDate = \APP\plugins\generic\academicCertificate\classes\services\PublicationEligibilityService::getPublicationDateForSubmission(
            (int) $submissionId,
            (int) $context->getId(),
            $certificateDao
        );
        if (!$publicationDate) {
            $publicationDate = $certificate->getDateIssued();
        }
        $generator->setPublicationDate($publicationDate);

        $templateSettings = $this->getTemplateSettings($context);
        $generator->setTemplateSettings($templateSettings);

        try {
            $pdfContent = $generator->generatePDF();
        } catch (\Throwable $e) {
            error_log(sprintf(
                'AcademicCertificate: Author PDF generation failed [%s]: %s in %s:%d | submission_id=%s',
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $submissionId
            ));
            http_response_code(500);
            echo 'An error occurred generating the certificate. Please try again later.';
            exit;
        }

        $filename = 'author_certificate_' . (int) $submissionId . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfContent));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        echo $pdfContent;
        exit;
    }

}
