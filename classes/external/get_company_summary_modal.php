<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\external;

use block_dashboardanalytics\context_resolver;
use block_dashboardanalytics\filters;
use block_dashboardanalytics\permissions;
use block_dashboardanalytics\repository\overview_repository;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

class get_company_summary_modal extends \external_api {

    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'contextid' => new \external_value(PARAM_INT, 'Block context ID'),
            'dashboardkey' => new \external_value(PARAM_ALPHANUMEXT, 'Dashboard key'),
            'companyname' => new \external_value(PARAM_TEXT, 'Company name'),
            'companyid' => new \external_value(PARAM_INT, 'Company id', VALUE_DEFAULT, 0),
            'filters' => new \external_value(PARAM_RAW, 'JSON encoded filters', VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(
        int $contextid,
        string $dashboardkey,
        string $companyname,
        int $companyid = 0,
        string $filters = '{}'
    ): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'dashboardkey' => $dashboardkey,
            'companyname' => $companyname,
            'companyid' => $companyid,
            'filters' => $filters,
        ]);

        $context = context_resolver::require_context((int)$params['contextid']);
        $resolveddashboardkey = permissions::require_dashboard_key($context, $params['dashboardkey'], (int)$USER->id);
        if ($resolveddashboardkey !== permissions::DASHBOARD_COMPANY) {
            throw new \moodle_exception('error:noaccess', 'block_dashboardanalytics');
        }

        $scopedfilters = filters::apply_dashboard_scope(
            filters::from_json($params['filters']),
            $resolveddashboardkey,
            (int)$USER->id
        );

        $overview = new overview_repository();
        return $overview->company_health_modal_data(
            $scopedfilters,
            $params['companyname'],
            (int)$params['companyid']
        );
    }

    public static function execute_returns(): \external_single_structure {
        $card = new \external_single_structure([
            'label' => new \external_value(PARAM_TEXT, 'Card label'),
            'value' => new \external_value(PARAM_TEXT, 'Card value'),
            'status' => new \external_value(PARAM_ALPHANUMEXT, 'Card status'),
        ]);

        $courseitem = new \external_single_structure([
            'label' => new \external_value(PARAM_TEXT, 'Course label'),
            'value' => new \external_value(PARAM_TEXT, 'Course value'),
            'percent' => new \external_value(PARAM_FLOAT, 'Course percent'),
            'status' => new \external_value(PARAM_ALPHANUMEXT, 'Course status'),
        ]);

        return new \external_single_structure([
            'title' => new \external_value(PARAM_TEXT, 'Modal title'),
            'subtitle' => new \external_value(PARAM_TEXT, 'Modal subtitle'),
            'statuskey' => new \external_value(PARAM_ALPHANUMEXT, 'Overall status key'),
            'statuslabel' => new \external_value(PARAM_TEXT, 'Overall status label'),
            'summarycards' => new \external_multiple_structure($card),
            'courseitems' => new \external_multiple_structure($courseitem),
            'additionalcards' => new \external_multiple_structure($card),
            'courseheading' => new \external_value(PARAM_TEXT, 'Course section heading'),
            'metricsheading' => new \external_value(PARAM_TEXT, 'Metrics section heading'),
            'closebutton' => new \external_value(PARAM_TEXT, 'Close button label'),
            'exportbutton' => new \external_value(PARAM_TEXT, 'Export button label'),
        ]);
    }
}
