<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\external;

use block_dashboardanalytics\context_resolver;
use block_dashboardanalytics\filters;
use block_dashboardanalytics\permissions;
use block_dashboardanalytics\repository\dimension_repository;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

class get_filter_options extends \external_api {

    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'contextid' => new \external_value(PARAM_INT, 'Block context ID'),
            'filters' => new \external_value(PARAM_RAW, 'JSON encoded filters', VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(int $contextid, string $filters = '{}'): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'filters' => $filters,
        ]);
        $context = context_resolver::require_context((int)$params['contextid']);
        $dashboardkey = permissions::resolve_dashboard_key($context, (int)$USER->id);
        if ($dashboardkey === null) {
            throw new \moodle_exception('error:noaccess', 'block_dashboardanalytics');
        }

        $repository = new dimension_repository();
        $scopefilters = filters::apply_dashboard_scope(
            filters::from_json($params['filters']),
            $dashboardkey,
            (int)$USER->id
        );
        return ['groups' => $repository->get_filter_groups($scopefilters)];
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'groups' => new \external_multiple_structure(new \external_single_structure([
                'key' => new \external_value(PARAM_ALPHANUMEXT, 'Filter key'),
                'label' => new \external_value(PARAM_TEXT, 'Filter label'),
                'multiple' => new \external_value(PARAM_BOOL, 'Whether multiple values are supported'),
                'searchable' => new \external_value(PARAM_BOOL, 'Whether this filter should render as a searchable suggestion field', VALUE_DEFAULT, false),
                'options' => new \external_multiple_structure(new \external_single_structure([
                    'value' => new \external_value(PARAM_RAW, 'Option value'),
                    'label' => new \external_value(PARAM_TEXT, 'Option label'),
                ])),
            ])),
        ]);
    }
}
