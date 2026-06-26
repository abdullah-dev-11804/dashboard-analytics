<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\external;

use block_dashboardanalytics\context_resolver;
use block_dashboardanalytics\service\report_service;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

class get_act_config extends \external_api {

    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'contextid' => new \external_value(PARAM_INT, 'Block context ID'),
        ]);
    }

    public static function execute(int $contextid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
        ]);

        context_resolver::require_context((int)$params['contextid']);

        $service = new report_service();
        return $service->config();
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'companies' => new \external_multiple_structure(new \external_single_structure([
                'value' => new \external_value(PARAM_RAW, 'Company id'),
                'label' => new \external_value(PARAM_TEXT, 'Company name'),
            ])),
            'months' => new \external_multiple_structure(new \external_single_structure([
                'value' => new \external_value(PARAM_RAW, 'Month number'),
                'label' => new \external_value(PARAM_TEXT, 'Month label'),
            ])),
            'years' => new \external_multiple_structure(new \external_single_structure([
                'value' => new \external_value(PARAM_RAW, 'Year'),
                'label' => new \external_value(PARAM_TEXT, 'Year label'),
            ])),
            'defaultmonth' => new \external_value(PARAM_INT, 'Default month'),
            'defaultyear' => new \external_value(PARAM_INT, 'Default year'),
            'defaultprovider' => new \external_value(PARAM_TEXT, 'Default service provider'),
        ]);
    }
}