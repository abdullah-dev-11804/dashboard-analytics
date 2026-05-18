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
];

