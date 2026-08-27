<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\external;

use block_dashboardanalytics\context_resolver;
use block_dashboardanalytics\service\report_service;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

class delete_report_template extends \external_api {

    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'contextid' => new \external_value(PARAM_INT, 'Block context ID'),
            'templateid' => new \external_value(PARAM_INT, 'Template id'),
        ]);
    }

    public static function execute(int $contextid, int $templateid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'templateid' => $templateid,
        ]);

        context_resolver::require_context((int)$params['contextid']);

        $service = new report_service();
        $service->delete_template((int)$USER->id, (int)$params['templateid']);

        return ['json' => json_encode([
            'templates' => $service->builder_config(\context_system::instance(), (int)$USER->id)['templates'] ?? [],
        ])];
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'json' => new \external_value(PARAM_RAW, 'JSON encoded delete response'),
        ]);
    }
}
