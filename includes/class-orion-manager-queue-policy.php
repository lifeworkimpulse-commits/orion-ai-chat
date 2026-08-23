<?php
if (!defined('ABSPATH')) { exit; }

final class Orion_Manager_Queue_Policy {
    public const STATUS_NEW = 'new';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_DISMISSED = 'dismissed';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_URGENT = 'urgent';
    public const RESPONSE_NONE = 'none';
    public const RESPONSE_DRAFT = 'draft';
    public const RESPONSE_APPROVED = 'approved';
    public const RESPONSE_DELIVERED = 'delivered';
    public const RESPONSE_EXPIRED = 'expired';

    public static function statuses(): array { return array(self::STATUS_NEW,self::STATUS_IN_PROGRESS,self::STATUS_RESOLVED,self::STATUS_DISMISSED); }
    public static function priorities(): array { return array(self::PRIORITY_NORMAL,self::PRIORITY_URGENT); }
    public static function response_statuses(): array { return array(self::RESPONSE_NONE,self::RESPONSE_DRAFT,self::RESPONSE_APPROVED,self::RESPONSE_DELIVERED,self::RESPONSE_EXPIRED); }
    public static function is_status(string $status): bool { return in_array($status,self::statuses(),true); }
    public static function normalize_priority(string $priority): string { return in_array($priority,self::priorities(),true)?$priority:self::PRIORITY_NORMAL; }
    public static function normalize_response_status(string $status): string { return in_array($status,self::response_statuses(),true)?$status:self::RESPONSE_NONE; }
    public static function can_transition(string $from,string $to): bool { if(!self::is_status($from)||!self::is_status($to))return false;if($from===$to)return true;$allowed=array(self::STATUS_NEW=>array(self::STATUS_IN_PROGRESS,self::STATUS_RESOLVED,self::STATUS_DISMISSED),self::STATUS_IN_PROGRESS=>array(self::STATUS_NEW,self::STATUS_RESOLVED,self::STATUS_DISMISSED),self::STATUS_RESOLVED=>array(self::STATUS_NEW),self::STATUS_DISMISSED=>array(self::STATUS_NEW));return in_array($to,$allowed[$from]??array(),true); }
    public static function is_terminal(string $status): bool { return in_array($status,array(self::STATUS_RESOLVED,self::STATUS_DISMISSED),true); }
}
