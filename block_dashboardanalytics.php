<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

use block_dashboardanalytics\output\dashboard;
use block_dashboardanalytics\permissions;

class block_dashboardanalytics extends block_base {

    public function init(): void {
        $this->title = get_string('pluginname', 'block_dashboardanalytics');
    }

    public function has_config(): bool {
        return true;
    }

    public function instance_allow_multiple(): bool {
        return false;
    }

    public function applicable_formats(): array {
        return [
            'my' => true,
            'site-index' => true,
            'course-view' => false,
            'all' => false,
        ];
    }

    public function get_content() {
        global $PAGE;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';

        if (empty($this->context) || !permissions::can_view_block($this->context)) {
            $this->content->text = '';
            return $this->content;
        }

        $PAGE->requires->css(new moodle_url('/blocks/dashboardanalytics/styles.css'));
        $PAGE->requires->js_call_amd('block_dashboardanalytics/dashboard', 'init', [$this->context->id]);

        $renderer = $PAGE->get_renderer('block_dashboardanalytics');
        $this->content->text = $renderer->render(new dashboard($this->context));

        return $this->content;
    }
}

