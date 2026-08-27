<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\external;

use block_dashboardanalytics\context_resolver;
use block_dashboardanalytics\filters;
use block_dashboardanalytics\permissions;
use block_dashboardanalytics\service\report_service;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

class get_report_builder_rows extends \external_api {

    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'contextid' => new \external_value(PARAM_INT, 'Block context ID'),
            'dashboardkey' => new \external_value(PARAM_ALPHANUMEXT, 'Dashboard key'),
            'filters' => new \external_value(PARAM_RAW, 'JSON encoded filters', VALUE_DEFAULT, '{}'),
            'options' => new \external_value(PARAM_RAW, 'JSON encoded report options', VALUE_DEFAULT, '{}'),
            'page' => new \external_value(PARAM_INT, 'Page number', VALUE_DEFAULT, 0),
            'perpage' => new \external_value(PARAM_INT, 'Rows per page', VALUE_DEFAULT, 20),
            'sortkey' => new \external_value(PARAM_TEXT, 'Sort key', VALUE_DEFAULT, 'completiondate'),
            'sortdir' => new \external_value(PARAM_TEXT, 'Sort direction', VALUE_DEFAULT, 'asc'),
        ]);
    }

    public static function execute(
        int $contextid,
        string $dashboardkey,
        string $filters = '{}',
        string $options = '{}',
        int $page = 0,
        int $perpage = 20,
        string $sortkey = 'completiondate',
        string $sortdir = 'asc'
    ): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'dashboardkey' => $dashboardkey,
            'filters' => $filters,
            'options' => $options,
            'page' => $page,
            'perpage' => $perpage,
            'sortkey' => $sortkey,
            'sortdir' => $sortdir,
        ]);

        $context = context_resolver::require_context((int)$params['contextid']);
        $dashboardkey = permissions::require_dashboard_key($context, (string)$params['dashboardkey'], (int)$USER->id);
        $scopedfilters = filters::apply_dashboard_scope(filters::from_json($params['filters']), $dashboardkey, (int)$USER->id);

        $service = new report_service();
        return ['json' => json_encode($service->builder_rows(
            $scopedfilters,
            json_decode((string)$params['options'], true) ?: [],
            (int)$params['page'],
            (int)$params['perpage'],
            (string)$params['sortkey'],
            (string)$params['sortdir']
        ))];
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'json' => new \external_value(PARAM_RAW, 'JSON encoded report rows'),
        ]);
    }
}
