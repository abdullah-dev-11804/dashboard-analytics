<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\external;

use block_dashboardanalytics\context_resolver;
use block_dashboardanalytics\service\report_service;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

class load_act_services extends \external_api {

    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'contextid' => new \external_value(PARAM_INT, 'Block context ID'),
            'companyid' => new \external_value(PARAM_INT, 'Company id'),
            'month' => new \external_value(PARAM_INT, 'Month'),
            'year' => new \external_value(PARAM_INT, 'Year'),
        ]);
    }

    public static function execute(int $contextid, int $companyid, int $month, int $year): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'companyid' => $companyid,
            'month' => $month,
            'year' => $year,
        ]);

        context_resolver::require_context((int)$params['contextid']);

        $service = new report_service();

        return $service->load_services(
            (int)$params['companyid'],
            (int)$params['month'],
            (int)$params['year']
        );
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'companyname' => new \external_value(PARAM_TEXT, 'Company name'),
            'rows' => new \external_multiple_structure(new \external_single_structure([
                'number' => new \external_value(PARAM_INT, 'Row number'),
                'courseid' => new \external_value(PARAM_INT, 'Course id'),
                'coursename' => new \external_value(PARAM_TEXT, 'Course name'),
                'unit' => new \external_value(PARAM_TEXT, 'Unit'),
                'lmscount' => new \external_value(PARAM_INT, 'LMS count'),
                'actqty' => new \external_value(PARAM_INT, 'Act quantity'),
            ])),
            'lmstotal' => new \external_value(PARAM_INT, 'LMS total'),
            'acttotal' => new \external_value(PARAM_INT, 'Act total'),
            'difference' => new \external_value(PARAM_INT, 'Difference'),
        ]);
    }
}