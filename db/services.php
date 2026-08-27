<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$functions = [
    'block_dashboardanalytics_get_bootstrap' => [
        'classname' => 'block_dashboardanalytics\external\get_bootstrap',
        'methodname' => 'execute',
        'description' => 'Return dashboard role, tabs, and access metadata.',
        'type' => 'read',
        'ajax' => true,
    ],

    'block_dashboardanalytics_get_filter_options' => [
        'classname' => 'block_dashboardanalytics\external\get_filter_options',
        'methodname' => 'execute',
        'description' => 'Return global dashboard filter options.',
        'type' => 'read',
        'ajax' => true,
    ],

    'block_dashboardanalytics_get_kpis' => [
        'classname' => 'block_dashboardanalytics\external\get_kpis',
        'methodname' => 'execute',
        'description' => 'Return dashboard KPI cards for the selected filters.',
        'type' => 'read',
        'ajax' => true,
    ],

    'block_dashboardanalytics_get_drilldown' => [
        'classname' => 'block_dashboardanalytics\external\get_drilldown',
        'methodname' => 'execute',
        'description' => 'Return rows for a dashboard drilldown table.',
        'type' => 'read',
        'ajax' => true,
    ],

    'block_dashboardanalytics_get_visuals' => [
        'classname' => 'block_dashboardanalytics\external\get_visuals',
        'methodname' => 'execute',
        'description' => 'Return visual panels for dashboard tabs.',
        'type' => 'read',
        'ajax' => true,
    ],

    'block_dashboardanalytics_get_company_summary_modal' => [
        'classname' => 'block_dashboardanalytics\external\get_company_summary_modal',
        'methodname' => 'execute',
        'description' => 'Return company overview modal data.',
        'type' => 'read',
        'ajax' => true,
    ],

    'block_dashboardanalytics_get_act_config' => 
    [
        'classname' => 'block_dashboardanalytics\external\get_act_config',
        'methodname' => 'execute',
        'description' => 'Return Act of Completed Works form configuration.',
        'type' => 'read',
        'ajax' => true,
    ],

    'block_dashboardanalytics_load_act_services' => 
    [
        'classname' => 'block_dashboardanalytics\external\load_act_services',
        'methodname' => 'execute',
        'description' => 'Load AVR service rows from LMS completions.',
        'type' => 'read',
        'ajax' => true,
    ],

    'block_dashboardanalytics_get_report_builder_config' => [
        'classname' => 'block_dashboardanalytics\external\get_report_builder_config',
        'methodname' => 'execute',
        'description' => 'Return report builder configuration, templates, and available columns.',
        'type' => 'read',
        'ajax' => true,
    ],

    'block_dashboardanalytics_get_report_builder_rows' => [
        'classname' => 'block_dashboardanalytics\external\get_report_builder_rows',
        'methodname' => 'execute',
        'description' => 'Return report builder rows for the current filters.',
        'type' => 'read',
        'ajax' => true,
    ],

    'block_dashboardanalytics_save_report_template' => [
        'classname' => 'block_dashboardanalytics\external\save_report_template',
        'methodname' => 'execute',
        'description' => 'Save a private report template for the current user.',
        'type' => 'write',
        'ajax' => true,
    ],

    'block_dashboardanalytics_delete_report_template' => [
        'classname' => 'block_dashboardanalytics\external\delete_report_template',
        'methodname' => 'execute',
        'description' => 'Delete a private report template for the current user.',
        'type' => 'write',
        'ajax' => true,
    ],

    'block_dashboardanalytics_get_course_analytics_control' => [
        'classname' => 'block_dashboardanalytics\external\get_course_analytics_control',
        'methodname' => 'execute',
        'description' => 'Return the admin course analytics inclusion control list.',
        'type' => 'read',
        'ajax' => true,
    ],

    'block_dashboardanalytics_set_course_analytics_control' => [
        'classname' => 'block_dashboardanalytics\external\set_course_analytics_control',
        'methodname' => 'execute',
        'description' => 'Update whether a course is included in analytics.',
        'type' => 'write',
        'ajax' => true,
    ],

    'block_dashboardanalytics_get_expiry_workflow_data' => [
        'classname' => 'block_dashboardanalytics\external\get_expiry_workflow_data',
        'methodname' => 'execute',
        'description' => 'Return expiry workflow settings, counters, courses, and current cases.',
        'type' => 'read',
        'ajax' => true,
    ],

    'block_dashboardanalytics_save_expiry_workflow_settings' => [
        'classname' => 'block_dashboardanalytics\external\save_expiry_workflow_settings',
        'methodname' => 'execute',
        'description' => 'Save expiry workflow site and company settings.',
        'type' => 'write',
        'ajax' => true,
    ],

    'block_dashboardanalytics_set_expiry_workflow_course' => [
        'classname' => 'block_dashboardanalytics\external\set_expiry_workflow_course',
        'methodname' => 'execute',
        'description' => 'Toggle whether a course participates in expiry workflow notifications.',
        'type' => 'write',
        'ajax' => true,
    ],

    'block_dashboardanalytics_act_on_expiry_workflow_case' => [
        'classname' => 'block_dashboardanalytics\external\act_on_expiry_workflow_case',
        'methodname' => 'execute',
        'description' => 'Apply coordinator actions to expiry workflow cases.',
        'type' => 'write',
        'ajax' => true,
    ],

    'block_dashboardanalytics_notify_expiry_workflow_now' => [
        'classname' => 'block_dashboardanalytics\external\notify_expiry_workflow_now',
        'methodname' => 'execute',
        'description' => 'Send the expiry workflow digest immediately for one company.',
        'type' => 'write',
        'ajax' => true,
    ],
];
