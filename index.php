<?php
/**
 * @file plugins/generic/academicCertificate/index.php
 *
 * Copyright (c) 2024
 * Distributed under the GNU GPL v3.
 *
 * @ingroup plugins_generic_academicCertificate
 * @brief Wrapper for Academic Certificate Manager plugin
 */

// OJS 3.5+ uses .php extension, older versions may still use .inc.php
if (file_exists(__DIR__ . '/AcademicCertificatePlugin.php')) {
    require_once('AcademicCertificatePlugin.php');
} else {
    require_once('AcademicCertificatePlugin.inc.php');
}

try {
    return new \APP\plugins\generic\academicCertificate\AcademicCertificatePlugin();
} catch (\Throwable $e) {
    error_log('AcademicCertificate: Failed to instantiate plugin: ' . $e->getMessage());
    // Fall back to global namespace alias (created by AcademicCertificatePlugin.php)
    if (class_exists('AcademicCertificatePlugin', false)) {
        return new \AcademicCertificatePlugin();
    }
    return null;
}
