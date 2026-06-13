<?php
/**
 * @file plugins/generic/academicCertificate/classes/form/CertificateSettingsForm.php
 *
 * Copyright (c) 2024
 * Distributed under the GNU GPL v3.
 *
 * @class CertificateSettingsForm
 * @ingroup plugins_generic_academicCertificate
 *
 * @brief Form for managing certificate settings
 */

namespace APP\plugins\generic\academicCertificate\classes\form;

use PKP\form\Form;
use PKP\form\validation\FormValidatorPost;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidator;
use PKP\form\validation\FormValidatorCustom;
use PKP\db\DAORegistry;
use PKP\core\Core;
use APP\core\Application;
use APP\template\TemplateManager;
use APP\plugins\generic\academicCertificate\classes\CertificateLayout;
use Exception;

class CertificateSettingsForm extends Form {

    /** @var AcademicCertificatePlugin */
    private $plugin;

    /** @var int */
    private $contextId;

    /** Certificate types shown in the visual designer (ordered). */
    private static $certificateTypes = array(
        'reviewer' => array(
            'bgField' => 'reviewerBackgroundImage',
            'headerField' => 'headerText',
            'bodyField' => 'bodyTemplate',
            'footerField' => 'footerText',
            'layoutField' => 'layoutReviewer',
            'variablesKey' => 'reviewer',
            'defaultBodyMethod' => 'getDefaultBodyTemplate',
        ),
        'acceptance' => array(
            'bgField' => 'acceptanceBackgroundImage',
            'headerField' => 'acceptanceHeaderText',
            'bodyField' => 'acceptanceBodyTemplate',
            'footerField' => 'acceptanceFooterText',
            'layoutField' => 'layoutAcceptance',
            'variablesKey' => 'acceptance',
            'defaultBodyMethod' => 'getDefaultAcceptanceBodyTemplate',
        ),
        'author' => array(
            'bgField' => 'authorBackgroundImage',
            'headerField' => 'authorHeaderText',
            'bodyField' => 'authorBodyTemplate',
            'footerField' => 'authorFooterText',
            'layoutField' => 'layoutAuthor',
            'variablesKey' => 'author',
            'defaultBodyMethod' => 'getDefaultAuthorBodyTemplate',
        ),
        'editor' => array(
            'bgField' => 'editorBackgroundImage',
            'headerField' => 'editorHeaderText',
            'bodyField' => 'editorBodyTemplate',
            'footerField' => 'editorFooterText',
            'layoutField' => 'layoutEditor',
            'variablesKey' => 'reviewer',
            'defaultBodyMethod' => 'getDefaultEditorBodyTemplate',
        ),
    );

    private static $backgroundImageFields = array(
        'backgroundImage' => 'default',
        'reviewerBackgroundImage' => 'reviewer',
        'acceptanceBackgroundImage' => 'acceptance',
        'authorBackgroundImage' => 'author',
        'editorBackgroundImage' => 'editor',
    );

    /**
     * Constructor
     * @param $plugin AcademicCertificatePlugin
     * @param $contextId int
     */
    public function __construct($plugin, $contextId) {
        $templateFile = dirname(__DIR__, 2) . '/templates/certificateSettings.tpl';
        if (file_exists($templateFile)) {
            $template = 'file:' . str_replace('\\', '/', $templateFile);
        } else {
            $template = $plugin->getTemplateResource('certificateSettings.tpl');
        }
        parent::__construct($template);

        $this->plugin = $plugin;
        $this->contextId = $contextId;

        // Add form validators - OJS 3.4+/3.3 compatibility
        if (class_exists('PKP\form\validation\FormValidatorPost')) {
            $this->addCheck(new FormValidatorPost($this));
            $this->addCheck(new FormValidatorCSRF($this));
            $this->addCheck(new FormValidator($this, 'headerText', 'required', 'plugins.generic.academicCertificate.settings.headerTextRequired'));
            $this->addCheck(new FormValidator($this, 'bodyTemplate', 'required', 'plugins.generic.academicCertificate.settings.bodyTemplateRequired'));
            $this->addCheck(new FormValidatorCustom($this, 'minimumReviews', 'required', 'plugins.generic.academicCertificate.settings.minimumReviewsInvalid', function($value) {
                return is_numeric($value) && $value >= 1;
            }));
        } elseif (function_exists('import')) {
            import('lib.pkp.classes.form.validation.FormValidatorPost');
            import('lib.pkp.classes.form.validation.FormValidatorCSRF');
            import('lib.pkp.classes.form.validation.FormValidator');
            import('lib.pkp.classes.form.validation.FormValidatorCustom');
            $this->addCheck(new \FormValidatorPost($this));
            $this->addCheck(new \FormValidatorCSRF($this));
            $this->addCheck(new \FormValidator($this, 'headerText', 'required', 'plugins.generic.academicCertificate.settings.headerTextRequired'));
            $this->addCheck(new \FormValidator($this, 'bodyTemplate', 'required', 'plugins.generic.academicCertificate.settings.bodyTemplateRequired'));
            $this->addCheck(new \FormValidatorCustom($this, 'minimumReviews', 'required', 'plugins.generic.academicCertificate.settings.minimumReviewsInvalid', function($value) {
                return is_numeric($value) && $value >= 1;
            }));
        }
    }

    /**
     * @copydoc Form::initData()
     */
    public function initData() {
        try {
            $this->setData('headerText', $this->plugin->getSetting($this->contextId, 'headerText') ?? '');
            $this->setData('bodyTemplate', $this->plugin->getSetting($this->contextId, 'bodyTemplate') ?? '');
            $this->setData('footerText', $this->plugin->getSetting($this->contextId, 'footerText') ?? '');
            $this->setData('acceptanceHeaderText', $this->plugin->getSetting($this->contextId, 'acceptanceHeaderText') ?? '');
            $this->setData('acceptanceBodyTemplate', $this->plugin->getSetting($this->contextId, 'acceptanceBodyTemplate') ?? '');
            $this->setData('acceptanceFooterText', $this->plugin->getSetting($this->contextId, 'acceptanceFooterText') ?? '');
            $this->setData('authorHeaderText', $this->plugin->getSetting($this->contextId, 'authorHeaderText') ?? '');
            $this->setData('authorBodyTemplate', $this->plugin->getSetting($this->contextId, 'authorBodyTemplate') ?? '');
            $this->setData('authorFooterText', $this->plugin->getSetting($this->contextId, 'authorFooterText') ?? '');
            $this->setData('editorHeaderText', $this->plugin->getSetting($this->contextId, 'editorHeaderText') ?? '');
            $this->setData('editorBodyTemplate', $this->plugin->getSetting($this->contextId, 'editorBodyTemplate') ?? '');
            $this->setData('editorFooterText', $this->plugin->getSetting($this->contextId, 'editorFooterText') ?? '');
            foreach (self::$certificateTypes as $typeId => $cfg) {
                $layoutKey = $cfg['layoutField'];
                $stored = $this->plugin->getSetting($this->contextId, $layoutKey);
                $this->setData($layoutKey, $stored ? $stored : CertificateLayout::encode(CertificateLayout::getDefaultBlocks()));
            }
            $this->setData('fontFamily', $this->plugin->getSetting($this->contextId, 'fontFamily') ?? 'dejavusans');
            $this->setData('fontSize', $this->plugin->getSetting($this->contextId, 'fontSize') ?? 12);
            $this->setData('textColorR', $this->plugin->getSetting($this->contextId, 'textColorR') ?? 0);
            $this->setData('textColorG', $this->plugin->getSetting($this->contextId, 'textColorG') ?? 0);
            $this->setData('textColorB', $this->plugin->getSetting($this->contextId, 'textColorB') ?? 0);
            $this->setData('minimumReviews', $this->plugin->getSetting($this->contextId, 'minimumReviews') ?? 1);
            $this->setData('certificateNumberPrefix', $this->plugin->getSetting($this->contextId, 'certificateNumberPrefix') ?? 'ACM');
            $this->setData('enableReviewerCertificates', $this->plugin->getSetting($this->contextId, 'enableReviewerCertificates') ?? true);
            $this->setData('enableAcceptanceCertificates', $this->plugin->getSetting($this->contextId, 'enableAcceptanceCertificates') ?? true);
            $this->setData('enableAuthorCertificates', $this->plugin->getSetting($this->contextId, 'enableAuthorCertificates') ?? true);
            $this->setData('enableEditorCertificates', $this->plugin->getSetting($this->contextId, 'enableEditorCertificates') ?? true);
            $this->setData('hideReviewerSubmissionTitle', $this->plugin->getSetting($this->contextId, 'hideReviewerSubmissionTitle') ?? true);
            $this->setData('includeQRCode', $this->plugin->getSetting($this->contextId, 'includeQRCode') ?? false);
            $this->setData('pageOrientation', $this->plugin->getSetting($this->contextId, 'pageOrientation') ?? 'L');
            foreach (array_keys(self::$backgroundImageFields) as $field) {
                $this->setData($field, $this->plugin->getSetting($this->contextId, $field) ?? '');
            }
        } catch (Exception $e) {
            error_log('AcademicCertificate: Error initializing form data: ' . $e->getMessage());
            // Set default values on error
            $this->setData('headerText', '');
            $this->setData('bodyTemplate', '');
            $this->setData('footerText', '');
            $this->setData('acceptanceHeaderText', '');
            $this->setData('acceptanceBodyTemplate', '');
            $this->setData('acceptanceFooterText', '');
            $this->setData('authorHeaderText', '');
            $this->setData('authorBodyTemplate', '');
            $this->setData('authorFooterText', '');
            $this->setData('editorHeaderText', '');
            $this->setData('editorBodyTemplate', '');
            $this->setData('editorFooterText', '');
            foreach (self::$certificateTypes as $typeId => $cfg) {
                $this->setData($cfg['layoutField'], CertificateLayout::encode(CertificateLayout::getDefaultBlocks()));
            }
            $this->setData('fontFamily', 'dejavusans');
            $this->setData('fontSize', 12);
            $this->setData('textColorR', 0);
            $this->setData('textColorG', 0);
            $this->setData('textColorB', 0);
            $this->setData('minimumReviews', 1);
            $this->setData('certificateNumberPrefix', 'ACM');
            $this->setData('enableReviewerCertificates', true);
            $this->setData('enableAcceptanceCertificates', true);
            $this->setData('enableAuthorCertificates', true);
            $this->setData('enableEditorCertificates', true);
            $this->setData('hideReviewerSubmissionTitle', true);
            $this->setData('includeQRCode', false);
            $this->setData('pageOrientation', 'L');
            foreach (array_keys(self::$backgroundImageFields) as $field) {
                $this->setData($field, '');
            }
        }
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData() {
        $this->readUserVars(array(
            'headerText',
            'bodyTemplate',
            'footerText',
            'acceptanceHeaderText',
            'acceptanceBodyTemplate',
            'acceptanceFooterText',
            'authorHeaderText',
            'authorBodyTemplate',
            'authorFooterText',
            'editorHeaderText',
            'editorBodyTemplate',
            'editorFooterText',
            'layoutReviewer',
            'layoutAcceptance',
            'layoutAuthor',
            'layoutEditor',
            'fontFamily',
            'fontSize',
            'textColorR',
            'textColorG',
            'textColorB',
            'minimumReviews',
            'certificateNumberPrefix',
            'enableReviewerCertificates',
            'enableAcceptanceCertificates',
            'enableAuthorCertificates',
            'enableEditorCertificates',
            'hideReviewerSubmissionTitle',
            'includeQRCode',
            'pageOrientation'
        ));

        foreach (self::$backgroundImageFields as $field => $prefix) {
            $existing = $this->plugin->getSetting($this->contextId, $field);
            if ($existing) {
                $this->setData($field, $existing);
            }
            if (isset($_FILES[$field]) && $_FILES[$field]['error'] == UPLOAD_ERR_OK) {
                $this->handleBackgroundImageUpload($field, $prefix);
            }
        }
    }

    /**
     * Handle background image upload for a specific form field.
     *
     * @param string $formField
     * @param string $filenamePrefix
     */
    private function handleBackgroundImageUpload($formField, $filenamePrefix) {
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        $tmpFile = $_FILES[$formField]['tmp_name'];

        // Validate actual file size via filesystem (not client-reported $_FILES['size'])
        $actualSize = filesize($tmpFile);
        if ($actualSize === false || $actualSize > 5 * 1024 * 1024) {
            $this->addError($formField, 'File size must be less than 5MB');
            return;
        }

        // Validate image content using getimagesize() instead of client-reported MIME
        $imageInfo = @getimagesize($tmpFile);
        if ($imageInfo === false) {
            $this->addError($formField, __('plugins.generic.academicCertificate.settings.invalidImageType'));
            return;
        }

        // Whitelist: only JPEG and PNG (drop GIF — TCPDF has inconsistent GIF support)
        $mimeToExt = array(
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        );

        $detectedMime = $imageInfo['mime'];
        if (!isset($mimeToExt[$detectedMime])) {
            $this->addError($formField, __('plugins.generic.academicCertificate.settings.invalidImageType'));
            return;
        }

        // Derive extension from detected MIME type, not from user filename
        $extension = $mimeToExt[$detectedMime];

        // Create upload directory if it doesn't exist - OJS 3.3 compatibility
        if (class_exists('PKP\core\Core')) {
            $baseDir = Core::getBaseDir();
        } else {
            $baseDir = \Core::getBaseDir();
        }
        $uploadDir = $baseDir . '/files/journals/' . $context->getId() . '/academicCertificate';

        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename using safe extension
        $filename = 'background_' . $filenamePrefix . '_' . time() . '.' . $extension;
        $targetPath = $uploadDir . '/' . $filename;

        // Move uploaded file
        if (move_uploaded_file($tmpFile, $targetPath)) {
            $this->setData($formField, $targetPath);
        } else {
            error_log('AcademicCertificate: File upload failed for ' . $formField);
            $this->addError($formField, __('plugins.generic.academicCertificate.settings.uploadFailed'));
        }
    }

    /**
     * @copydoc Form::fetch()
     */
    public function fetch($request, $template = null, $display = false) {
        $templateMgr = TemplateManager::getManager($request);

        $templateMgr->assign('pluginName', $this->plugin->getName());
        $templateMgr->assign('contextId', $this->contextId);

        // Available fonts
        $fontOptions = array(
            'helvetica' => __('plugins.generic.academicCertificate.settings.font.helvetica'),
            'times' => __('plugins.generic.academicCertificate.settings.font.times'),
            'courier' => __('plugins.generic.academicCertificate.settings.font.courier'),
            'dejavusans' => __('plugins.generic.academicCertificate.settings.font.dejavusans'),
        );
        $templateMgr->assign('fontOptions', $fontOptions);

        // Available page orientations
        $orientationOptions = array(
            'P' => __('plugins.generic.academicCertificate.settings.orientation.portrait'),
            'L' => __('plugins.generic.academicCertificate.settings.orientation.landscape'),
        );
        $templateMgr->assign('orientationOptions', $orientationOptions);

        // Available template variables
        $templateVariables = array(
            '{{$reviewerName}}',
            '{{$reviewerFirstName}}',
            '{{$reviewerLastName}}',
            '{{$journalName}}',
            '{{$journalAcronym}}',
            '{{$submissionTitle}}',
            '{{$reviewDate}}',
            '{{$reviewYear}}',
            '{{$currentDate}}',
            '{{$currentYear}}',
            '{{$certificateCode}}',
            '{{$certificateNumber}}',
        );
        $templateMgr->assign('templateVariables', $templateVariables);

        $acceptanceTemplateVariables = array(
            '{{$articleTitle}}',
            '{{$authors}}',
            '{{$authorName}}',
            '{{$correspondingAuthor}}',
            '{{$journalName}}',
            '{{$journalAcronym}}',
            '{{$acceptanceDate}}',
            '{{$certificateCode}}',
            '{{$certificateNumber}}',
            '{{$currentDate}}',
            '{{$currentYear}}',
        );
        $templateMgr->assign('acceptanceTemplateVariables', $acceptanceTemplateVariables);

        // Default templates
        require_once(dirname(__FILE__, 2) . '/CertificateGenerator.php');
        $defaultBodyTemplate = \APP\plugins\generic\academicCertificate\classes\CertificateGenerator::getDefaultBodyTemplate();
        $defaultAcceptanceBodyTemplate = \APP\plugins\generic\academicCertificate\classes\CertificateGenerator::getDefaultAcceptanceBodyTemplate();

        $templateMgr->assign('defaultBodyTemplate', $defaultBodyTemplate);
        $templateMgr->assign('defaultAcceptanceBodyTemplate', $defaultAcceptanceBodyTemplate);
        $defaultAuthorBodyTemplate = \APP\plugins\generic\academicCertificate\classes\CertificateGenerator::getDefaultAuthorBodyTemplate();
        $templateMgr->assign('defaultAuthorBodyTemplate', $defaultAuthorBodyTemplate);
        $defaultEditorBodyTemplate = \APP\plugins\generic\academicCertificate\classes\CertificateGenerator::getDefaultEditorBodyTemplate();
        $templateMgr->assign('defaultEditorBodyTemplate', $defaultEditorBodyTemplate);

        $templateMgr->assign('pluginUrl', $request->getBaseUrl() . '/' . str_replace('\\', '/', $this->plugin->getPluginPath()));
        $templateMgr->assign('a4LandscapeWidthMm', CertificateLayout::PAGE_WIDTH_MM);
        $templateMgr->assign('a4LandscapeHeightMm', CertificateLayout::PAGE_HEIGHT_MM);
        $templateMgr->assign('a4LandscapeWidthPx', CertificateLayout::PAGE_WIDTH_PX_300DPI);
        $templateMgr->assign('a4LandscapeHeightPx', CertificateLayout::PAGE_HEIGHT_PX_300DPI);

        $variablesByKey = array(
            'reviewer' => $templateVariables,
            'acceptance' => $acceptanceTemplateVariables,
            'author' => $authorTemplateVariables,
        );

        $bgFieldLabels = array(
            'reviewerBackgroundImage' => 'plugins.generic.academicCertificate.settings.reviewerBackgroundImage',
            'acceptanceBackgroundImage' => 'plugins.generic.academicCertificate.settings.acceptanceBackgroundImage',
            'authorBackgroundImage' => 'plugins.generic.academicCertificate.settings.authorBackgroundImage',
            'editorBackgroundImage' => 'plugins.generic.academicCertificate.settings.editorBackgroundImage',
        );

        $certificateTypesForDesigner = array();
        $backgroundPreviewUrls = array();
        foreach (self::$certificateTypes as $typeId => $cfg) {
            $defaultMethod = $cfg['defaultBodyMethod'];
            $defaultBody = \APP\plugins\generic\academicCertificate\classes\CertificateGenerator::$defaultMethod();
            $certificateTypesForDesigner[$typeId] = array_merge($cfg, array(
                'label' => __('plugins.generic.academicCertificate.settings.certType.' . $typeId),
                'variables' => $variablesByKey[$cfg['variablesKey']] ?? $templateVariables,
                'layoutJson' => $this->getData($cfg['layoutField']),
                'defaultBody' => $defaultBody,
                'bgLabel' => $bgFieldLabels[$cfg['bgField']] ?? '',
                'headerValue' => $this->getData($cfg['headerField']),
                'bodyValue' => $this->getData($cfg['bodyField']),
                'footerValue' => $this->getData($cfg['footerField']),
            ));
            $bgField = $cfg['bgField'];
            $bgPath = $this->getData($bgField);
            if ($bgPath) {
                $backgroundPreviewUrls[$bgField] = $this->buildBackgroundPreviewUrl($request, $bgField);
            }
        }
        $defaultBg = $this->getData('backgroundImage');
        $templateMgr->assign('defaultBackgroundPreviewUrl', $defaultBg ? $this->buildBackgroundPreviewUrl($request, 'backgroundImage') : '');
        $templateMgr->assign('backgroundPreviewUrls', $backgroundPreviewUrls);
        $templateMgr->assign('certificateTypesForDesigner', $certificateTypesForDesigner);

        $authorTemplateVariables = array(
            '{{$articleTitle}}',
            '{{$authors}}',
            '{{$authorName}}',
            '{{$correspondingAuthor}}',
            '{{$journalName}}',
            '{{$journalAcronym}}',
            '{{$publicationDate}}',
            '{{$publicationYear}}',
            '{{$certificateCode}}',
            '{{$certificateNumber}}',
            '{{$currentDate}}',
            '{{$currentYear}}',
        );
        $templateMgr->assign('authorTemplateVariables', $authorTemplateVariables);

        $backgroundImages = array();
        foreach (self::$backgroundImageFields as $field => $prefix) {
            $path = $this->getData($field);
            if ($path) {
                $backgroundImages[$field] = array(
                    'path' => $path,
                    'name' => basename($path),
                );
            }
        }
        $templateMgr->assign('backgroundImages', $backgroundImages);

        // Statistics (graceful fallback if tables are missing)
        $templateMgr->assign('totalCertificates', 0);
        $templateMgr->assign('totalDownloads', 0);
        $templateMgr->assign('uniqueReviewers', 0);
        $templateMgr->assign('eligibleReviewers', array());

        try {
            $certificateDao = $this->plugin->getCertificateDao();
            if (!$certificateDao) {
                error_log('AcademicCertificate: CertificateDAO not registered - statistics unavailable');
            } else {
                $statistics = $certificateDao->getStatisticsByContext($this->contextId);
                $templateMgr->assign('totalCertificates', $statistics['total']);
                $templateMgr->assign('totalDownloads', $statistics['downloads']);
                $templateMgr->assign('uniqueReviewers', $statistics['reviewers']);
                $templateMgr->assign('eligibleReviewers', $this->getEligibleReviewers());
            }
        } catch (\Throwable $e) {
            error_log('AcademicCertificate: Settings statistics unavailable: ' . $e->getMessage());
        }

        return parent::fetch($request, $template, $display);
    }

    /**
     * Get eligible reviewers for batch certificate generation
     * @return array
     */
    private function getEligibleReviewers() {
        $certificateDao = $this->plugin->getCertificateDao();

        // Check if DAO is available
        if (!$certificateDao) {
            error_log('AcademicCertificate: CertificateDAO not registered - cannot get eligible reviewers');
            return array();
        }

        // Use direct database query for OJS 3.4 compatibility
        // Note: review_id is the primary key in review_assignments table
        $result = $certificateDao->retrieve(
            'SELECT DISTINCT ra.reviewer_id,
                    COUNT(*) as completed_reviews,
                    SUM(CASE WHEN rc.certificate_id IS NULL THEN 1 ELSE 0 END) as missing_certificates
             FROM review_assignments ra
             LEFT JOIN submissions s ON ra.submission_id = s.submission_id
             LEFT JOIN reviewer_certificates rc ON ra.review_id = rc.review_id AND rc.certificate_type = ?
             WHERE s.context_id = ?
                   AND ra.date_completed IS NOT NULL
             GROUP BY ra.reviewer_id
             HAVING missing_certificates > 0
             ORDER BY completed_reviews DESC
             LIMIT 100',
            array('reviewer', (int) $this->contextId)
        );

        $reviewers = array();
        $count = 0;
        foreach ($result as $row) {
            if ($count >= 100) {
                break;
            }
            $reviewerId = is_array($row) ? ($row['reviewer_id'] ?? null) : ($row->reviewer_id ?? null);
            $completedReviews = is_array($row) ? ($row['completed_reviews'] ?? 0) : ($row->completed_reviews ?? 0);
            $missingCertificates = is_array($row) ? ($row['missing_certificates'] ?? 0) : ($row->missing_certificates ?? 0);
            if (!$reviewerId) {
                continue;
            }
            try {
                // OJS 3.3 compatibility
                if (class_exists('APP\facades\Repo')) {
                    $user = \APP\facades\Repo::user()->get($reviewerId);
                } else {
                    $userDao = DAORegistry::getDAO('UserDAO');
                    $user = $userDao->getById($reviewerId);
                }
                if ($user) {
                    $reviewers[] = array(
                        'id' => $reviewerId,
                        'name' => $user->getFullName(),
                        'completedReviews' => $completedReviews,
                        'missingCertificates' => $missingCertificates
                    );
                    $count++;
                }
            } catch (Exception $e) {
                error_log('AcademicCertificate: Error getting user ' . $reviewerId . ': ' . $e->getMessage());
                continue;
            }
        }

        return $reviewers;
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs) {
        try {
            $this->plugin->updateSetting($this->contextId, 'headerText', $this->getData('headerText'), 'string');
            $this->plugin->updateSetting($this->contextId, 'bodyTemplate', $this->getData('bodyTemplate'), 'string');
            $this->plugin->updateSetting($this->contextId, 'footerText', $this->getData('footerText'), 'string');
            $this->plugin->updateSetting($this->contextId, 'acceptanceHeaderText', $this->getData('acceptanceHeaderText'), 'string');
            $this->plugin->updateSetting($this->contextId, 'acceptanceBodyTemplate', $this->getData('acceptanceBodyTemplate'), 'string');
            $this->plugin->updateSetting($this->contextId, 'acceptanceFooterText', $this->getData('acceptanceFooterText'), 'string');
            $this->plugin->updateSetting($this->contextId, 'authorHeaderText', $this->getData('authorHeaderText'), 'string');
            $this->plugin->updateSetting($this->contextId, 'authorBodyTemplate', $this->getData('authorBodyTemplate'), 'string');
            $this->plugin->updateSetting($this->contextId, 'authorFooterText', $this->getData('authorFooterText'), 'string');
            $this->plugin->updateSetting($this->contextId, 'editorHeaderText', $this->getData('editorHeaderText'), 'string');
            $this->plugin->updateSetting($this->contextId, 'editorBodyTemplate', $this->getData('editorBodyTemplate'), 'string');
            $this->plugin->updateSetting($this->contextId, 'editorFooterText', $this->getData('editorFooterText'), 'string');

            foreach (self::$certificateTypes as $cfg) {
                $layoutKey = $cfg['layoutField'];
                $rawLayout = (string) $this->getData($layoutKey);
                $layout = CertificateLayout::parse($rawLayout);
                $this->plugin->updateSetting($this->contextId, $layoutKey, CertificateLayout::encode($layout), 'string');
            }

            // Validate fontFamily against whitelist
            $allowedFonts = array('helvetica', 'times', 'courier', 'dejavusans');
            $fontFamily = $this->getData('fontFamily');
            if (!in_array($fontFamily, $allowedFonts)) {
                $fontFamily = 'dejavusans';
            }
            $this->plugin->updateSetting($this->contextId, 'fontFamily', $fontFamily, 'string');

            // Clamp numeric values to valid ranges
            $this->plugin->updateSetting($this->contextId, 'fontSize', max(6, min(72, (int) $this->getData('fontSize'))), 'int');
            $this->plugin->updateSetting($this->contextId, 'textColorR', max(0, min(255, (int) $this->getData('textColorR'))), 'int');
            $this->plugin->updateSetting($this->contextId, 'textColorG', max(0, min(255, (int) $this->getData('textColorG'))), 'int');
            $this->plugin->updateSetting($this->contextId, 'textColorB', max(0, min(255, (int) $this->getData('textColorB'))), 'int');
            $this->plugin->updateSetting($this->contextId, 'minimumReviews', max(1, (int) $this->getData('minimumReviews')), 'int');

            $prefix = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $this->getData('certificateNumberPrefix')));
            $this->plugin->updateSetting($this->contextId, 'certificateNumberPrefix', $prefix !== '' ? $prefix : 'ACM', 'string');

            $this->plugin->updateSetting($this->contextId, 'enableReviewerCertificates', (bool) $this->getData('enableReviewerCertificates'), 'bool');
            $this->plugin->updateSetting($this->contextId, 'enableAcceptanceCertificates', (bool) $this->getData('enableAcceptanceCertificates'), 'bool');
            $this->plugin->updateSetting($this->contextId, 'enableAuthorCertificates', (bool) $this->getData('enableAuthorCertificates'), 'bool');
            $this->plugin->updateSetting($this->contextId, 'enableEditorCertificates', (bool) $this->getData('enableEditorCertificates'), 'bool');
            $this->plugin->updateSetting($this->contextId, 'hideReviewerSubmissionTitle', (bool) $this->getData('hideReviewerSubmissionTitle'), 'bool');

            $this->plugin->updateSetting($this->contextId, 'includeQRCode', (bool) $this->getData('includeQRCode'), 'bool');

            // Validate pageOrientation against whitelist (default landscape A4)
            $orientation = $this->getData('pageOrientation');
            if (!in_array($orientation, array('P', 'L'))) {
                $orientation = 'L';
            }
            $this->plugin->updateSetting($this->contextId, 'pageOrientation', $orientation, 'string');

            foreach (array_keys(self::$backgroundImageFields) as $field) {
                $path = $this->getData($field);
                $this->plugin->updateSetting($this->contextId, $field, $path ? $path : '', 'string');
            }
        } catch (\Exception $e) {
            // Log error but don't fail - settings may already exist from previous install
            error_log('AcademicCertificate: Error saving settings (may be duplicate key on reinstall): ' . $e->getMessage());
        }

        parent::execute(...$functionArgs);
    }

    /**
     * @param \PKP\request\Request $request
     * @param string $field
     * @return string
     */
    private function buildBackgroundPreviewUrl($request, $field) {
        $router = $request->getRouter();
        return $router->url(
            $request,
            null,
            null,
            'manage',
            null,
            array(
                'verb' => 'backgroundPreview',
                'plugin' => $this->plugin->getName(),
                'category' => 'generic',
                'field' => $field,
            )
        );
    }
}
