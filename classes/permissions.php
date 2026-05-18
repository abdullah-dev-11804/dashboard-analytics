<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics;

defined('MOODLE_INTERNAL') || die();

class permissions {

    public const DASHBOARD_OWNER = 'owner';
    public const DASHBOARD_COORDINATOR = 'coordinator';
    public const DASHBOARD_CLIENT_MANAGER = 'clientmanager';
    public const DASHBOARD_SYSTEM = 'system';
    public const DASHBOARD_PROFILE = 'profile';

    public static function can_view_block(\context $context, ?int $userid = null): bool {
        global $USER;

        $userid = $userid ?? (int)$USER->id;

        if (is_siteadmin($userid)) {
            return true;
        }

        if (has_capability('block/dashboardanalytics:view', $context, $userid)) {
            return true;
        }

        return self::resolve_dashboard_key($context, $userid) !== null;
    }

    public static function can_view_employee_identity(\context $context, ?int $userid = null): bool {
        global $USER;

        $userid = $userid ?? (int)$USER->id;

        if (is_siteadmin($userid)) {
            return true;
        }

        if (has_capability('block/dashboardanalytics:viewemployeeidentity', $context, $userid)) {
            return true;
        }

        $dashboard = self::resolve_dashboard_key($context, $userid);
        return in_array($dashboard, [
            self::DASHBOARD_OWNER,
            self::DASHBOARD_COORDINATOR,
            self::DASHBOARD_CLIENT_MANAGER,
        ], true);
    }

    public static function resolve_dashboard_key(\context $context, ?int $userid = null): ?string {
        global $USER;

        $userid = $userid ?? (int)$USER->id;

        if (is_siteadmin($userid) || has_capability('block/dashboardanalytics:viewsystem', $context, $userid)) {
            return self::DASHBOARD_SYSTEM;
        }

        if (has_capability('block/dashboardanalytics:viewowner', $context, $userid)) {
            return self::DASHBOARD_OWNER;
        }

        if (has_capability('block/dashboardanalytics:viewcoordinator', $context, $userid)) {
            return self::DASHBOARD_COORDINATOR;
        }

        if (has_capability('block/dashboardanalytics:viewclientmanager', $context, $userid)) {
            return self::DASHBOARD_CLIENT_MANAGER;
        }

        $roles = self::role_shortnames_for_user($context, $userid);

        if (self::has_configured_role($roles, 'systemadministratorroles')) {
            return self::DASHBOARD_SYSTEM;
        }

        if (self::has_configured_role($roles, 'companyownerroles')) {
            return self::DASHBOARD_OWNER;
        }

        if (self::has_configured_role($roles, 'companycoordinatorroles')) {
            return self::DASHBOARD_COORDINATOR;
        }

        if (self::has_configured_role($roles, 'clientmanagerroles')) {
            return self::DASHBOARD_CLIENT_MANAGER;
        }

        if (has_capability('block/dashboardanalytics:viewprofile', $context, $userid)) {
            return self::DASHBOARD_PROFILE;
        }

        return null;
    }

    public static function dashboard_name(string $dashboardkey): string {
        $map = [
            self::DASHBOARD_OWNER => get_string('dashboard:owner', 'block_dashboardanalytics'),
            self::DASHBOARD_COORDINATOR => get_string('dashboard:coordinator', 'block_dashboardanalytics'),
            self::DASHBOARD_CLIENT_MANAGER => get_string('dashboard:clientmanager', 'block_dashboardanalytics'),
            self::DASHBOARD_SYSTEM => get_string('dashboard:system', 'block_dashboardanalytics'),
            self::DASHBOARD_PROFILE => get_string('dashboard:profile', 'block_dashboardanalytics'),
        ];

        return $map[$dashboardkey] ?? get_string('pluginname', 'block_dashboardanalytics');
    }

    public static function dashboard_tabs(string $dashboardkey): array {
        $tabs = [
            self::DASHBOARD_OWNER => [
                ['key' => 'kpis', 'label' => 'KPI Strip'],
                ['key' => 'overview', 'label' => 'Overview'],
                ['key' => 'compliance', 'label' => 'Compliance'],
                ['key' => 'turnover', 'label' => 'Staff Turnover'],
                ['key' => 'quality', 'label' => 'Training Quality'],
                ['key' => 'proctoring', 'label' => 'Proctoring'],
                ['key' => 'forecast', 'label' => 'Forecast'],
                ['key' => 'server', 'label' => 'Server'],
            ],
            self::DASHBOARD_COORDINATOR => [
                ['key' => 'kpis', 'label' => 'KPI Strip'],
                ['key' => 'overview', 'label' => 'Overview'],
                ['key' => 'compliance', 'label' => 'Compliance'],
                ['key' => 'proctoring', 'label' => 'Proctoring'],
                ['key' => 'forecast', 'label' => 'Forecast'],
            ],
            self::DASHBOARD_CLIENT_MANAGER => [
                ['key' => 'overview', 'label' => 'Overview'],
                ['key' => 'compliance', 'label' => 'Compliance'],
                ['key' => 'forecast', 'label' => 'Forecast'],
                ['key' => 'expiry', 'label' => '30/60/90 days'],
                ['key' => 'newstaff', 'label' => 'New staff'],
            ],
            self::DASHBOARD_SYSTEM => [
                ['key' => 'kpis', 'label' => 'KPI Strip'],
                ['key' => 'capacity', 'label' => 'Capacity'],
                ['key' => 'performance', 'label' => 'Performance'],
                ['key' => 'forecast', 'label' => 'Forecast'],
                ['key' => 'errorlog', 'label' => 'Error Log'],
                ['key' => 'settings', 'label' => 'System Settings'],
            ],
            self::DASHBOARD_PROFILE => [
                ['key' => 'profile', 'label' => 'My dashboard'],
                ['key' => 'certificates', 'label' => 'Certificates'],
                ['key' => 'courses', 'label' => 'Courses'],
            ],
        ];

        $items = $tabs[$dashboardkey] ?? [];
        foreach ($items as $index => $tab) {
            $items[$index]['active'] = $index === 0;
        }

        return $items;
    }

    private static function has_configured_role(array $assignedroles, string $settingname): bool {
        $configured = self::configured_role_shortnames($settingname);
        return (bool)array_intersect($assignedroles, $configured);
    }

    private static function configured_role_shortnames(string $settingname): array {
        $defaults = [
            'companyownerroles' => 'companyowner',
            'companycoordinatorroles' => 'companycoordinator,trainingmanager',
            'clientmanagerroles' => 'clientadministrator,manager',
            'systemadministratorroles' => 'systemadministrator',
        ];

        $raw = get_config('block_dashboardanalytics', $settingname);
        if ($raw === false || $raw === null || trim((string)$raw) === '') {
            $raw = $defaults[$settingname] ?? '';
        }

        $roles = preg_split('/\s*,\s*/', (string)$raw, -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_unique(array_map('strtolower', $roles)));
    }

    private static function role_shortnames_for_user(\context $context, int $userid): array {
        global $DB;

        $contextids = self::context_path_ids($context);
        if (!$contextids) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx');
        $params['userid'] = $userid;

        $sql = "SELECT DISTINCT r.shortname
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE ra.userid = :userid
                   AND ra.contextid $insql";

        $records = $DB->get_records_sql($sql, $params);
        $shortnames = [];
        foreach ($records as $record) {
            $shortnames[] = strtolower((string)$record->shortname);
        }

        return array_values(array_unique($shortnames));
    }

    private static function context_path_ids(\context $context): array {
        if (method_exists($context, 'get_parent_context_ids')) {
            return array_map('intval', $context->get_parent_context_ids(true));
        }

        if (!empty($context->path)) {
            return array_map('intval', array_filter(explode('/', trim($context->path, '/'))));
        }

        return [(int)$context->id];
    }
}
