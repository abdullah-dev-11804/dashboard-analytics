<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\repository;

defined('MOODLE_INTERNAL') || die();

class dimension_repository {

    public function get_filter_groups(array $scopefilters = []): array {
        $companyrepo = new company_repository();

        $groups = [
            [
                'key' => $companyrepo->company_filter_key(),
                'label' => get_string('filter:companies', 'block_dashboardanalytics'),
                'multiple' => true,
                'options' => $companyrepo->get_company_options($scopefilters),
            ],
        ];

        if ($companyrepo->has_iomad_tables()) {
            $groups[] = [
                'key' => 'departments',
                'label' => get_string('filter:departments', 'block_dashboardanalytics'),
                'multiple' => true,
                'options' => $this->departments($scopefilters),
            ];
        }

        $groups[] = [
            'key' => 'locations',
            'label' => get_string('filter:locations', 'block_dashboardanalytics'),
            'multiple' => true,
            'options' => $this->locations($scopefilters),
        ];

        $positions = $this->positions($scopefilters);
        if ($positions) {
            $groups[] = [
                'key' => 'positions',
                'label' => get_string('filter:positions', 'block_dashboardanalytics'),
                'multiple' => true,
                'options' => $positions,
            ];
        }

        $groups[] = [
            'key' => 'courseids',
            'label' => get_string('filter:courses', 'block_dashboardanalytics'),
            'multiple' => true,
            'options' => $this->courses(),
        ];

        return array_values(array_filter($groups, static function(array $group): bool {
            return !empty($group['options']);
        }));
    }

    private function departments(array $scopefilters): array {
        global $DB;

        $employee = new employee_repository();
        $filter = $employee->user_filter_sql($scopefilters, 'u', 'dimensiondepartment');

        $sql = "SELECT DISTINCT u.department
                  FROM {user} u
                 WHERE {$filter['sql']}
                   AND u.department <> ''
              ORDER BY u.department ASC";

        return $this->text_options($DB->get_fieldset_sql($sql, $filter['params']));
    }

    private function locations(array $scopefilters): array {
        global $DB;

        $employee = new employee_repository();
        $filter = $employee->user_filter_sql($scopefilters, 'u', 'dimensionlocation');

        $sql = "SELECT DISTINCT u.city
                  FROM {user} u
                 WHERE {$filter['sql']}
                   AND u.city <> ''
              ORDER BY u.city ASC";

        return $this->text_options($DB->get_fieldset_sql($sql, $filter['params']));
    }

    private function positions(array $scopefilters): array {
        global $DB;

        $positionfield = trim((string)get_config('block_dashboardanalytics', 'positionfield'));
        if ($positionfield === '') {
            return [];
        }

        $employee = new employee_repository();
        $filter = $employee->user_filter_sql($scopefilters, 'u', 'dimensionposition');

        $sql = "SELECT DISTINCT uid.data
                  FROM {user_info_data} uid
                  JOIN {user_info_field} uif ON uif.id = uid.fieldid
                  JOIN {user} u ON u.id = uid.userid
                 WHERE uif.shortname = :shortname
                   AND uid.data <> ''
                   AND {$filter['sql']}
              ORDER BY uid.data ASC";

        return $this->text_options($DB->get_fieldset_sql($sql, ['shortname' => $positionfield] + $filter['params']));
    }

    private function courses(): array {
        global $DB;

        $records = $DB->get_records_select(
            'course',
            'id <> :siteid AND visible = 1',
            ['siteid' => SITEID],
            'fullname ASC',
            'id, fullname',
            0,
            300
        );

        $options = [];
        foreach ($records as $record) {
            $options[] = ['value' => (string)$record->id, 'label' => format_string($record->fullname)];
        }
        return $options;
    }

    private function text_options(array $values): array {
        $options = [];
        foreach ($values as $value) {
            $value = trim((string)$value);
            if ($value !== '') {
                $options[] = ['value' => $value, 'label' => $value];
            }
        }
        return $options;
    }
}
