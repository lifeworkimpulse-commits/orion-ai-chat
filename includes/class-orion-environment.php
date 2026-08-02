<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Orion_Environment {
    public static function issues(): array {
        $issues = array();
        if ( version_compare( PHP_VERSION, '8.0', '<' ) ) $issues[] = 'PHP 8.0 or newer is required.';
        if ( version_compare( get_bloginfo( 'version' ), '6.2', '<' ) ) $issues[] = 'WordPress 6.2 or newer is required.';
        if ( ! class_exists( 'WooCommerce' ) ) $issues[] = 'WooCommerce must be active.';
        if ( class_exists( 'WooCommerce' ) && defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '8.0', '<' ) ) $issues[] = 'WooCommerce 8.0 or newer is required.';
        if ( ! class_exists( 'DOMDocument' ) ) $issues[] = 'The PHP DOM extension is required for knowledge imports.';
        if ( ! function_exists( 'mb_strlen' ) ) $issues[] = 'The PHP mbstring extension is required.';
        return $issues;
    }
    public static function ready(): bool { return array() === self::issues(); }
}
