<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\external;

use block_dashboardanalytics\context_resolver;
use block_dashboardanalytics\repository\employee_status_repository;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

class set_employee_deactivation extends \external_api {
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'contextid' => new \external_value(PARAM_INT, 'Block context ID'),
            'userid' => new \external_value(PARAM_INT, 'Employee user ID'),
            'companyid' => new \external_value(PARAM_INT, 'Company ID'),
            'deactivated' => new \external_value(PARAM_BOOL, 'Requested company-employment state'),
        ]);
    }

    public static function execute(int $contextid, int $userid, int $companyid, bool $deactivated): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'userid' => $userid,
            'companyid' => $companyid,
            'deactivated' => $deactivated,
        ]);
        $context = context_resolver::require_context((int)$params['contextid']);
        self::validate_context($context);
        if (!is_siteadmin((int)$USER->id)) {
            throw new \moodle_exception('error:noaccess', 'block_dashboardanalytics');
        }
        require_sesskey();

        $result = (new employee_status_repository())->set_deactivated(
            (int)$params['userid'],
            (int)$params['companyid'],
            (bool)$params['deactivated'],
            (int)$USER->id
        );

        return [
            'userid' => (int)$params['userid'],
            'companyid' => (int)$params['companyid'],
            'deactivated' => (bool)$result['deactivated'],
            'changed' => (bool)$result['changed'],
        ];
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'userid' => new \external_value(PARAM_INT, 'Employee user ID'),
            'companyid' => new \external_value(PARAM_INT, 'Company ID'),
            'deactivated' => new \external_value(PARAM_BOOL, 'Saved state'),
            'changed' => new \external_value(PARAM_BOOL, 'Whether an actual transition was recorded'),
        ]);
    }
}
