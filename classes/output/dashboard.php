<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\output;

use block_dashboardanalytics\permissions;

defined('MOODLE_INTERNAL') || die();

class dashboard implements \renderable, \templatable {

    private \context $context;

    public function __construct(\context $context) {
        $this->context = $context;
    }

    public function export_for_template(\renderer_base $output): array {
        $dashboardkey = permissions::resolve_dashboard_key($this->context);
        if ($dashboardkey === null) {
            return [
                'hasaccess' => false,
                'contextid' => $this->context->id,
                'dashboardkey' => '',
                'dashboardname' => '',
                'tabs' => [],
            ];
        }

        return [
            'hasaccess' => true,
            'contextid' => $this->context->id,
            'dashboardkey' => $dashboardkey,
            'dashboardname' => permissions::dashboard_name($dashboardkey),
            'tabs' => permissions::dashboard_tabs($dashboardkey),
        ];
    }
}

