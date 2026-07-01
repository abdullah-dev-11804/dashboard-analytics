<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics;

defined('MOODLE_INTERNAL') || die();

class permissions {

    public const DASHBOARD_COMPANY = 'company';
    public const DASHBOARD_CLIENT = 'client';
    public const DASHBOARD_EMPLOYEE = 'employee';

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

        return in_array(self::resolve_dashboard_key($context, $userid), [
            self::DASHBOARD_COMPANY,
            self::DASHBOARD_CLIENT,
        ], true);
    }

    public static function resolve_dashboard_key(\context $context, ?int $userid = null): ?string {
        global $USER;

        $userid = $userid ?? (int)$USER->id;

        if (is_siteadmin($userid) || has_capability('block/dashboardanalytics:viewcompany', $context, $userid)) {
            return self::DASHBOARD_COMPANY;
        }

        if (has_capability('block/dashboardanalytics:viewclient', $context, $userid)) {
            return self::DASHBOARD_CLIENT;
        }

        $roles = self::role_shortnames_for_user($context, $userid);

        if (self::has_configured_role($roles, 'companyownerroles')) {
            return self::DASHBOARD_COMPANY;
        }

        if (self::has_configured_role($roles, 'clientroles')) {
            return self::DASHBOARD_CLIENT;
        }

        if (!isguestuser($userid)) {
            return self::DASHBOARD_EMPLOYEE;
        }

        return null;
    }

    public static function dashboard_name(string $dashboardkey): string {
        $map = [
            self::DASHBOARD_COMPANY => get_string('dashboard:company', 'block_dashboardanalytics'),
            self::DASHBOARD_CLIENT => get_string('dashboard:client', 'block_dashboardanalytics'),
            self::DASHBOARD_EMPLOYEE => get_string('dashboard:employee', 'block_dashboardanalytics'),
        ];

        return $map[$dashboardkey] ?? get_string('pluginname', 'block_dashboardanalytics');
    }

    public static function dashboard_tabs(string $dashboardkey, ?\context $context = null, ?int $userid = null): array {
        global $USER;

        $userid = $userid ?? (int)$USER->id;
        $issuperadmin = is_siteadmin($userid);

        $tabs = [
            self::DASHBOARD_COMPANY => [
                ['key' => 'kpis', 'label' => get_string('tab:kpis', 'block_dashboardanalytics')],
                ['key' => 'overview', 'label' => get_string('tab:overview', 'block_dashboardanalytics')],
                ['key' => 'compliance', 'label' => get_string('tab:compliance', 'block_dashboardanalytics')],
                ['key' => 'turnover', 'label' => get_string('tab:turnover', 'block_dashboardanalytics')],
                ['key' => 'quality', 'label' => get_string('tab:quality', 'block_dashboardanalytics')],
                ['key' => 'proctoring', 'label' => get_string('tab:proctoring', 'block_dashboardanalytics')],
                ['key' => 'forecast', 'label' => get_string('tab:forecast', 'block_dashboardanalytics')],
            ],
            self::DASHBOARD_CLIENT => [
                ['key' => 'kpis', 'label' => get_string('tab:kpis', 'block_dashboardanalytics')],
                ['key' => 'compliance', 'label' => get_string('tab:compliance', 'block_dashboardanalytics')],
                ['key' => 'turnover', 'label' => get_string('tab:turnover', 'block_dashboardanalytics')],
            ],
            self::DASHBOARD_EMPLOYEE => [
                ['key' => 'overview', 'label' => get_string('tab:overview', 'block_dashboardanalytics')],
                ['key' => 'certificates', 'label' => get_string('tab:certificates', 'block_dashboardanalytics')],
                ['key' => 'courses', 'label' => get_string('tab:courses', 'block_dashboardanalytics')],
            ],
        ];

        if ($dashboardkey === self::DASHBOARD_COMPANY && $issuperadmin) {
            $tabs[self::DASHBOARD_COMPANY][] = ['key' => 'server', 'label' => get_string('tab:server', 'block_dashboardanalytics')];
            $tabs[self::DASHBOARD_COMPANY][] = ['key' => 'reports', 'label' => get_string('tab:reports', 'block_dashboardanalytics')];
            $tabs[self::DASHBOARD_COMPANY][] = ['key' => 'analyticscourses', 'label' => get_string('tab:analyticscourses', 'block_dashboardanalytics')];
        }

        $items = $tabs[$dashboardkey] ?? [];
        foreach ($items as $index => $tab) {
            $items[$index]['active'] = $index === 0;
        }

        return $items;
    }

    public static function tab_is_allowed(string $dashboardkey, string $tabkey, ?\context $context = null, ?int $userid = null): bool {
        foreach (self::dashboard_tabs($dashboardkey, $context, $userid) as $tab) {
            if (($tab['key'] ?? '') === $tabkey) {
                return true;
            }
        }

        return false;
    }

    public static function require_dashboard_key(\context $context, string $requestedkey, ?int $userid = null): string {
        $resolved = self::resolve_dashboard_key($context, $userid);
        if ($resolved === null || $resolved !== $requestedkey) {
            throw new \moodle_exception('error:noaccess', 'block_dashboardanalytics');
        }

        return $resolved;
    }

    private static function has_configured_role(array $assignedroles, string $settingname): bool {
        $configured = self::configured_role_shortnames($settingname);
        return (bool)array_intersect($assignedroles, $configured);
    }

    private static function configured_role_shortnames(string $settingname): array {
        $defaults = [
            'companyownerroles' => 'companyowner',
            'clientroles' => 'trainingmanager',
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
