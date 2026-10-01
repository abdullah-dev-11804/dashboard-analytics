<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\external;

use block_dashboardanalytics\context_resolver;
use block_dashboardanalytics\repository\employee_status_repository;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

class get_employee_management extends \external_api {
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'contextid' => new \external_value(PARAM_INT, 'Block context ID'),
            'search' => new \external_value(PARAM_TEXT, 'Employee or company search text', VALUE_DEFAULT, ''),
            'companyid' => new \external_value(PARAM_INT, 'Optional company filter', VALUE_DEFAULT, 0),
            'status' => new \external_value(PARAM_ALPHA, 'active, deactivated, or all', VALUE_DEFAULT, 'active'),
            'page' => new \external_value(PARAM_INT, 'Zero-based page index', VALUE_DEFAULT, 0),
            'perpage' => new \external_value(PARAM_INT, 'Rows per page', VALUE_DEFAULT, 20),
        ]);
    }

    public static function execute(
        int $contextid,
        string $search = '',
        int $companyid = 0,
        string $status = 'active',
        int $page = 0,
        int $perpage = 20
    ): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'search' => $search,
            'companyid' => $companyid,
            'status' => $status,
            'page' => $page,
            'perpage' => $perpage,
        ]);
        $context = context_resolver::require_context((int)$params['contextid']);
        self::validate_context($context);
        if (!is_siteadmin((int)$USER->id)) {
            throw new \moodle_exception('error:noaccess', 'block_dashboardanalytics');
        }

        return (new employee_status_repository())->list_employees(
            trim((string)$params['search']),
            (int)$params['companyid'],
            (string)$params['status'],
            (int)$params['page'],
            (int)$params['perpage']
        );
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'rows' => new \external_multiple_structure(new \external_single_structure([
                'userid' => new \external_value(PARAM_INT, 'Employee user ID'),
                'fullname' => new \external_value(PARAM_TEXT, 'Employee full name'),
                'email' => new \external_value(PARAM_TEXT, 'Employee email'),
                'companyid' => new \external_value(PARAM_INT, 'Company ID'),
                'companyname' => new \external_value(PARAM_TEXT, 'Company name'),
                'deactivated' => new \external_value(PARAM_BOOL, 'Company-specific deactivation state'),
                'accountsuspended' => new \external_value(PARAM_BOOL, 'Independent Moodle suspension state'),
                'deactivatedat' => new \external_value(PARAM_INT, 'Last deactivation timestamp'),
            ])),
            'companies' => new \external_multiple_structure(new \external_single_structure([
                'value' => new \external_value(PARAM_INT, 'Company ID'),
                'label' => new \external_value(PARAM_TEXT, 'Company name'),
            ])),
            'totalcount' => new \external_value(PARAM_INT, 'Total matching memberships'),
            'page' => new \external_value(PARAM_INT, 'Current page'),
            'perpage' => new \external_value(PARAM_INT, 'Current page size'),
            'status' => new \external_value(PARAM_ALPHA, 'Current status filter'),
            'companyid' => new \external_value(PARAM_INT, 'Current company filter'),
        ]);
    }
}
