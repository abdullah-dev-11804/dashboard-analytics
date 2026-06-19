<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics;

use block_dashboardanalytics\repository\company_repository;

defined('MOODLE_INTERNAL') || die();

class filters {

    public static function from_json(?string $json): array {
        $decoded = json_decode((string)$json, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        return [
            'companyids' => self::int_list($decoded['companyids'] ?? $decoded['companies'] ?? []),
            'companies' => self::text_list($decoded['companies'] ?? []),
            'userids' => self::int_list($decoded['userids'] ?? []),
            'courseids' => self::int_list($decoded['courseids'] ?? $decoded['courses'] ?? []),
            'departments' => self::text_list($decoded['departments'] ?? $decoded['departmentids'] ?? []),
            'locations' => self::text_list($decoded['locations'] ?? $decoded['locationids'] ?? []),
            'positions' => self::text_list($decoded['positions'] ?? $decoded['positionids'] ?? []),
            'personnelcategories' => self::text_list($decoded['personnelcategories'] ?? []),
            'sites' => self::text_list($decoded['sites'] ?? []),
            'educations' => self::text_list($decoded['educations'] ?? []),
            'daterange' => self::date_range($decoded['daterange'] ?? 'last12months'),
            'customstart' => self::date_input($decoded['customstart'] ?? ''),
            'customend' => self::date_input($decoded['customend'] ?? ''),
            'status' => self::status($decoded['status'] ?? ''),
            'search' => trim(clean_param((string)($decoded['search'] ?? ''), PARAM_TEXT)),
        ];
    }

    public static function apply_dashboard_scope(array $filters, string $dashboardkey, int $userid): array {
        if ($dashboardkey === permissions::DASHBOARD_EMPLOYEE) {
            $filters['userids'] = [$userid];
            $filters['companyids'] = [];
            $filters['companies'] = [];
            return $filters;
        }

        if ($dashboardkey === permissions::DASHBOARD_COMPANY && !is_siteadmin($userid)) {
            $companies = new company_repository();
            $scope = $companies->scope_filters_for_user($userid);
            if (!empty($scope['companyids'])) {
                $filters['companyids'] = $scope['companyids'];
                $filters['companies'] = [];
                return $filters;
            }

            if (!empty($scope['companies'])) {
                $filters['companies'] = $scope['companies'];
                $filters['companyids'] = [];
                return $filters;
            }
        }

        if ($dashboardkey !== permissions::DASHBOARD_CLIENT) {
            return $filters;
        }

        $companies = new company_repository();
        $scope = $companies->scope_filters_for_user($userid);
        if (!empty($scope['companyids'])) {
            $filters['companyids'] = $scope['companyids'];
            $filters['companies'] = [];
            return $filters;
        }

        if (!empty($scope['companies'])) {
            $filters['companies'] = $scope['companies'];
            $filters['companyids'] = [];
            return $filters;
        }

        $filters['companyids'] = [-1];
        $filters['companies'] = ['__no_client_scope__'];
        return $filters;
    }

    private static function int_list($value): array {
        if (!is_array($value)) {
            $value = $value === '' || $value === null ? [] : [$value];
        }

        $items = [];
        foreach ($value as $item) {
            $item = (int)$item;
            if ($item > 0) {
                $items[] = $item;
            }
        }

        return array_values(array_unique($items));
    }

    private static function text_list($value): array {
        if (!is_array($value)) {
            $value = $value === '' || $value === null ? [] : [$value];
        }

        $items = [];
        foreach ($value as $item) {
            $item = trim(clean_param((string)$item, PARAM_TEXT));
            if ($item !== '') {
                $items[] = $item;
            }
        }

        return array_values(array_unique($items));
    }

    private static function status($value): string {
        $value = clean_param((string)$value, PARAM_ALPHANUMEXT);
        return in_array($value, ['expired', 'expiring', 'active', 'nodocument'], true) ? $value : '';
    }

    private static function date_range($value): string {
        $value = clean_param((string)$value, PARAM_ALPHANUMEXT);
        $allowed = [
            'day',
            'week',
            'month',
            '6months',
            'year',
            'alltime',
            'customrange',
            'last30days',
            'last90days',
            'last6months',
            'last12months',
        ];
        return in_array($value, $allowed, true) ? $value : 'last12months';
    }

    private static function date_input($value): string {
        $value = trim((string)$value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }
}
