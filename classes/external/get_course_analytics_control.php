<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\external;

use block_dashboardanalytics\context_resolver;
use block_dashboardanalytics\repository\course_analytics_repository;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

class get_course_analytics_control extends \external_api {

    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'contextid' => new \external_value(PARAM_INT, 'Block context ID'),
            'search' => new \external_value(PARAM_TEXT, 'Course search text', VALUE_DEFAULT, ''),
            'page' => new \external_value(PARAM_INT, 'Zero-based page index', VALUE_DEFAULT, 0),
            'perpage' => new \external_value(PARAM_INT, 'Rows per page', VALUE_DEFAULT, 20),
        ]);
    }

    public static function execute(int $contextid, string $search = '', int $page = 0, int $perpage = 20): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'search' => $search,
            'page' => $page,
            'perpage' => $perpage,
        ]);

        $context = context_resolver::require_context((int)$params['contextid']);
        self::validate_context($context);

        if (!is_siteadmin((int)$USER->id) && !has_capability('block/dashboardanalytics:managesettings', $context, (int)$USER->id)) {
            throw new \moodle_exception('error:noaccess', 'block_dashboardanalytics');
        }

        $repository = new course_analytics_repository();
        return $repository->list_courses(trim((string)$params['search']), (int)$params['page'], (int)$params['perpage']);
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'rows' => new \external_multiple_structure(new \external_single_structure([
                'courseid' => new \external_value(PARAM_INT, 'Course id'),
                'fullname' => new \external_value(PARAM_TEXT, 'Course fullname'),
                'shortname' => new \external_value(PARAM_TEXT, 'Course shortname'),
                'visible' => new \external_value(PARAM_BOOL, 'Course visibility'),
                'analyticsenabled' => new \external_value(PARAM_BOOL, 'Analytics inclusion flag'),
            ])),
            'totalcount' => new \external_value(PARAM_INT, 'Total matching course count'),
            'page' => new \external_value(PARAM_INT, 'Current page'),
            'perpage' => new \external_value(PARAM_INT, 'Current page size'),
        ]);
    }
}
