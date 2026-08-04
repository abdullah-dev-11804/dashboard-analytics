<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\external;

use block_dashboardanalytics\context_resolver;
use block_dashboardanalytics\repository\expiry_workflow_repository;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

class save_expiry_workflow_settings extends \external_api {
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'contextid' => new \external_value(PARAM_INT, 'Block context ID'),
            'companyid' => new \external_value(PARAM_INT, 'Company id', VALUE_DEFAULT, 0),
            'siteenabled' => new \external_value(PARAM_BOOL, 'Master switch', VALUE_DEFAULT, false),
            'thresholddays' => new \external_value(PARAM_INT, 'Threshold days', VALUE_DEFAULT, 30),
            'defaultrecipient' => new \external_value(PARAM_TEXT, 'Fallback recipient email', VALUE_DEFAULT, ''),
            'companyenabled' => new \external_value(PARAM_BOOL, 'Company switch', VALUE_DEFAULT, true),
            'recipientids' => new \external_multiple_structure(new \external_value(PARAM_INT, 'Recipient user id'), 'Selected recipients', VALUE_DEFAULT, []),
        ]);
    }

    public static function execute(
        int $contextid,
        int $companyid = 0,
        bool $siteenabled = false,
        int $thresholddays = 30,
        string $defaultrecipient = '',
        bool $companyenabled = true,
        array $recipientids = []
    ): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'companyid' => $companyid,
            'siteenabled' => $siteenabled,
            'thresholddays' => $thresholddays,
            'defaultrecipient' => $defaultrecipient,
            'companyenabled' => $companyenabled,
            'recipientids' => $recipientids,
        ]);

        $context = context_resolver::require_context((int)$params['contextid']);
        self::validate_context($context);
        require_sesskey();

        $repository = new expiry_workflow_repository();

        if (is_siteadmin((int)$USER->id)) {
            $repository->save_site_settings(
                (bool)$params['siteenabled'],
                max(1, (int)$params['thresholddays']),
                trim((string)$params['defaultrecipient'])
            );
        }

        if ((int)$params['companyid'] > 0) {
            if (!$repository->can_manage_settings((int)$USER->id, (int)$params['companyid'])) {
                throw new \moodle_exception('error:noaccess', 'block_dashboardanalytics');
            }

            $repository->save_company_config(
                (int)$params['companyid'],
                (bool)$params['companyenabled'],
                array_map('intval', $params['recipientids']),
                (int)$USER->id
            );
        }

        return ['saved' => true];
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'saved' => new \external_value(PARAM_BOOL, 'Saved flag'),
        ]);
    }
}
