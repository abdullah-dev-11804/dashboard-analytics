<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\external;

use block_dashboardanalytics\context_resolver;
use block_dashboardanalytics\repository\course_analytics_repository;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

class set_course_analytics_control extends \external_api {

    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'contextid' => new \external_value(PARAM_INT, 'Block context ID'),
            'courseid' => new \external_value(PARAM_INT, 'Course id'),
            'enabled' => new \external_value(PARAM_BOOL, 'Whether analytics should include this course'),
        ]);
    }

    public static function execute(int $contextid, int $courseid, bool $enabled): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'courseid' => $courseid,
            'enabled' => $enabled,
        ]);

        $context = context_resolver::require_context((int)$params['contextid']);
        self::validate_context($context);

        if (!is_siteadmin((int)$USER->id) && !has_capability('block/dashboardanalytics:managesettings', $context, (int)$USER->id)) {
            throw new \moodle_exception('error:noaccess', 'block_dashboardanalytics');
        }

        require_sesskey();

        $repository = new course_analytics_repository();
        $repository->set_course_enabled((int)$params['courseid'], (bool)$params['enabled']);

        return [
            'courseid' => (int)$params['courseid'],
            'enabled' => (bool)$params['enabled'],
        ];
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'courseid' => new \external_value(PARAM_INT, 'Course id'),
            'enabled' => new \external_value(PARAM_BOOL, 'Saved analytics inclusion state'),
        ]);
    }
}
