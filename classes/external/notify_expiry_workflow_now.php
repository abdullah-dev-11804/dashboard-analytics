<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\external;

use block_dashboardanalytics\context_resolver;
use block_dashboardanalytics\repository\expiry_workflow_repository;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

class notify_expiry_workflow_now extends \external_api {
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'contextid' => new \external_value(PARAM_INT, 'Block context ID'),
            'companyid' => new \external_value(PARAM_INT, 'Company id'),
        ]);
    }

    public static function execute(int $contextid, int $companyid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'companyid' => $companyid,
        ]);

        $context = context_resolver::require_context((int)$params['contextid']);
        self::validate_context($context);
        require_sesskey();

        $repository = new expiry_workflow_repository();
        $result = $repository->send_company_digest_now(
            (int)$params['companyid'],
            (int)$USER->id
        );

        return [
            'status' => !empty($result['status']),
            'message' => (string)($result['message'] ?? ''),
            'casesent' => (int)($result['casesent'] ?? 0),
            'recipients' => (int)($result['recipients'] ?? 0),
        ];
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'status' => new \external_value(PARAM_BOOL, 'Action success flag'),
            'message' => new \external_value(PARAM_TEXT, 'Result message'),
            'casesent' => new \external_value(PARAM_INT, 'Case rows included in sent digests'),
            'recipients' => new \external_value(PARAM_INT, 'Recipient count'),
        ]);
    }
}
