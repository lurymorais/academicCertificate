<?php
/**
 * @file plugins/generic/academicCertificate/classes/AcademicCertificatePluginCore.php
 *
 * Copyright (c) 2024
 * Distributed under the GNU GPL v3.
 *
 * @class AcademicCertificatePlugin
 * @ingroup plugins_generic_academicCertificate
 *
 * @brief Academic Certificate Manager for OJS — PDF certificates for reviewers, authors, and editors
 *
 * This file contains the main plugin implementation. It is loaded by AcademicCertificatePlugin.php
 * after the compatibility autoloader has been registered.
 */

namespace APP\plugins\generic\academicCertificate;

use PKP\plugins\GenericPlugin;
use PKP\db\DAORegistry;
use PKP\plugins\Hook;
use PKP\config\Config;
use APP\core\Application;
use APP\template\TemplateManager;
use Exception;
use Throwable;

class AcademicCertificatePlugin extends GenericPlugin {

    /**
     * @copydoc Plugin::register()
     */
    public function register($category, $path, $mainContextId = null) {
        $success = parent::register($category, $path, $mainContextId);

        if ($success) {
            $this->registerPluginRegistryHooks($category);
            $this->registerLegacyPluginAlias($category);
        }

        if ($success && $this->getEnabled($mainContextId)) {
            try {
                // OJS 3.3: migration runs only on fresh OJS install (Installer::postInstall),
                // not when enabling the plugin later — ensure tables exist.
                $this->ensurePluginSchema();
                $this->registerCertificateDao();
                $this->addLocaleData();
                require_once($this->getPluginPath() . '/classes/services/CertificateNavigationMenuService.php');
                \APP\plugins\generic\academicCertificate\classes\services\CertificateNavigationMenuService::registerHooks();

                // Register hooks - use Hook class for OJS 3.4+, HookRegistry for OJS 3.3
                if (class_exists('PKP\plugins\Hook')) {
                    Hook::register('LoadHandler', array($this, 'setupHandler'));
                    Hook::register('TemplateManager::display', array($this, 'addCertificateButton'));
                    Hook::register('TemplateManager::display', array($this, 'addProfileCertificatesLink'));
                    // Note: reviewassignmentdao::_updateobject hook removed in OJS 3.5
                    // Auto-email on review completion not supported in OJS 3.5
                    Hook::register('reviewassignmentdao::_updateobject', array($this, 'handleReviewComplete'));

                    // Register Mailable for OJS 3.5+ email system
                    if (class_exists('PKP\mail\Mailable')) {
                        Hook::register('Mailer::Mailables', array($this, 'addMailable'));
                    }
                } else {
                    \HookRegistry::register('LoadHandler', array($this, 'setupHandler'));
                    \HookRegistry::register('TemplateManager::display', array($this, 'addCertificateButton'));
                    \HookRegistry::register('TemplateManager::display', array($this, 'addProfileCertificatesLink'));
                    \HookRegistry::register('reviewassignmentdao::_updateobject', array($this, 'handleReviewComplete'));
                }
            } catch (\Throwable $e) {
                error_log('AcademicCertificate: Error during plugin registration: ' . $e->getMessage());
                // Still return $success — plugin is registered but may not be fully functional
            }
        }

        return $success;
    }

    /**
     * Map pre-rename plugin registry keys to this instance (admin settings URLs).
     */
    private function registerLegacyPluginAlias($category) {
        if ($category !== 'generic') {
            return;
        }
        $plugins =& $this->getPluginRegistryMap($category);
        $plugins['academiccertificateplugin'] = $this;
        $plugins['reviewercertificateplugin'] = $this;
    }

    /** @var bool */
    private static $registryHooksRegistered = false;

    /**
     * Drop legacy reviewerCertificate folders from scans; keep registry aliases fresh.
     */
    private function registerPluginRegistryHooks($category) {
        if ($category !== 'generic' || self::$registryHooksRegistered) {
            return;
        }
        self::$registryHooksRegistered = true;

        $loadCategoryHook = array($this, 'filterLegacyPluginFolders');
        $categoryLoadedHook = array($this, 'normalizePluginRegistry');

        if (class_exists('HookRegistry', false)) {
            \HookRegistry::register('PluginRegistry::loadCategory', $loadCategoryHook);
            \HookRegistry::register('PluginRegistry::categoryLoaded::generic', $categoryLoadedHook);
        } elseif (class_exists('PKP\plugins\Hook')) {
            Hook::register('PluginRegistry::loadCategory', $loadCategoryHook);
            Hook::register('PluginRegistry::categoryLoaded::generic', $categoryLoadedHook);
        }
    }

    /**
     * @param string $hookName
     * @param array $params
     * @return bool
     */
    public function filterLegacyPluginFolders($hookName, $params) {
        if ($params[0] !== 'generic') {
            return false;
        }
        $plugins =& $params[1];
        foreach ($plugins as $seq => $entries) {
            foreach ($entries as $path => $plugin) {
                if ($this->isLegacyCertificatePluginPath($path)) {
                    unset($plugins[$seq][$path]);
                }
            }
        }
        return false;
    }

    /**
     * @param string $hookName
     * @param array $plugins
     * @return bool
     */
    public function normalizePluginRegistry($hookName, &$plugins) {
        foreach ($plugins as $name => $plugin) {
            if (!is_object($plugin) || !method_exists($plugin, 'getPluginPath')) {
                continue;
            }
            if ($this->isLegacyCertificatePluginPath($plugin->getPluginPath())) {
                unset($plugins[$name]);
            }
        }
        $plugins['academiccertificateplugin'] = $this;
        $plugins['reviewercertificateplugin'] = $this;
        return false;
    }

    /**
     * @param string $path
     * @return bool
     */
    private function isLegacyCertificatePluginPath($path) {
        $folder = basename(str_replace('\\', '/', $path));
        return (bool) preg_match('/^reviewerCertificate(\.|$)/i', $folder);
    }

    /**
     * @param string $category
     * @return array
     */
    private function &getPluginRegistryMap($category) {
        if (class_exists('PKP\plugins\PluginRegistry')) {
            return \PKP\plugins\PluginRegistry::getPlugins($category);
        }
        return \PluginRegistry::getPlugins($category);
    }

    /**
     * Always use the academicCertificate plugin instance for settings UI.
     * @return self
     */
    private function resolveSettingsPlugin() {
        if (stripos($this->getPluginPath(), 'academicCertificate') !== false) {
            return $this;
        }
        $plugins =& $this->getPluginRegistryMap('generic');
        if (isset($plugins['academiccertificateplugin'])) {
            return $plugins['academiccertificateplugin'];
        }
        if (isset($plugins['reviewercertificateplugin'])
            && stripos($plugins['reviewercertificateplugin']->getPluginPath(), 'academicCertificate') !== false) {
            return $plugins['reviewercertificateplugin'];
        }
        return $this;
    }

    /**
     * Manager-only access for certificate designer assets.
     *
     * @param \PKP\request\Request $request
     * @param \Context $context
     * @return bool
     */
    private function userCanManageCertificateSettings($request, $context) {
        $user = $request->getUser();
        if (!$user) {
            return false;
        }
        $contextId = (int) $context->getId();
        if (class_exists('PKP\security\Role')) {
            $roleId = \PKP\security\Role::ROLE_ID_MANAGER;
        } else {
            import('lib.pkp.classes.security.Role');
            $roleId = ROLE_ID_MANAGER;
        }
        $roleDao = DAORegistry::getDAO('RoleDAO');
        if ($roleDao && method_exists($roleDao, 'userHasRole')) {
            return $roleDao->userHasRole($contextId, $user->getId(), $roleId);
        }
        return false;
    }

    /**
     * Register Mailable with OJS 3.5+ email system
     */
    public function addMailable(string $hookName, array $args): void {
        require_once($this->getPluginPath() . '/classes/AcademicCertificateMailable.php');
        $args[0]->push(\APP\plugins\generic\academicCertificate\classes\AcademicCertificateMailable::class);
    }

    /**
     * Get the display name of this plugin
     * @return string
     */
    public function getDisplayName() {
        return __('plugins.generic.academicCertificate.displayName');
    }

    /**
     * Get the description of this plugin
     * @return string
     */
    public function getDescription() {
        return __('plugins.generic.academicCertificate.description');
    }

    /**
     * @copydoc Plugin::getName()
     *
     * Returns a simple name without namespace backslashes.
     * OJS 3.3's base getName() returns strtolower(get_class($this)) which
     * includes the full namespace with backslashes, breaking jQuery selectors
     * in the plugin grid and preventing enable/disable (Issue #65).
     */
    public function getName() {
        return 'academiccertificateplugin';
    }

    /**
     * @copydoc Plugin::getInstallEmailTemplatesFile()
     */
    public function getInstallEmailTemplatesFile() {
        return ($this->getPluginPath() . DIRECTORY_SEPARATOR . 'emailTemplates.xml');
    }

    /**
     * @copydoc Plugin::getCanEnable()
     */
    public function getCanEnable() {
        return true;
    }

    /**
     * @copydoc Plugin::getCanDisable()
     */
    public function getCanDisable() {
        return true;
    }

    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $verb) {
        $router = $request->getRouter();

        // OJS 3.3 compatibility for LinkAction and AjaxModal
        if (class_exists('PKP\linkAction\LinkAction')) {
            $linkAction = new \PKP\linkAction\LinkAction(
                'settings',
                new \PKP\linkAction\request\AjaxModal(
                    $router->url($request, null, null, 'manage', null, array('verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic')),
                    $this->getDisplayName()
                ),
                __('manager.plugins.settings'),
                null
            );
        } else {
            import('lib.pkp.classes.linkAction.LinkAction');
            import('lib.pkp.classes.linkAction.request.AjaxModal');
            $linkAction = new \LinkAction(
                'settings',
                new \AjaxModal(
                    $router->url($request, null, null, 'manage', null, array('verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic')),
                    $this->getDisplayName()
                ),
                __('manager.plugins.settings'),
                null
            );
        }

        return array_merge(
            $this->getEnabled() ? array($linkAction) : array(),
            parent::getActions($request, $verb)
        );
    }

    /**
     * @copydoc Plugin::manage()
     */
    public function manage($args, $request) {
        $verb = $request->getUserVar('verb');

        switch ($verb) {
            case 'settings':
                try {
                    $plugin = $this->resolveSettingsPlugin();
                    $this->addLocaleData();
                    $this->ensurePluginSchema();
                    $this->registerCertificateDao();

                    $context = $request->getContext();

                    // Validate context
                    if (!$context) {
                        error_log('AcademicCertificate: No context available for settings');
                        return $this->createJSONMessage(false, __('plugins.generic.academicCertificate.error.noContext'));
                    }

                    require_once(dirname(__DIR__) . '/classes/CertificateLayout.php');
                    require_once(dirname(__DIR__) . '/classes/form/CertificateSettingsForm.php');
                    $form = new \APP\plugins\generic\academicCertificate\classes\form\CertificateSettingsForm($plugin, $context->getId());

                    if ($request->getUserVar('save')) {
                        $form->readInputData();
                        if ($form->validate()) {
                            $form->execute();

                            // Redirect after file upload (multipart form, not AJAX)
                            $backgroundFields = array(
                                'backgroundImage',
                                'reviewerBackgroundImage',
                                'acceptanceBackgroundImage',
                                'authorBackgroundImage',
                                'editorBackgroundImage',
                            );
                            foreach ($backgroundFields as $field) {
                                if (isset($_FILES[$field]) && $_FILES[$field]['error'] == UPLOAD_ERR_OK) {
                                    $request->redirect(null, 'management', 'settings', array('website'));
                                    break;
                                }
                            }

                            return $this->createJSONMessage(true);
                        }
                    } else {
                        $form->initData();
                    }

                    return $this->createJSONMessage(true, $form->fetch($request));
                } catch (\Throwable $e) {
                    error_log('AcademicCertificate: Settings form error: ' . $e->getMessage());
                    return $this->createJSONMessage(false, $e->getMessage());
                }

            case 'backgroundPreview':
                $context = $request->getContext();
                if (!$context || !$this->userCanManageCertificateSettings($request, $context)) {
                    http_response_code(403);
                    exit;
                }
                $allowedFields = array(
                    'backgroundImage',
                    'reviewerBackgroundImage',
                    'acceptanceBackgroundImage',
                    'authorBackgroundImage',
                    'editorBackgroundImage',
                );
                $field = (string) $request->getUserVar('field');
                if (!in_array($field, $allowedFields, true)) {
                    http_response_code(400);
                    exit;
                }
                $path = $this->getSetting($context->getId(), $field);
                if (!$path || !file_exists($path)) {
                    http_response_code(404);
                    exit;
                }
                if (class_exists('PKP\core\Core')) {
                    $baseDir = \PKP\core\Core::getBaseDir();
                } else {
                    $baseDir = \Core::getBaseDir();
                }
                $allowedDir = realpath($baseDir . '/files/journals/');
                $realPath = realpath($path);
                if ($realPath === false || $allowedDir === false || strpos($realPath, $allowedDir) !== 0) {
                    http_response_code(403);
                    exit;
                }
                $mime = preg_match('/\.png$/i', $realPath) ? 'image/png' : 'image/jpeg';
                header('Content-Type: ' . $mime);
                header('Content-Length: ' . filesize($realPath));
                readfile($realPath);
                exit;

            case 'preview':
                $context = $request->getContext();

                // Validate context
                if (!$context) {
                    error_log('AcademicCertificate: No context available for preview');
                    http_response_code(400);
                    echo 'Error: No context available';
                    exit;
                }

                require_once($this->getPluginPath() . '/classes/CertificateGenerator.php');

                // Create a sample certificate for preview
                $generator = new \APP\plugins\generic\academicCertificate\classes\CertificateGenerator();

                // Get current settings
                $templateSettings = array(
                    'backgroundImage' => $this->getSetting($context->getId(), 'backgroundImage'),
                    'headerText' => $this->getSetting($context->getId(), 'headerText') ?: 'Certificate of Recognition',
                    'bodyTemplate' => $this->getSetting($context->getId(), 'bodyTemplate') ?: \APP\plugins\generic\academicCertificate\classes\CertificateGenerator::getDefaultBodyTemplate(),
                    'footerText' => $this->getSetting($context->getId(), 'footerText') ?: '',
                    'fontFamily' => $this->getSetting($context->getId(), 'fontFamily') ?: 'helvetica',
                    'fontSize' => $this->getSetting($context->getId(), 'fontSize') ?: 12,
                    'textColorR' => $this->getSetting($context->getId(), 'textColorR') ?: 0,
                    'textColorG' => $this->getSetting($context->getId(), 'textColorG') ?: 0,
                    'textColorB' => $this->getSetting($context->getId(), 'textColorB') ?: 0,
                    'includeQRCode' => $this->getSetting($context->getId(), 'includeQRCode') ?: false,
                    'pageOrientation' => $this->getSetting($context->getId(), 'pageOrientation') ?: 'P',
                );

                $generator->setContext($context);
                $generator->setTemplateSettings($templateSettings);
                $generator->setPreviewMode(true); // Enable preview mode with sample data

                // Generate and output PDF
                try {
                    $pdfContent = $generator->generatePDF();
                } catch (\Throwable $e) {
                    error_log('AcademicCertificate: Preview PDF generation failed: ' . $e->getMessage());
                    http_response_code(500);
                    echo 'An error occurred generating the preview. Please try again later.';
                    exit;
                }

                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="certificate-preview.pdf"');
                header('Content-Length: ' . strlen($pdfContent));
                echo $pdfContent;
                exit;

            case 'generateBatch':
                // Increase execution time limit for batch operations to prevent timeouts
                $originalTimeLimit = (int) ini_get('max_execution_time');
                set_time_limit(300); // 5 minutes for batch operations

                $context = $request->getContext();

                // Validate context
                if (!$context) {
                    error_log('AcademicCertificate: No context available for batch generation');
                    return $this->createJSONMessage(false, __('plugins.generic.academicCertificate.error.noContext'));
                }

                $reviewerIds = $request->getUserVar('reviewerIds');

                if (!is_array($reviewerIds) || empty($reviewerIds)) {
                    return $this->createJSONMessage(false, __('plugins.generic.academicCertificate.batch.noSelection'));
                }

                $certificateDao = $this->getCertificateDao();

                // Validate DAO
                if (!$certificateDao) {
                    error_log('AcademicCertificate: CertificateDAO not registered');
                    return $this->createJSONMessage(false, __('plugins.generic.academicCertificate.error.daoNotAvailable'));
                }
                require_once($this->getPluginPath() . '/classes/Certificate.php');

                $generated = 0;
                $errors = array();

                // Create ONE mysqli connection for the entire batch
                $dbConn = null;
                $stmt = null;
                try {
                    // Set database lock wait timeout to fail fast if there are locks
                    try {
                        $certificateDao->update('SET SESSION innodb_lock_wait_timeout = 10');
                    } catch (Exception $e) {
                        // Non-critical: proceed without custom timeout
                    }

                    $dbHost = Config::getVar('database', 'host');
                    $dbUser = Config::getVar('database', 'username');
                    $dbPass = Config::getVar('database', 'password');
                    $dbName = Config::getVar('database', 'name');

                    $dbConn = new \mysqli($dbHost, $dbUser, $dbPass, $dbName);
                    if ($dbConn->connect_error) {
                        throw new Exception("Connection failed: " . $dbConn->connect_error);
                    }

                    // Prepare ONE statement for all inserts
                    $insertSql = "INSERT INTO reviewer_certificates
                                  (reviewer_id, submission_id, review_id, context_id, template_id,
                                   date_issued, certificate_code, download_count)
                                  VALUES (?, ?, ?, ?, NULL, ?, ?, 0)";
                    $stmt = $dbConn->prepare($insertSql);
                    if (!$stmt) {
                        throw new Exception("Failed to prepare statement: " . $dbConn->error);
                    }

                    foreach ($reviewerIds as $reviewerId) {

                        // Use direct SQL query for OJS 3.4 compatibility
                        $result = $certificateDao->retrieve(
                            'SELECT ra.review_id, ra.reviewer_id, ra.submission_id
                             FROM review_assignments ra
                             INNER JOIN submissions s ON ra.submission_id = s.submission_id
                             LEFT JOIN reviewer_certificates rc ON ra.review_id = rc.review_id AND rc.certificate_type = ?
                             WHERE ra.reviewer_id = ?
                                   AND s.context_id = ?
                                   AND ra.date_completed IS NOT NULL
                                   AND rc.certificate_id IS NULL
                             LIMIT 500',
                            array('reviewer', (int) $reviewerId, (int) $context->getId())
                        );

                        if ($result) {
                            foreach ($result as $row) {
                                // Create certificate
                                $certificate = new \APP\plugins\generic\academicCertificate\classes\Certificate();
                                $certificate->setReviewerId($row->reviewer_id);
                                $certificate->setSubmissionId($row->submission_id);
                                $certificate->setReviewId($row->review_id);
                                $certificate->setContextId($context->getId());
                                $certificate->setDateIssued(\PKP\core\Core::getCurrentDate());
                                $certificate->setCertificateCode(\APP\plugins\generic\academicCertificate\classes\Certificate::generateCode());
                                $certificate->setDownloadCount(0);

                                try {
                                    $paramReviewerId = (int) $certificate->getReviewerId();
                                    $paramSubmissionId = (int) $certificate->getSubmissionId();
                                    $paramReviewId = (int) $certificate->getReviewId();
                                    $paramContextId = (int) $certificate->getContextId();
                                    $paramDateIssued = $certificate->getDateIssued();
                                    $paramCertCode = $certificate->getCertificateCode();

                                    $stmt->bind_param('iiiiss',
                                        $paramReviewerId,
                                        $paramSubmissionId,
                                        $paramReviewId,
                                        $paramContextId,
                                        $paramDateIssued,
                                        $paramCertCode
                                    );

                                    if ($stmt->execute()) {
                                        $generated++;
                                    } else {
                                        throw new Exception("Execute failed: " . $stmt->error);
                                    }
                                } catch (Throwable $insertError) {
                                    if (strpos($insertError->getMessage(), 'Duplicate') !== false) {
                                        // Certificate created by concurrent request — not an error
                                    } else {
                                        $errors[] = "Failed to create certificate for review_id {$row->review_id}";
                                    }
                                }
                            }
                        }
                    }

                    // Return response in format expected by JavaScript
                    $response = $this->createJSONMessage(true);
                    $response->setContent(array('generated' => $generated));
                    return $response;

                } catch (Throwable $e) {
                    error_log('AcademicCertificate batch generation error: ' . $e->getMessage());
                    return $this->createJSONMessage(false, 'An error occurred during batch generation. Please check the server logs.');
                } finally {
                    // Guarantee cleanup of DB resources
                    if ($stmt) {
                        $stmt->close();
                    }
                    if ($dbConn) {
                        $dbConn->close();
                    }
                    // Restore original time limit
                    set_time_limit($originalTimeLimit);
                }

            case 'issueAcceptance':
                try {
                    $this->addLocaleData();
                    $this->ensurePluginSchema();
                    $this->registerCertificateDao();

                    $context = $request->getContext();
                    $user = $request->getUser();
                    if (!$context) {
                        return $this->createJSONMessage(false, __('plugins.generic.academicCertificate.error.noContext'));
                    }
                    if (!$user) {
                        return $this->createJSONMessage(false, __('plugins.generic.academicCertificate.error.accessDenied'));
                    }

                    require_once($this->getPluginPath() . '/classes/services/MyCertificateListService.php');
                    $listService = new \APP\plugins\generic\academicCertificate\classes\services\MyCertificateListService();
                    if (!$listService->userHasManagerAccess($user, $context)) {
                        return $this->createJSONMessage(false, __('plugins.generic.academicCertificate.error.accessDenied'));
                    }

                    $submissionId = (int) $request->getUserVar('submissionId');
                    if ($submissionId < 1) {
                        return $this->createJSONMessage(false, __('plugins.generic.academicCertificate.error.invalidSubmissionId'));
                    }

                    $certificateDao = $this->getCertificateDao();
                    if (!$certificateDao) {
                        return $this->createJSONMessage(false, __('plugins.generic.academicCertificate.error.daoNotAvailable'));
                    }

                    $subResult = $certificateDao->retrieve(
                        'SELECT submission_id FROM submissions WHERE submission_id = ? AND context_id = ? LIMIT 1',
                        array($submissionId, (int) $context->getId())
                    );
                    if (!$subResult || !$subResult->current()) {
                        return $this->createJSONMessage(false, __('plugins.generic.academicCertificate.error.invalidSubmissionId'));
                    }

                    require_once($this->getPluginPath() . '/classes/Certificate.php');
                    require_once($this->getPluginPath() . '/classes/services/AcceptanceEligibilityService.php');
                    require_once($this->getPluginPath() . '/classes/services/CertificateRecordService.php');

                    $existing = $certificateDao->getBySubmissionIdAndType(
                        $submissionId,
                        (int) $context->getId(),
                        \APP\plugins\generic\academicCertificate\classes\Certificate::TYPE_ACCEPTANCE
                    );
                    if ($existing) {
                        return $this->createJSONMessage(true, array(
                            'certificateId' => (int) $existing->getCertificateId(),
                            'message' => __('plugins.generic.academicCertificate.issueAcceptance.alreadyExists'),
                        ));
                    }

                    $authorUserId = \APP\plugins\generic\academicCertificate\classes\services\AcceptanceEligibilityService::resolveAuthorUserIdForSubmission(
                        $submissionId,
                        (int) $context->getId(),
                        $certificateDao
                    );
                    if (!$authorUserId) {
                        return $this->createJSONMessage(false, __('plugins.generic.academicCertificate.error.noAuthorForSubmission'));
                    }

                    $acceptanceDate = \APP\plugins\generic\academicCertificate\classes\services\AcceptanceEligibilityService::getAcceptanceDateForSubmission(
                        $submissionId,
                        (int) $context->getId(),
                        $certificateDao
                    );

                    $locale = null;
                    if (class_exists('PKP\facades\Locale')) {
                        try {
                            $locale = \PKP\facades\Locale::getLocale();
                        } catch (\Throwable $e) {
                            // ignore
                        }
                    } elseif (class_exists('AppLocale', false)) {
                        $locale = \AppLocale::getLocale();
                    }

                    $certificate = \APP\plugins\generic\academicCertificate\classes\services\CertificateRecordService::createAcceptanceCertificate(
                        $this,
                        $authorUserId,
                        $submissionId,
                        (int) $context->getId(),
                        $locale,
                        $acceptanceDate
                    );
                    $certificateDao->insertObject($certificate);

                    return $this->createJSONMessage(true, array(
                        'certificateId' => (int) $certificate->getCertificateId(),
                        'message' => __('plugins.generic.academicCertificate.issueAcceptance.success'),
                    ));
                } catch (\Throwable $e) {
                    error_log('AcademicCertificate issueAcceptance error: ' . $e->getMessage());
                    return $this->createJSONMessage(false, $e->getMessage());
                }

            default:
                return parent::manage($args, $request);
        }
    }

    /**
     * Setup custom handlers
     */
    public function setupHandler($hookName, $params) {
        $page = $params[0];

        if ($page == 'certificate') {
            // OJS 3.3 compatibility: re-load locale data for handler rendering.
            // Plugin locale may not be usable on public pages if the lazy-loaded
            // locale file was registered with a relative path before the working
            // directory or locale context was finalized.
            $this->ensurePluginLocaleLoaded();

            require_once($this->getPluginPath() . '/controllers/CertificateHandler.php');

            // Check if handler class file was loaded (use FQN for namespaced class)
            $handlerClass = 'APP\\plugins\\generic\\academicCertificate\\controllers\\CertificateHandler';
            if (!class_exists($handlerClass)) {
                error_log('AcademicCertificate: ERROR - CertificateHandler class not found after import!');
                return false;
            }

            // OJS 3.5+ uses direct handler assignment; OJS 3.3/3.4 use HANDLER_CLASS constant
            // Use array_key_exists() because isset() returns false for null values
            // In OJS 3.5, $params[3] exists but is null initially
            if (array_key_exists(3, $params)) {
                // OJS 3.5+ pattern: assign handler via reference (per PKP Plugin Guide)
                // Must use =& to get reference, then assign to modify original
                $handler =& $params[3];
                $handler = new \APP\plugins\generic\academicCertificate\controllers\CertificateHandler();
                $handler->setPlugin($this);
            } else {
                // OJS 3.3/3.4 pattern: use HANDLER_CLASS constant (must be FQN)
                \APP\plugins\generic\academicCertificate\controllers\CertificateHandler::setPluginInstance($this);
                if (!defined('HANDLER_CLASS')) {
                    define('HANDLER_CLASS', 'APP\\plugins\\generic\\academicCertificate\\controllers\\CertificateHandler');
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Add My Certificates link to the user profile page.
     * @param string $hookName
     * @param array $params
     * @return bool
     */
    public function addProfileCertificatesLink($hookName, $params) {
        $this->ensurePluginLocaleLoaded();

        $request = Application::get()->getRequest();
        $context = $request->getContext();
        if (!$context || !$request->getUser()) {
            return false;
        }

        $template = $params[1];
        if (strpos($template, 'user/profile.tpl') === false) {
            return false;
        }

        $templateMgr = $params[0];
        $linkHtml = $templateMgr->fetch($this->getTemplateResource('profileMyCertificates.tpl'));

        if (isset($params[2]) && is_string($params[2])) {
            $needle = '<div id="profileTabs"';
            $pos = strpos($params[2], $needle);
            if ($pos !== false) {
                $params[2] = substr($params[2], 0, $pos) . $linkHtml . "\n" . substr($params[2], $pos);
            } else {
                $params[2] .= "\n" . $linkHtml;
            }
        } else {
            echo "\n" . $linkHtml;
        }

        return false;
    }

    /**
     * Add certificate download button to reviewer dashboard
     */
    public function addCertificateButton($hookName, $params) {
        // Ensure locale is loaded for non-English UIs (fixes ##key## display in uk_UA etc.)
        $this->ensurePluginLocaleLoaded();

        $request = Application::get()->getRequest();
        $templateMgr = $params[0];
        $template = $params[1];

        // Exclude our own templates to prevent interference with Smarty path resolution
        if (strpos($template, 'verify.tpl') !== false || strpos($template, 'myCertificates.tpl') !== false) {
            return false;
        }

        // Check if this is the reviewer dashboard - support multiple template patterns
        // Different templates for different OJS versions and review states
        $reviewerTemplates = array(
            // OJS 3.3/3.4 templates
            'reviewer/review/reviewCompleted.tpl',
            'reviewer/review/step3.tpl',
            'reviewer/review/step4.tpl',
            'reviewer/review/reviewStepHeader.tpl',

            // OJS 3.5 templates - may use different paths
            'reviewer/review/step4.tpl',  // Review completion step
            'reviewer/review/complete.tpl',  // Potential OJS 3.5 completion template
            'reviewer/review/reviewStep4.tpl',  // Alternative naming
            'reviewer/review/reviewComplete.tpl',  // Alternative naming
        );

        if (!in_array($template, $reviewerTemplates)) {
            return false;
        }

        // Get template variable - might be ReviewAssignment or Submission object
        $templateVar = $templateMgr->getTemplateVars('reviewAssignment');
        if (!$templateVar) {
            $templateVar = $templateMgr->getTemplateVars('submission');
        }

        if (!$templateVar) {
            return false;
        }

        // Check the type of object we received
        $reviewAssignment = null;

        if ($templateVar instanceof \APP\submission\Submission) {
            // Template variable is a Submission - need to fetch ReviewAssignment from database

            // Get current user
            $user = $request->getUser();
            if (!$user) {
                return false;
            }

            // Fetch review assignment for this submission and user
            // Use direct SQL query for OJS 3.5 compatibility (ReviewAssignmentDAO not available)
            $certificateDao = $this->getCertificateDao();
            if (!$certificateDao) {
                return false;
            }
            $result = $certificateDao->retrieve(
                'SELECT * FROM review_assignments WHERE submission_id = ? AND reviewer_id = ?',
                array((int) $templateVar->getId(), (int) $user->getId())
            );

            if ($result) {
                $row = $result->current();
                if ($row) {
                    $reviewAssignment = $certificateDao->reviewAssignmentFromRow($row);
                }
            }

            if (!$reviewAssignment) {
                return false;
            }
        } elseif (method_exists($templateVar, 'getDateCompleted') && method_exists($templateVar, 'getReviewerId')) {
            // Template variable is already a ReviewAssignment
            $reviewAssignment = $templateVar;
        } else {
            // Unknown object type
            return false;
        }

        // Now we have a valid ReviewAssignment object - check if review is completed

        if (!$reviewAssignment->getDateCompleted()) {
            return false;
        }

        // Check if certificate exists or if reviewer is eligible
        $certificateDao = $this->getCertificateDao();
        $certificate = $certificateDao->getByReviewId($reviewAssignment->getId());


        // Only show button if certificate exists or reviewer is eligible
        $isEligible = $this->isEligibleForCertificate($reviewAssignment);


        if ($certificate || $isEligible) {
            // Load CSS and JS assets
            $this->addScript($request);

            // Assign template variables
            $templateMgr->assign('showCertificateButton', true);
            $templateMgr->assign('certificateExists', (bool)$certificate);
            $templateMgr->assign('certificateUrl', $request->url(null, 'certificate', 'download', array($reviewAssignment->getId())));
            $templateMgr->assign('reviewAssignmentId', $reviewAssignment->getId());

            // Fetch the button HTML
            $additionalContent = $templateMgr->fetch($this->getTemplateResource('reviewerDashboard.tpl'));

            // Store content in template variable for Smarty templates to include
            $templateMgr->assign('academicCertificateButtonHTML', $additionalContent);

            // Multiple injection strategies for maximum compatibility across OJS versions

            // Strategy 1: Try to modify output buffer (params[2])
            if (isset($params[2]) && is_string($params[2])) {
                $params[2] .= "\n" . $additionalContent;
            }
            // Strategy 2: Direct echo (works in most template hooks due to output buffering)
            else {
                echo "\n" . $additionalContent;
            }
        }

        return false;
    }

    /**
     * Ensure plugin locale data is loaded with absolute path fallback.
     * Required for OJS 3.3 where relative path locale registration
     * can fail on public pages and reviewer dashboard.
     */
    private function ensurePluginLocaleLoaded() {
        // Standard reload attempt
        $this->addLocaleData();

        // Check if translations are actually available
        $testKey = 'plugins.generic.academicCertificate.certificateAvailable';
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
     * Handle review completion
     */
    public function handleReviewComplete($hookName, $params) {
        $reviewAssignment = $params[0];

        // Check if review is newly completed
        if ($reviewAssignment->getDateCompleted() && !$reviewAssignment->getDateNotified()) {
            // Check eligibility
            if ($this->isEligibleForCertificate($reviewAssignment)) {
                $this->createCertificateRecord($reviewAssignment);
                $this->sendCertificateNotification($reviewAssignment);
            }
        }

        return false;
    }

    /**
     * Check if reviewer is eligible for certificate
     */
    private function isEligibleForCertificate($reviewAssignment) {
        $context = Application::get()->getRequest()->getContext();
        $minimumReviews = $this->getSetting($context->getId(), 'minimumReviews');

        if (!$minimumReviews) {
            $minimumReviews = 1;
        }

        // Count completed reviews for this reviewer
        // Use direct SQL query for OJS 3.5 compatibility (ReviewAssignmentDAO not available)
        $certificateDao = $this->getCertificateDao();
        if (!$certificateDao) {
            return false;
        }
        $result = $certificateDao->retrieve(
            'SELECT COUNT(*) AS cnt FROM review_assignments WHERE reviewer_id = ? AND date_completed IS NOT NULL',
            array((int) $reviewAssignment->getReviewerId())
        );

        $row = $result ? $result->current() : null;
        $completedReviews = $row ? (int) $row->cnt : 0;


        return $completedReviews >= $minimumReviews;
    }

    /**
     * Create certificate record
     */
    private function createCertificateRecord($reviewAssignment) {
        $certificateDao = $this->getCertificateDao();
        if (!$certificateDao) {
            error_log('AcademicCertificate: CertificateDAO not available for certificate creation');
            return;
        }

        if ($certificateDao->getByReviewId($reviewAssignment->getId())) {
            return;
        }

        require_once($this->getPluginPath() . '/classes/services/CertificateRecordService.php');
        $context = Application::get()->getRequest()->getContext();
        $contextId = $context ? (int) $context->getId() : 0;
        $certificate = \APP\plugins\generic\academicCertificate\classes\services\CertificateRecordService::createReviewerCertificate(
            $this,
            $reviewAssignment->getReviewerId(),
            $reviewAssignment->getSubmissionId(),
            $reviewAssignment->getId(),
            $contextId
        );

        try {
            $certificateDao->insertObject($certificate);
        } catch (\Throwable $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                return;
            }
            throw $e;
        }
    }

    /**
     * Send certificate notification email
     */
    private function sendCertificateNotification($reviewAssignment) {
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        // OJS 3.3 compatibility
        if (class_exists('APP\facades\Repo')) {
            $reviewer = \APP\facades\Repo::user()->get($reviewAssignment->getReviewerId());
        } else {
            $userDao = DAORegistry::getDAO('UserDAO');
            $reviewer = $userDao->getById($reviewAssignment->getReviewerId());
        }

        if (!$reviewer) {
            error_log('AcademicCertificate: Cannot send notification - reviewer ID ' . $reviewAssignment->getReviewerId() . ' not found');
            return;
        }

        // OJS 3.5+ — use Mailable system
        if (class_exists('PKP\mail\Mailable')) {
            require_once($this->getPluginPath() . '/classes/AcademicCertificateMailable.php');
            $mailable = new \APP\plugins\generic\academicCertificate\classes\AcademicCertificateMailable();
            $mailable->setData([
                'reviewerName' => $reviewer->getFullName(),
                'certificateUrl' => $request->url(null, 'certificate', 'download', array($reviewAssignment->getId())),
                'journalName' => $context->getLocalizedName(),
                'journalUrl' => $request->getBaseUrl() . '/' . $context->getPath(),
            ]);
            $template = \APP\facades\Repo::emailTemplate()->getByKey(
                $context->getId(), $mailable::getEmailTemplateKey()
            );
            $locale = $context->getPrimaryLocale();
            $mailable
                ->sender($request->getUser())
                ->to($reviewer->getEmail(), $reviewer->getFullName())
                ->subject($template->getLocalizedData('subject', $locale))
                ->body($template->getLocalizedData('body', $locale));
            \Illuminate\Support\Facades\Mail::send($mailable);
            return;
        }

        // OJS 3.3/3.4 — use legacy MailTemplate
        if (class_exists('PKP\mail\MailTemplate')) {
            $mail = new \PKP\mail\MailTemplate('REVIEWER_CERTIFICATE_AVAILABLE');
        } else {
            import('lib.pkp.classes.mail.MailTemplate');
            $mail = new \MailTemplate('REVIEWER_CERTIFICATE_AVAILABLE');
        }

        $mail->setReplyTo($context->getData('contactEmail'), $context->getData('contactName'));
        $mail->addRecipient($reviewer->getEmail(), $reviewer->getFullName());

        $mail->assignParams(array(
            'reviewerName' => $reviewer->getFullName(),
            'certificateUrl' => $request->url(null, 'certificate', 'download', array($reviewAssignment->getId())),
            'journalName' => $context->getLocalizedName(),
            'journalUrl' => $request->getBaseUrl() . '/' . $context->getPath(),
        ));

        $mail->send($request);
    }

    /**
     * Add JavaScript to page
     */
    private function addScript($request) {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->addJavaScript(
            'academicCertificateJS',
            $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/certificate.js',
            array('contexts' => 'frontend')
        );

        $templateMgr->addStyleSheet(
            'academicCertificateCSS',
            $request->getBaseUrl() . '/' . $this->getPluginPath() . '/css/certificate.css',
            array('contexts' => 'frontend')
        );
    }

    /**
     * Register CertificateDAO if not already registered.
     * OJS 3.3: never call getDAO() to probe — unknown names trigger fatalError().
     */
    private function registerCertificateDao() {
        if (class_exists('PKP\db\DAORegistry')) {
            $daos = DAORegistry::getDAOs();
        } else {
            $daos = \DAORegistry::getDAOs();
        }
        if (isset($daos['CertificateDAO'])) {
            return;
        }

        require_once($this->getPluginPath() . '/classes/CertificateDAO.php');
        $certificateDao = new \APP\plugins\generic\academicCertificate\classes\CertificateDAO();
        if (class_exists('PKP\db\DAORegistry')) {
            DAORegistry::registerDAO('CertificateDAO', $certificateDao);
        } else {
            \DAORegistry::registerDAO('CertificateDAO', $certificateDao);
        }
    }

    /**
     * Return CertificateDAO, registering it first if needed.
     * @return \APP\plugins\generic\academicCertificate\classes\CertificateDAO|null
     */
    public function getCertificateDao() {
        try {
            $this->registerCertificateDao();
            if (class_exists('PKP\db\DAORegistry')) {
                return DAORegistry::getDAO('CertificateDAO');
            }
            return \DAORegistry::getDAO('CertificateDAO');
        } catch (\Throwable $e) {
            error_log('AcademicCertificate: getCertificateDao failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Ensure plugin database tables exist (manual enable on existing OJS installs).
     */
    private function ensurePluginSchema() {
        try {
            if (class_exists('PKP\db\DAORegistry')) {
                $userDao = DAORegistry::getDAO('UserDAO');
            } else {
                $userDao = \DAORegistry::getDAO('UserDAO');
            }
            if (!$userDao) {
                return;
            }

            $result = $userDao->retrieve(
                "SELECT COUNT(*) AS cnt FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = 'reviewer_certificates'"
            );
            $row = $result ? $result->current() : null;
            $cnt = 0;
            if ($row) {
                if (is_array($row)) {
                    $cnt = (int) ($row['cnt'] ?? 0);
                } else {
                    $cnt = (int) ($row->cnt ?? 0);
                }
            }
            if ($cnt > 0) {
                $this->runUpgradeMigration();
                return;
            }

            $migration = $this->getInstallMigration();
            if ($migration) {
                $migration->up();
                error_log('AcademicCertificate: Database tables created via ensurePluginSchema()');
            }
            $this->runUpgradeMigration();
        } catch (\Throwable $e) {
            error_log('AcademicCertificate: ensurePluginSchema failed: ' . $e->getMessage());
        }
    }

    /**
     * Run Phase 1 schema upgrade (idempotent).
     */
    private function runUpgradeMigration() {
        try {
            require_once($this->getPluginPath() . '/classes/migration/AcademicCertificateUpgradeMigration.php');
            $upgrade = new \APP\plugins\generic\academicCertificate\classes\migration\AcademicCertificateUpgradeMigration();
            $upgrade->up();
        } catch (\Throwable $e) {
            error_log('AcademicCertificate: runUpgradeMigration failed: ' . $e->getMessage());
        }
    }

    /**
     * Get the installation migration for this plugin
     * @return \Illuminate\Database\Migrations\Migration
     */
    public function getInstallMigration() {
        try {
            require_once($this->getPluginPath() . '/classes/migration/AcademicCertificateInstallMigration.php');
            return new \APP\plugins\generic\academicCertificate\classes\migration\AcademicCertificateInstallMigration();
        } catch (\Throwable $e) {
            error_log('AcademicCertificate: Failed to load migration: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create JSONMessage - OJS 3.3 compatibility helper
     * @param $status bool
     * @param $content mixed
     * @return JSONMessage
     */
    public function createJSONMessage($status, $content = '') {
        if (class_exists('PKP\core\JSONMessage')) {
            return new \PKP\core\JSONMessage($status, $content);
        } else {
            import('lib.pkp.classes.core.JSONMessage');
            return new \JSONMessage($status, $content);
        }
    }
}
