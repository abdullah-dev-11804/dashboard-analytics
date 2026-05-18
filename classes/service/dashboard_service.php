<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\service;

use block_dashboardanalytics\permissions;

defined('MOODLE_INTERNAL') || die();

class dashboard_service {

    public function bootstrap(\context $context, int $userid): array {
        $dashboardkey = permissions::resolve_dashboard_key($context, $userid);
        if ($dashboardkey === null) {
            return [
                'hasaccess' => false,
                'dashboardkey' => '',
                'dashboardname' => '',
                'canviewemployeeidentity' => false,
                'tabs' => [],
            ];
        }

        return [
            'hasaccess' => true,
            'dashboardkey' => $dashboardkey,
            'dashboardname' => permissions::dashboard_name($dashboardkey),
            'canviewemployeeidentity' => permissions::can_view_employee_identity($context, $userid),
            'tabs' => permissions::dashboard_tabs($dashboardkey),
        ];
    }
}

