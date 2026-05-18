<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\output;

defined('MOODLE_INTERNAL') || die();

class renderer extends \plugin_renderer_base {

    public function render_dashboard(dashboard $dashboard): string {
        return $this->render_from_template('block_dashboardanalytics/dashboard', $dashboard->export_for_template($this));
    }
}

