<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\external;

use block_dashboardanalytics\context_resolver;
use block_dashboardanalytics\filters;
use block_dashboardanalytics\permissions;
use block_dashboardanalytics\service\visual_service;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

class get_visuals extends \external_api {

    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'contextid' => new \external_value(PARAM_INT, 'Block context ID'),
            'dashboardkey' => new \external_value(PARAM_ALPHANUMEXT, 'Dashboard key'),
            'tabkey' => new \external_value(PARAM_ALPHANUMEXT, 'Tab key'),
            'filters' => new \external_value(PARAM_RAW, 'JSON encoded filters', VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(int $contextid, string $dashboardkey, string $tabkey, string $filters = '{}'): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'dashboardkey' => $dashboardkey,
            'tabkey' => $tabkey,
            'filters' => $filters,
        ]);

        $context = context_resolver::require_context((int)$params['contextid']);
        $dashboardkey = permissions::require_dashboard_key($context, $params['dashboardkey'], (int)$USER->id);
        $scopedfilters = filters::apply_dashboard_scope(filters::from_json($params['filters']), $dashboardkey, (int)$USER->id);

        $service = new visual_service();
        return $service->panels(
            $dashboardkey,
            $params['tabkey'],
            $scopedfilters
        );
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'title' => new \external_value(PARAM_TEXT, 'Panel title'),
            'description' => new \external_value(PARAM_TEXT, 'Panel description'),
            'panels' => new \external_multiple_structure(new \external_single_structure([
                'key' => new \external_value(PARAM_ALPHANUMEXT, 'Panel key'),
                'title' => new \external_value(PARAM_TEXT, 'Panel title'),
                'type' => new \external_value(PARAM_ALPHANUMEXT, 'Visual type'),
                'description' => new \external_value(PARAM_TEXT, 'Description'),
                'items' => new \external_multiple_structure(new \external_single_structure([
                    'label' => new \external_value(PARAM_TEXT, 'Item label'),
                    'value' => new \external_value(PARAM_TEXT, 'Item value'),
                    'percent' => new \external_value(PARAM_FLOAT, 'Percent or bar width'),
                    'status' => new \external_value(PARAM_ALPHANUMEXT, 'Status'),
                    'meta' => new \external_value(PARAM_TEXT, 'Meta text'),
                    'segments' => new \external_multiple_structure(new \external_single_structure([
                        'label' => new \external_value(PARAM_TEXT, 'Segment label'),
                        'value' => new \external_value(PARAM_TEXT, 'Segment value'),
                        'percent' => new \external_value(PARAM_FLOAT, 'Segment percent'),
                        'status' => new \external_value(PARAM_ALPHANUMEXT, 'Segment status'),
                    ]), 'Optional grouped/stacked chart segments', VALUE_OPTIONAL),
                ])),
            ])),
        ]);
    }
}
