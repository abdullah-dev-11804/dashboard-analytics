<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\external;

use block_dashboardanalytics\context_resolver;
use block_dashboardanalytics\repository\expiry_workflow_repository;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

class set_expiry_workflow_course extends \external_api {
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'contextid' => new \external_value(PARAM_INT, 'Block context ID'),
            'companyid' => new \external_value(PARAM_INT, 'Company id', VALUE_DEFAULT, 0),
            'courseid' => new \external_value(PARAM_INT, 'Course id'),
            'enabled' => new \external_value(PARAM_BOOL, 'Course enabled flag'),
        ]);
    }

    public static function execute(int $contextid, int $companyid, int $courseid, bool $enabled): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'companyid' => $companyid,
            'courseid' => $courseid,
            'enabled' => $enabled,
        ]);

        $context = context_resolver::require_context((int)$params['contextid']);
        self::validate_context($context);
        require_sesskey();

        $repository = new expiry_workflow_repository();
        if (!$repository->can_manage_settings((int)$USER->id, (int)$params['companyid'])) {
            throw new \moodle_exception('error:noaccess', 'block_dashboardanalytics');
        }

        $repository->set_course_enabled((int)$params['courseid'], (bool)$params['enabled'], (int)$USER->id);

        return [
            'courseid' => (int)$params['courseid'],
            'enabled' => (bool)$params['enabled'],
        ];
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'courseid' => new \external_value(PARAM_INT, 'Course id'),
            'enabled' => new \external_value(PARAM_BOOL, 'Enabled flag'),
        ]);
    }
}
