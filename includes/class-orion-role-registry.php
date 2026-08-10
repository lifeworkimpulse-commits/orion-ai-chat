<?php
if (!defined('ABSPATH')) { exit; }

final class Orion_Role_Registry {
    private const ROLES = array(
        'primary_coating', 'primary_product', 'primer', 'cleaner', 'filler',
        'roller', 'brush', 'tray', 'masking_tape', 'dust_sheet',
        'sandpaper', 'scraper', 'fixings', 'trim', 'tools', 'protection',
    );

    private const ALIASES = array(
        'primarycoating' => 'primary_coating', 'primaryproduct' => 'primary_product', 'mainpaint' => 'primary_coating',
        'maskingtape' => 'masking_tape', 'masking' => 'masking_tape', 'dustsheet' => 'dust_sheet',
        'rollerframe' => 'roller', 'rollersleeve' => 'roller', 'sleeve' => 'roller', 'frame' => 'roller', 'tool' => 'tools',
    );

    private const OPTIONAL = array(
        'primer', 'cleaner', 'filler', 'roller', 'brush', 'tray', 'masking_tape', 'dust_sheet',
        'sandpaper', 'scraper', 'fixings', 'trim', 'tools', 'protection',
    );

    private const PRIORITY = array(
        'primary_coating' => 10, 'primary_product' => 10, 'roller' => 20, 'roller_frame' => 21,
        'roller_sleeve' => 22, 'tray' => 30, 'brush' => 40, 'cleaner' => 45, 'masking_tape' => 50,
        'dust_sheet' => 60, 'primer' => 70, 'filler' => 72, 'sandpaper' => 73, 'scraper' => 74,
    );

    public static function all(): array { return self::ROLES; }
    public static function canonical(string $role): string {
        $key = (string) preg_replace('/[^a-z0-9_-]/', '', strtolower($role));
        $compact = (string) preg_replace('/[^a-z0-9]/', '', $key);
        return self::ALIASES[$compact] ?? $key;
    }
    public static function canonical_for_query(string $role, string $query): string {
        $role = self::canonical($role);
        if ('protection' === $role && preg_match('/\b(dust sheet|protective sheet|polythene sheet|floor protection)\b/i', $query)) { return 'dust_sheet'; }
        return $role;
    }
    public static function supported(string $role): bool { return in_array(self::canonical($role), self::ROLES, true); }
    public static function optional(string $role): bool { return in_array(self::canonical($role), self::OPTIONAL, true); }
    public static function priority(string $role): int { return self::PRIORITY[$role] ?? self::PRIORITY[self::canonical($role)] ?? 80; }
}
