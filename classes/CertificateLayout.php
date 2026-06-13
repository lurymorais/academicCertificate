<?php
/**
 * @file plugins/generic/academicCertificate/classes/CertificateLayout.php
 *
 * Layout positions for certificate text blocks (percent of page width/height).
 */

namespace APP\plugins\generic\academicCertificate\classes;

class CertificateLayout {

    /** A4 landscape dimensions */
    const PAGE_WIDTH_MM = 297;
    const PAGE_HEIGHT_MM = 210;
    const PAGE_WIDTH_PX_300DPI = 3508;
    const PAGE_HEIGHT_PX_300DPI = 2480;
    const PAGE_WIDTH_PX_96DPI = 1123;
    const PAGE_HEIGHT_PX_96DPI = 794;

    /** @var array<string,string> */
    private static $typeToSettingKey = array(
        'reviewer' => 'layoutReviewer',
        'acceptance' => 'layoutAcceptance',
        'author' => 'layoutAuthor',
        'editor' => 'layoutEditor',
    );

    /**
     * @param string $type reviewer|acceptance|author|editor
     * @return string
     */
    public static function getSettingKeyForType($type) {
        return self::$typeToSettingKey[$type] ?? 'layoutReviewer';
    }

    /**
     * Default block layout (x,y,w in % of page; align L|C|R).
     *
     * @return array<string,array<string,mixed>>
     */
    public static function getDefaultBlocks() {
        return array(
            'header' => array(
                'x' => 10,
                'y' => 10,
                'w' => 80,
                'align' => 'C',
                'fontScale' => 2.0,
            ),
            'body' => array(
                'x' => 10,
                'y' => 32,
                'w' => 80,
                'h' => 40,
                'align' => 'C',
                'fontScale' => 1.167,
            ),
            'footer' => array(
                'x' => 10,
                'y' => 76,
                'w' => 80,
                'align' => 'C',
                'fontScale' => 0.833,
            ),
            'code' => array(
                'x' => 35,
                'y' => 88,
                'w' => 30,
                'align' => 'C',
                'fontScale' => 0.667,
                'visible' => true,
            ),
        );
    }

    /**
     * @param string|null $json
     * @return array<string,array<string,mixed>>
     */
    public static function parse($json) {
        $defaults = self::getDefaultBlocks();
        if (!$json || !is_string($json)) {
            return $defaults;
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return $defaults;
        }
        foreach ($defaults as $block => $def) {
            if (!isset($decoded[$block]) || !is_array($decoded[$block])) {
                $decoded[$block] = $def;
                continue;
            }
            $decoded[$block] = array_merge($def, $decoded[$block]);
        }
        return $decoded;
    }

    /**
     * @param array<string,array<string,mixed>> $layout
     * @return string
     */
    public static function encode($layout) {
        return json_encode($layout, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param float $pageDim
     * @param float $percent
     * @return float
     */
    public static function percentToUnits($pageDim, $percent) {
        return $pageDim * ((float) $percent / 100.0);
    }

    /**
     * @param string $align
     * @return string
     */
    public static function normalizeAlign($align) {
        $align = strtoupper((string) $align);
        return in_array($align, array('L', 'C', 'R'), true) ? $align : 'C';
    }
}
