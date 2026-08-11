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
        if (!permissions::tab_is_allowed($dashboardkey, $params['tabkey'], $context, (int)$USER->id)) {
            throw new \moodle_exception('error:noaccess', 'block_dashboardanalytics');
        }
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
                'threshold' => new \external_value(PARAM_FLOAT, 'Optional reference threshold', VALUE_OPTIONAL),
                'secondarythreshold' => new \external_value(PARAM_FLOAT, 'Optional second reference threshold', VALUE_OPTIONAL),
                'currentpercent' => new \external_value(PARAM_FLOAT, 'Optional current KPI percent', VALUE_OPTIONAL),
                'currentdelta' => new \external_value(PARAM_FLOAT, 'Optional current KPI percent delta', VALUE_OPTIONAL),
                'thresholdlabel' => new \external_value(PARAM_TEXT, 'Optional reference threshold label', VALUE_OPTIONAL),
                'secondarythresholdlabel' => new \external_value(PARAM_TEXT, 'Optional second reference threshold label', VALUE_OPTIONAL),
                'emptymessage' => new \external_value(PARAM_TEXT, 'Optional empty-state message', VALUE_OPTIONAL),
                'chartlabel' => new \external_value(PARAM_TEXT, 'Optional chart badge label', VALUE_OPTIONAL),
                'interactivelabel' => new \external_value(PARAM_TEXT, 'Optional interactive badge label', VALUE_OPTIONAL),
                'formula' => new \external_value(PARAM_TEXT, 'Optional formula tooltip', VALUE_OPTIONAL),
                'footer' => new \external_value(PARAM_TEXT, 'Optional panel footer note', VALUE_OPTIONAL),
                'alertmessage' => new \external_value(PARAM_TEXT, 'Optional panel alert message', VALUE_OPTIONAL),
                'alertstatus' => new \external_value(PARAM_ALPHANUMEXT, 'Optional panel alert status', VALUE_OPTIONAL),
                'tabs' => new \external_multiple_structure(new \external_single_structure([
                    'key' => new \external_value(PARAM_TEXT, 'Tab key'),
                    'label' => new \external_value(PARAM_TEXT, 'Tab label'),
                    'active' => new \external_value(PARAM_BOOL, 'Tab active state'),
                    'companyid' => new \external_value(PARAM_INT, 'Optional company id', VALUE_OPTIONAL),
                    'companyname' => new \external_value(PARAM_TEXT, 'Optional company name', VALUE_OPTIONAL),
                ]), 'Optional panel tabs', VALUE_OPTIONAL),
                'items' => new \external_multiple_structure(new \external_single_structure([
                    'label' => new \external_value(PARAM_TEXT, 'Item label'),
                    'value' => new \external_value(PARAM_TEXT, 'Item value'),
                    'percent' => new \external_value(PARAM_FLOAT, 'Percent or bar width'),
                    'status' => new \external_value(PARAM_ALPHANUMEXT, 'Status'),
                    'meta' => new \external_value(PARAM_TEXT, 'Meta text'),
                    'groupkey' => new \external_value(PARAM_TEXT, 'Optional group key', VALUE_OPTIONAL),
                    'periodkey' => new \external_value(PARAM_TEXT, 'Optional forecast period key', VALUE_OPTIONAL),
                    'rowlabel' => new \external_value(PARAM_TEXT, 'Optional row label', VALUE_OPTIONAL),
                    'columnlabel' => new \external_value(PARAM_TEXT, 'Optional column label', VALUE_OPTIONAL),
                    'fromts' => new \external_value(PARAM_INT, 'Optional interval start timestamp', VALUE_OPTIONAL),
                    'tots' => new \external_value(PARAM_INT, 'Optional interval end timestamp', VALUE_OPTIONAL),
                    'drilldownkey' => new \external_value(PARAM_ALPHANUMEXT, 'Optional drilldown key', VALUE_OPTIONAL),
                    'companyid' => new \external_value(PARAM_INT, 'Optional company id', VALUE_OPTIONAL),
                    'companyname' => new \external_value(PARAM_TEXT, 'Optional company name', VALUE_OPTIONAL),
                    'courseid' => new \external_value(PARAM_INT, 'Optional course id', VALUE_OPTIONAL),
                    'colour' => new \external_value(PARAM_RAW, 'Optional colour token', VALUE_OPTIONAL),
                    'url' => new \external_value(PARAM_URL, 'Optional course URL', VALUE_OPTIONAL),
                    'rating' => new \external_value(PARAM_FLOAT, 'Optional course rating', VALUE_OPTIONAL),
                    'ratinglabel' => new \external_value(PARAM_TEXT, 'Optional formatted course rating', VALUE_OPTIONAL),
                    'reviews' => new \external_value(PARAM_INT, 'Optional review count', VALUE_OPTIONAL),
                    'nps' => new \external_value(PARAM_INT, 'Optional NPS value', VALUE_OPTIONAL),
                    'npslabel' => new \external_value(PARAM_TEXT, 'Optional formatted NPS value', VALUE_OPTIONAL),
                    'relevance' => new \external_value(PARAM_FLOAT, 'Optional relevance score', VALUE_OPTIONAL),
                    'relevancelabel' => new \external_value(PARAM_TEXT, 'Optional formatted relevance score', VALUE_OPTIONAL),
                    'relevancestatus' => new \external_value(PARAM_ALPHANUMEXT, 'Optional relevance status', VALUE_OPTIONAL),
                    'latestfeedback' => new \external_value(PARAM_TEXT, 'Optional latest feedback text', VALUE_OPTIONAL),
                    'activevalue' => new \external_value(PARAM_TEXT, 'Optional active time label', VALUE_OPTIONAL),
                    'sessionvalue' => new \external_value(PARAM_TEXT, 'Optional session time label', VALUE_OPTIONAL),
                    'activepercent' => new \external_value(PARAM_FLOAT, 'Optional active time width', VALUE_OPTIONAL),
                    'sessionpercent' => new \external_value(PARAM_FLOAT, 'Optional session time width', VALUE_OPTIONAL),
                    'segments' => new \external_multiple_structure(new \external_single_structure([
                        'label' => new \external_value(PARAM_TEXT, 'Segment label'),
                        'value' => new \external_value(PARAM_TEXT, 'Segment value'),
                        'percent' => new \external_value(PARAM_FLOAT, 'Segment percent'),
                        'status' => new \external_value(PARAM_ALPHANUMEXT, 'Segment status'),
                        'drilldownkey' => new \external_value(PARAM_ALPHANUMEXT, 'Optional drilldown key', VALUE_OPTIONAL),
                        'companyid' => new \external_value(PARAM_INT, 'Optional company id', VALUE_OPTIONAL),
                        'companyname' => new \external_value(PARAM_TEXT, 'Optional company name', VALUE_OPTIONAL),
                        'courseid' => new \external_value(PARAM_INT, 'Optional course id', VALUE_OPTIONAL),
                        'colour' => new \external_value(PARAM_RAW, 'Optional colour token', VALUE_OPTIONAL),
                        'fromts' => new \external_value(PARAM_INT, 'Optional interval start timestamp', VALUE_OPTIONAL),
                        'tots' => new \external_value(PARAM_INT, 'Optional interval end timestamp', VALUE_OPTIONAL),
                    ]), 'Optional grouped/stacked chart segments', VALUE_OPTIONAL),
                ])),
            ])),
        ]);
    }
}
