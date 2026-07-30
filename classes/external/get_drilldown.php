<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\external;

use block_dashboardanalytics\context_resolver;
use block_dashboardanalytics\filters;
use block_dashboardanalytics\permissions;
use block_dashboardanalytics\service\compliance_service;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

class get_drilldown extends \external_api {

    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'contextid' => new \external_value(PARAM_INT, 'Block context ID'),
            'dashboardkey' => new \external_value(PARAM_ALPHANUMEXT, 'Dashboard key'),
            'drilldownkey' => new \external_value(PARAM_ALPHANUMEXT, 'Drilldown key'),
            'filters' => new \external_value(PARAM_RAW, 'JSON encoded filters', VALUE_DEFAULT, '{}'),
            'page' => new \external_value(PARAM_INT, 'Page number', VALUE_DEFAULT, 0),
            'perpage' => new \external_value(PARAM_INT, 'Rows per page', VALUE_DEFAULT, 25),
        ]);
    }

    public static function execute(
        int $contextid,
        string $dashboardkey,
        string $drilldownkey,
        string $filters = '{}',
        int $page = 0,
        int $perpage = 25
    ): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'dashboardkey' => $dashboardkey,
            'drilldownkey' => $drilldownkey,
            'filters' => $filters,
            'page' => $page,
            'perpage' => $perpage,
        ]);

        $context = context_resolver::require_context((int)$params['contextid']);
        $dashboardkey = permissions::require_dashboard_key($context, $params['dashboardkey'], (int)$USER->id);
        $showidentity = permissions::can_view_employee_identity($context)
            || $dashboardkey === permissions::DASHBOARD_EMPLOYEE;
        $scopedfilters = filters::apply_dashboard_scope(filters::from_json($params['filters']), $dashboardkey, (int)$USER->id);

        $service = new compliance_service();
        return $service->drilldown(
            $dashboardkey,
            $params['drilldownkey'],
            $scopedfilters,
            (int)$params['page'],
            (int)$params['perpage'],
            $showidentity,
            (int)$USER->id
        );
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'title' => new \external_value(PARAM_TEXT, 'Drilldown title'),
            'columns' => new \external_multiple_structure(new \external_single_structure([
                'key' => new \external_value(PARAM_ALPHANUMEXT, 'Column key'),
                'label' => new \external_value(PARAM_TEXT, 'Column label'),
            ])),
            'rows' => new \external_multiple_structure(new \external_single_structure([
                'rowtype' => new \external_value(PARAM_ALPHANUMEXT, 'Optional row type', VALUE_OPTIONAL),
                'groupid' => new \external_value(PARAM_ALPHANUMEXT, 'Optional row group id', VALUE_OPTIONAL),
                'expanded' => new \external_value(PARAM_BOOL, 'Whether the row is expanded by default', VALUE_OPTIONAL),
                'cells' => new \external_multiple_structure(new \external_single_structure([
                    'key' => new \external_value(PARAM_ALPHANUMEXT, 'Cell key'),
                    'value' => new \external_value(PARAM_TEXT, 'Cell value'),
                    'profileurl' => new \external_value(PARAM_URL, 'Optional profile URL', VALUE_OPTIONAL),
                    'courseurl' => new \external_value(PARAM_URL, 'Optional course URL', VALUE_OPTIONAL),
                    'statuskey' => new \external_value(PARAM_ALPHANUMEXT, 'Optional normalized status key', VALUE_OPTIONAL),
                    'togglelabel' => new \external_value(PARAM_TEXT, 'Optional expand/collapse label', VALUE_OPTIONAL),
                    'coursecount' => new \external_value(PARAM_INT, 'Optional course count for matrix rows', VALUE_OPTIONAL),
                ])),
            ])),
            'totalcount' => new \external_value(PARAM_INT, 'Total matching rows'),
            'notice' => new \external_value(PARAM_TEXT, 'Optional notice'),
            'description' => new \external_value(PARAM_TEXT, 'Optional explanation'),
            'exporturl' => new \external_value(PARAM_URL, 'Optional export URL', VALUE_OPTIONAL),
        ]);
    }
}
