<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics;

use block_dashboardanalytics\repository\employee_status_repository;

defined('MOODLE_INTERNAL') || die();

/**
 * Public helper for company-specific employee activation state.
 */
class employee_status {
    public static function is_deactivated(int $userid, int $companyid): bool {
        return (new employee_status_repository())->is_deactivated($userid, $companyid);
    }

    /**
     * Return a reusable active-employee SQL condition and its parameters.
     *
     * @return array{sql: string, params: array}
     */
    public static function active_user_filter_sql(
        array $filters,
        string $useralias = 'u',
        string $prefix = 'empstatus'
    ): array {
        return (new employee_status_repository())->active_user_filter_sql($filters, $useralias, $prefix);
    }
}
