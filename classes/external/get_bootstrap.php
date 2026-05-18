<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\external;

use block_dashboardanalytics\context_resolver;
use block_dashboardanalytics\service\dashboard_service;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

class get_bootstrap extends \external_api {

    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'contextid' => new \external_value(PARAM_INT, 'Block context ID'),
        ]);
    }

    public static function execute(int $contextid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['contextid' => $contextid]);
        $context = context_resolver::require_context((int)$params['contextid']);

        $service = new dashboard_service();
        return $service->bootstrap($context, (int)$USER->id);
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'hasaccess' => new \external_value(PARAM_BOOL, 'Whether the user can view a dashboard'),
            'dashboardkey' => new \external_value(PARAM_ALPHANUMEXT, 'Resolved dashboard key'),
            'dashboardname' => new \external_value(PARAM_TEXT, 'Resolved dashboard name'),
            'canviewemployeeidentity' => new \external_value(PARAM_BOOL, 'Whether named employees can be returned'),
            'tabs' => new \external_multiple_structure(new \external_single_structure([
                'key' => new \external_value(PARAM_ALPHANUMEXT, 'Tab key'),
                'label' => new \external_value(PARAM_TEXT, 'Tab label'),
                'active' => new \external_value(PARAM_BOOL, 'Whether this is the default active tab'),
            ])),
        ]);
    }
}

