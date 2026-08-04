<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\external;

use block_dashboardanalytics\context_resolver;
use block_dashboardanalytics\repository\expiry_workflow_repository;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

class act_on_expiry_workflow_case extends \external_api {
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'contextid' => new \external_value(PARAM_INT, 'Block context ID'),
            'caseid' => new \external_value(PARAM_INT, 'Workflow case id'),
            'action' => new \external_value(PARAM_ALPHANUMEXT, 'Action key'),
            'cadence' => new \external_value(PARAM_ALPHANUMEXT, 'Cadence key', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(int $contextid, int $caseid, string $action, string $cadence = ''): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'caseid' => $caseid,
            'action' => $action,
            'cadence' => $cadence,
        ]);

        $context = context_resolver::require_context((int)$params['contextid']);
        self::validate_context($context);
        require_sesskey();

        $repository = new expiry_workflow_repository();
        $result = $repository->take_action(
            (int)$params['caseid'],
            (string)$params['action'],
            (int)$USER->id,
            ['cadence' => (string)$params['cadence']]
        );

        return [
            'status' => !empty($result['status']),
            'message' => (string)($result['message'] ?? ''),
        ];
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'status' => new \external_value(PARAM_BOOL, 'Action success flag'),
            'message' => new \external_value(PARAM_TEXT, 'Result message'),
        ]);
    }
}
