<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics;

defined('MOODLE_INTERNAL') || die();

class context_resolver {

    public static function require_context(int $contextid): \context {
        $context = \context::instance_by_id($contextid, MUST_EXIST);
        require_login();
        \external_api::validate_context($context);

        if (!permissions::can_view_block($context)) {
            throw new \required_capability_exception($context, 'block/dashboardanalytics:view', 'nopermissions', '');
        }

        return $context;
    }
}

