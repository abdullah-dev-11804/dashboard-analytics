<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\external;

use block_dashboardanalytics\context_resolver;
use block_dashboardanalytics\filters;
use block_dashboardanalytics\permissions;
use block_dashboardanalytics\service\kpi_service;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

class get_kpis extends \external_api {

    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'contextid' => new \external_value(PARAM_INT, 'Block context ID'),
            'dashboardkey' => new \external_value(PARAM_ALPHANUMEXT, 'Dashboard key'),
            'filters' => new \external_value(PARAM_RAW, 'JSON encoded filters', VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(int $contextid, string $dashboardkey, string $filters = '{}'): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'dashboardkey' => $dashboardkey,
            'filters' => $filters,
        ]);
        $context = context_resolver::require_context((int)$params['contextid']);
        $dashboardkey = permissions::require_dashboard_key($context, $params['dashboardkey'], (int)$USER->id);
        $scopedfilters = filters::apply_dashboard_scope(filters::from_json($params['filters']), $dashboardkey, (int)$USER->id);

        $service = new kpi_service();
        return [
            'cards' => $service->cards($scopedfilters, $dashboardkey, (int)$USER->id),
            'notice' => '',
        ];
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'cards' => new \external_multiple_structure(new \external_single_structure([
                'key' => new \external_value(PARAM_ALPHANUMEXT, 'KPI key'),
                'label' => new \external_value(PARAM_TEXT, 'KPI label'),
                'value' => new \external_value(PARAM_TEXT, 'KPI value'),
                'unit' => new \external_value(PARAM_TEXT, 'KPI unit'),
                'status' => new \external_value(PARAM_ALPHANUMEXT, 'KPI status'),
                'trend' => new \external_value(PARAM_TEXT, 'KPI trend'),
                'trendstyle' => new \external_value(PARAM_ALPHA, 'Optional KPI trend display style', VALUE_OPTIONAL),
                'drilldownkey' => new \external_value(PARAM_ALPHANUMEXT, 'Drilldown key'),
                'filterstatus' => new \external_value(PARAM_ALPHANUMEXT, 'Optional status filter to apply on open', VALUE_OPTIONAL),
                'note' => new \external_value(PARAM_TEXT, 'Optional KPI note', VALUE_OPTIONAL),
                'help' => new \external_value(PARAM_TEXT, 'Short KPI explanation'),
            ])),
            'notice' => new \external_value(PARAM_TEXT, 'Optional notice'),
        ]);
    }
}
