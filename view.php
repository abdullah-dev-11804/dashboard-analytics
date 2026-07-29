<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

require_once(__DIR__ . '/../../config.php');

use block_dashboardanalytics\output\dashboard;
use block_dashboardanalytics\permissions;

require_login();

$context = context_system::instance();
if (!permissions::can_view_block($context)) {
    throw new required_capability_exception($context, 'block/dashboardanalytics:view', 'nopermissions', '');
}

$dashboardkey = permissions::resolve_dashboard_key($context);
$dashboardname = $dashboardkey !== null ? permissions::dashboard_name($dashboardkey) : get_string('pluginname', 'block_dashboardanalytics');

$url = new moodle_url('/blocks/dashboardanalytics/view.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title($dashboardname);
$PAGE->set_heading($dashboardname);
$PAGE->set_secondary_navigation(false);
$PAGE->add_body_class('path-block-dashboardanalytics-view');
$PAGE->requires->css(new moodle_url('/blocks/dashboardanalytics/styles.css'));
$PAGE->requires->css(new moodle_url('/blocks/dashboardanalytics/view.css'));
$PAGE->requires->js_call_amd('block_dashboardanalytics/dashboard', 'init', [$context->id]);

$renderer = $PAGE->get_renderer('block_dashboardanalytics');

echo $OUTPUT->header();
echo html_writer::start_div('dashboardanalytics-page');
echo html_writer::start_div('dashboardanalytics-page-toolbar');
if ($dashboardkey !== permissions::DASHBOARD_EMPLOYEE) {
    echo html_writer::start_div('dashboardanalytics-page-toolbar-statusmode', [
        'data-region' => 'statusmode-toolbar',
        'role' => 'group',
        'aria-label' => get_string('statusmode:label', 'block_dashboardanalytics'),
    ]);
    echo html_writer::tag('span', get_string('statusmode:label', 'block_dashboardanalytics'), [
        'class' => 'dashboardanalytics-page-toolbar-statusmode-label',
    ]);
    echo html_writer::start_div('dashboardanalytics-page-toolbar-statusmode-buttons');
    echo html_writer::tag(
        'button',
        get_string('statusmode:course', 'block_dashboardanalytics'),
        [
            'type' => 'button',
            'class' => 'dashboardanalytics-page-toolbar-button dashboardanalytics-page-toolbar-button-secondary is-active',
            'data-action' => 'statusmode-toggle',
            'data-statusmode' => 'course',
            'aria-pressed' => 'true',
        ]
    );
    echo html_writer::tag(
        'button',
        get_string('statusmode:employee', 'block_dashboardanalytics'),
        [
            'type' => 'button',
            'class' => 'dashboardanalytics-page-toolbar-button dashboardanalytics-page-toolbar-button-secondary',
            'data-action' => 'statusmode-toggle',
            'data-statusmode' => 'employee',
            'aria-pressed' => 'false',
        ]
    );
    echo html_writer::end_div();
    echo html_writer::end_div();
}
echo html_writer::tag(
    'button',
    get_string('view:hidesidebar', 'block_dashboardanalytics'),
    [
        'type' => 'button',
        'class' => 'dashboardanalytics-page-toolbar-button dashboardanalytics-page-toolbar-button-secondary',
        'data-action' => 'view-stretch-toggle',
        'aria-pressed' => 'false',
    ]
);
echo html_writer::end_div();
echo $renderer->render(new dashboard($context, true));
echo html_writer::end_div();
echo $OUTPUT->footer();
