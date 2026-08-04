<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\external;

use block_dashboardanalytics\context_resolver;
use block_dashboardanalytics\repository\expiry_workflow_repository;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

class get_expiry_workflow_data extends \external_api {
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'contextid' => new \external_value(PARAM_INT, 'Block context ID'),
            'companyid' => new \external_value(PARAM_INT, 'Selected company id', VALUE_DEFAULT, 0),
            'coursesearch' => new \external_value(PARAM_TEXT, 'Course search', VALUE_DEFAULT, ''),
            'coursepage' => new \external_value(PARAM_INT, 'Course page', VALUE_DEFAULT, 0),
            'courseperpage' => new \external_value(PARAM_INT, 'Course rows per page', VALUE_DEFAULT, 20),
            'casesearch' => new \external_value(PARAM_TEXT, 'Case search', VALUE_DEFAULT, ''),
            'casestatus' => new \external_value(PARAM_ALPHANUMEXT, 'Case status', VALUE_DEFAULT, ''),
            'casepage' => new \external_value(PARAM_INT, 'Case page', VALUE_DEFAULT, 0),
            'caseperpage' => new \external_value(PARAM_INT, 'Case rows per page', VALUE_DEFAULT, 20),
        ]);
    }

    public static function execute(
        int $contextid,
        int $companyid = 0,
        string $coursesearch = '',
        int $coursepage = 0,
        int $courseperpage = 20,
        string $casesearch = '',
        string $casestatus = '',
        int $casepage = 0,
        int $caseperpage = 20
    ): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'companyid' => $companyid,
            'coursesearch' => $coursesearch,
            'coursepage' => $coursepage,
            'courseperpage' => $courseperpage,
            'casesearch' => $casesearch,
            'casestatus' => $casestatus,
            'casepage' => $casepage,
            'caseperpage' => $caseperpage,
        ]);

        $context = context_resolver::require_context((int)$params['contextid']);
        self::validate_context($context);

        $repository = new expiry_workflow_repository();
        if (!$repository->can_view_panel((int)$USER->id)) {
            throw new \moodle_exception('error:noaccess', 'block_dashboardanalytics');
        }

        return $repository->panel_data(
            (int)$USER->id,
            (int)$params['companyid'],
            trim((string)$params['coursesearch']),
            (int)$params['coursepage'],
            (int)$params['courseperpage'],
            trim((string)$params['casesearch']),
            trim((string)$params['casestatus']),
            (int)$params['casepage'],
            (int)$params['caseperpage']
        );
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'site' => new \external_single_structure([
                'enabled' => new \external_value(PARAM_BOOL, 'Master switch'),
                'thresholddays' => new \external_value(PARAM_INT, 'Threshold days'),
                'defaultrecipient' => new \external_value(PARAM_TEXT, 'Fallback recipient email'),
                'cansavesite' => new \external_value(PARAM_BOOL, 'Can save site settings'),
            ]),
            'company' => new \external_single_structure([
                'companyid' => new \external_value(PARAM_INT, 'Selected company id'),
                'companyoptions' => new \external_multiple_structure(new \external_single_structure([
                    'value' => new \external_value(PARAM_TEXT, 'Company id'),
                    'label' => new \external_value(PARAM_TEXT, 'Company label'),
                ])),
                'selectorvisible' => new \external_value(PARAM_BOOL, 'Show company selector'),
                'enabled' => new \external_value(PARAM_BOOL, 'Company switch'),
                'recipientids' => new \external_multiple_structure(new \external_value(PARAM_TEXT, 'Selected recipient id')),
                'recipientoptions' => new \external_multiple_structure(new \external_single_structure([
                    'value' => new \external_value(PARAM_TEXT, 'Recipient id'),
                    'label' => new \external_value(PARAM_TEXT, 'Recipient label'),
                ])),
                'cansavecompany' => new \external_value(PARAM_BOOL, 'Can save company settings'),
            ]),
            'counters' => new \external_multiple_structure(new \external_single_structure([
                'key' => new \external_value(PARAM_ALPHANUMEXT, 'Counter key'),
                'label' => new \external_value(PARAM_TEXT, 'Counter label'),
                'value' => new \external_value(PARAM_TEXT, 'Counter value'),
            ])),
            'courses' => new \external_single_structure([
                'rows' => new \external_multiple_structure(new \external_single_structure([
                    'courseid' => new \external_value(PARAM_INT, 'Course id'),
                    'fullname' => new \external_value(PARAM_TEXT, 'Course name'),
                    'shortname' => new \external_value(PARAM_TEXT, 'Course shortname'),
                    'enabled' => new \external_value(PARAM_BOOL, 'Enabled in expiry workflow'),
                ])),
                'totalcount' => new \external_value(PARAM_INT, 'Total matching courses'),
                'page' => new \external_value(PARAM_INT, 'Current course page'),
                'perpage' => new \external_value(PARAM_INT, 'Current course per-page'),
            ]),
            'cases' => new \external_single_structure([
                'rows' => new \external_multiple_structure(new \external_single_structure([
                    'caseid' => new \external_value(PARAM_INT, 'Case id'),
                    'userid' => new \external_value(PARAM_INT, 'User id'),
                    'courseid' => new \external_value(PARAM_INT, 'Course id'),
                    'employee' => new \external_value(PARAM_TEXT, 'Employee name'),
                    'employeeprofile' => new \external_value(PARAM_URL, 'Profile URL'),
                    'company' => new \external_value(PARAM_TEXT, 'Company'),
                    'course' => new \external_value(PARAM_TEXT, 'Course'),
                    'courserecordurl' => new \external_value(PARAM_URL, 'Course record URL'),
                    'issuedate' => new \external_value(PARAM_TEXT, 'Completion date'),
                    'expirydate' => new \external_value(PARAM_TEXT, 'Expiry date'),
                    'workflowstatus' => new \external_value(PARAM_ALPHANUMEXT, 'Status key'),
                    'workflowstatuslabel' => new \external_value(PARAM_TEXT, 'Status label'),
                    'cadencemode' => new \external_value(PARAM_ALPHANUMEXT, 'Cadence key'),
                ])),
                'totalcount' => new \external_value(PARAM_INT, 'Total matching cases'),
                'page' => new \external_value(PARAM_INT, 'Current case page'),
                'perpage' => new \external_value(PARAM_INT, 'Current case per-page'),
            ]),
            'cadenceoptions' => new \external_multiple_structure(new \external_single_structure([
                'value' => new \external_value(PARAM_ALPHANUMEXT, 'Cadence key'),
                'label' => new \external_value(PARAM_TEXT, 'Cadence label'),
            ])),
            'canmanagecases' => new \external_value(PARAM_BOOL, 'Can run coordinator actions'),
        ]);
    }
}
