<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\output;

use block_dashboardanalytics\permissions;

defined('MOODLE_INTERNAL') || die();

class dashboard implements \renderable, \templatable {

    private \context $context;
    private bool $fullpage;

    public function __construct(\context $context, bool $fullpage = false) {
        $this->context = $context;
        $this->fullpage = $fullpage;
    }

    public function export_for_template(\renderer_base $output): array {
        $dashboardkey = permissions::resolve_dashboard_key($this->context);
        if ($dashboardkey === null) {
            return [
                'hasaccess' => false,
                'contextid' => $this->context->id,
                'dashboardkey' => '',
                'dashboardname' => '',
                'dashboardtitles' => $this->dashboard_title_strings(),
                'fullpage' => $this->fullpage,
                'tabs' => [],
            ];
        }

        return [
            'hasaccess' => true,
            'contextid' => $this->context->id,
            'dashboardkey' => $dashboardkey,
            'dashboardname' => permissions::dashboard_name($dashboardkey),
            'dashboardtitles' => $this->dashboard_title_strings(),
            'fullpage' => $this->fullpage,
            'showstatusmode' => $dashboardkey !== permissions::DASHBOARD_EMPLOYEE,
            'tabs' => permissions::dashboard_tabs($dashboardkey, $this->context),
        ];
    }

    private function dashboard_title_strings(): array {
        $manager = \get_string_manager();
        $languages = ['en', 'ru', 'kk'];
        $keys = [
            'company' => 'dashboard:company',
            'client' => 'dashboard:client',
            'employee' => 'dashboard:employee',
            'plugin' => 'pluginname',
        ];
        $strings = [];

        foreach ($keys as $key => $identifier) {
            foreach ($languages as $language) {
                $strings[$key . '_' . $language] = $manager->get_string(
                    $identifier,
                    'block_dashboardanalytics',
                    null,
                    $language
                );
            }
        }

        return $strings;
    }
}
