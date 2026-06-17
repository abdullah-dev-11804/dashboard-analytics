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
$PAGE->requires->js_call_amd('block_dashboardanalytics/dashboard', 'init', [$context->id]);

$renderer = $PAGE->get_renderer('block_dashboardanalytics');

echo $OUTPUT->header();
echo $renderer->render(new dashboard($context, true));
echo $OUTPUT->footer();
