<?php
/**
 * @file plugins/generic/academicCertificate/AcademicCertificatePlugin.php
 *
 * Copyright (c) 2024
 * Distributed under the GNU GPL v3.
 *
 * @class AcademicCertificatePlugin
 * @ingroup plugins_generic_academicCertificate
 *
 * Academic Certificate Manager for OJS — entry point and autoloader bootstrap
 *
 * Publisher: Holistence Publication — https://holistence.com/
 * Folder name remains `academicCertificate` for OJS compatibility.
 * It loads the OJS 3.3 compatibility autoloader first (before any namespace resolution),
 * then includes the main plugin implementation.
 *
 * The autoloader must be registered BEFORE PHP tries to resolve namespaced class names,
 * which happens when including files that use `extends NamespacedClass`.
 */

// Step 1: Load the OJS 3.3 compatibility autoloader FIRST (only if it exists)
// This must happen before any file with `use` statements or class inheritance is parsed
// Note: compat_autoloader.php is only included in OJS 3.3 release packages
// OJS 3.4+ have native namespaced classes and don't need it
if (file_exists(__DIR__ . '/compat_autoloader.php')) {
    require_once __DIR__ . '/compat_autoloader.php';
}

// Plugin PSR-4 autoloader — OJS does not run composer autoload for generic plugins
if (!defined('ACADEMIC_CERTIFICATE_PLUGIN_AUTOLOADER')) {
    define('ACADEMIC_CERTIFICATE_PLUGIN_AUTOLOADER', true);
    spl_autoload_register(function ($class) {
        $prefix = 'APP\\plugins\\generic\\academicCertificate\\';
        $normalized = str_replace('/', '\\', $class);
        if (strncmp($normalized, $prefix, strlen($prefix)) !== 0) {
            return false;
        }
        $relative = substr($normalized, strlen($prefix));
        $file = __DIR__ . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
        if (is_file($file)) {
            require_once $file;
            return true;
        }
        return false;
    }, true, true);
}

// Step 2: Now load the main plugin implementation
// At this point, the autoloader is registered and will handle namespaced class resolution
require_once __DIR__ . '/classes/AcademicCertificatePluginCore.php';

// Step 3: Create a global namespace alias for OJS 3.3 compatibility
// OJS 3.3 expects plugins in the global namespace
// OJS 3.4+ expects plugins in their PSR-4 namespace
// By creating this alias, both work correctly
if (!class_exists('AcademicCertificatePlugin', false)) {
    class_alias(
        'APP\\plugins\\generic\\academicCertificate\\AcademicCertificatePlugin',
        'AcademicCertificatePlugin'
    );
}
