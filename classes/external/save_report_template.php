<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\external;

use block_dashboardanalytics\context_resolver;
use block_dashboardanalytics\service\report_service;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

class save_report_template extends \external_api {

    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'contextid' => new \external_value(PARAM_INT, 'Block context ID'),
            'templateid' => new \external_value(PARAM_INT, 'Template id', VALUE_DEFAULT, 0),
            'name' => new \external_value(PARAM_TEXT, 'Template name'),
            'columns' => new \external_value(PARAM_RAW, 'JSON encoded column keys', VALUE_DEFAULT, '[]'),
            'filters' => new \external_value(PARAM_RAW, 'JSON encoded template filters', VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(int $contextid, int $templateid = 0, string $name = '', string $columns = '[]', string $filters = '{}'): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'templateid' => $templateid,
            'name' => $name,
            'columns' => $columns,
            'filters' => $filters,
        ]);

        $context = context_resolver::require_context((int)$params['contextid']);
        if (!is_siteadmin((int)$USER->id)) {
            throw new \moodle_exception('error:noaccess', 'block_dashboardanalytics');
        }

        $service = new report_service();
        $template = $service->save_template(
            (int)$USER->id,
            (int)$params['templateid'],
            (string)$params['name'],
            json_decode((string)$params['columns'], true) ?: [],
            json_decode((string)$params['filters'], true) ?: []
        );

        return ['json' => json_encode([
            'template' => $template,
            'templates' => $service->builder_config($context, (int)$USER->id)['templates'] ?? [],
        ])];
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'json' => new \external_value(PARAM_RAW, 'JSON encoded template payload'),
        ]);
    }
}
