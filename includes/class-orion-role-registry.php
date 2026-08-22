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
        $key = strtolower(trim($role));
        $key = (string) preg_replace('/[^a-z0-9]+/', '_', $key);
        $key = trim($key, '_');
        $compact = (string) preg_replace('/[^a-z0-9]/', '', $key);
        if (isset(self::ALIASES[$compact])) { return self::ALIASES[$compact]; }
        return self::semantic_hint($key) ?: $key;
    }

    public static function canonical_for_query(string $role, string $query): string {
        $role = self::canonical($role);
        if ('protection' === $role && preg_match('/\b(dust sheet|protective sheet|polythene sheet|floor protection)\b/i', $query)) { return 'dust_sheet'; }
        return $role;
    }

    public static function known(string $role): bool {
        return in_array(self::canonical($role), self::ROLES, true);
    }

    public static function open_key(string $role, string $query = ''): string {
        $canonical = self::canonical_for_query($role, $query);
        if (self::known($canonical)) { return $canonical; }
        $key = strtolower(trim($role));
        $key = (string) preg_replace('/[^a-z0-9]+/', '_', $key);
        $key = trim($key, '_');
        return substr($key, 0, 64);
    }

    public static function supported(string $role): bool {
        return '' !== self::open_key($role);
    }

    public static function role_hint(string $role): string {
        $canonical = self::canonical($role);
        return self::known($canonical) ? $canonical : '';
    }

    public static function optional(string $role): bool { return in_array(self::canonical($role), self::OPTIONAL, true); }
    public static function priority(string $role): int { return self::PRIORITY[$role] ?? self::PRIORITY[self::canonical($role)] ?? 80; }

    private static function semantic_hint(string $key): string {
        if ($key === '') { return ''; }
        if (preg_match('/(^|_)(roller_)?extension_pole($|_)|(^|_)roller_pole($|_)/', $key)) { return 'tools'; }
        if (preg_match('/(^|_)masking(_tape)?($|_)/', $key)) { return 'masking_tape'; }
        if (preg_match('/(^|_)dust_sheet($|_)/', $key)) { return 'dust_sheet'; }
        if (preg_match('/(^|_)primer($|_)/', $key)) { return 'primer'; }
        if (preg_match('/(^|_)(cleaner|degreaser)($|_)/', $key)) { return 'cleaner'; }
        if (preg_match('/(^|_)(filler|repair_compound)($|_)/', $key) || (str_contains($key, 'repair') && preg_match('/(^|_)(concrete|surface)($|_)/', $key))) { return 'filler'; }
        if (preg_match('/(^|_)scraper($|_)/', $key)) { return 'scraper'; }
        if (preg_match('/(^|_)(sandpaper|sanding|abrasive)($|_)/', $key)) { return 'sandpaper'; }
        if (preg_match('/(^|_)tray($|_)/', $key)) { return 'tray'; }
        if (preg_match('/(^|_)brush($|_)/', $key)) { return 'brush'; }
        if (preg_match('/(^|_)roller($|_)/', $key)) { return 'roller'; }
        if (preg_match('/(^|_)(protection|ppe|safety_equipment)($|_)/', $key)) { return 'protection'; }
        if (preg_match('/(^|_)(topcoat|finish_coat)($|_)/', $key)) { return 'primary_coating'; }
        if (preg_match('/(^|_)(paint|coating)($|_)/', $key) && !preg_match('/(^|_)(additive|remover|stripper|cleaner|primer|filler)($|_)/', $key)) { return 'primary_coating'; }
        return '';
    }
}
