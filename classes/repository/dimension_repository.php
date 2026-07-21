<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\repository;

defined('MOODLE_INTERNAL') || die();

class dimension_repository {

    public function get_filter_groups(array $scopefilters = []): array {
        $companyrepo = new company_repository();

        $groups = [
            [
                'key' => $companyrepo->company_filter_key($scopefilters),
                'label' => get_string('filter:companies', 'block_dashboardanalytics'),
                'multiple' => true,
                'options' => $companyrepo->get_company_options($scopefilters),
                'searchable' => false,
            ],
        ];

        $users = $this->users($scopefilters);
        if ($users) {
            $groups[] = [
                'key' => 'userids',
                'label' => get_string('filter:employees', 'block_dashboardanalytics'),
                'multiple' => true,
                'options' => $users,
                'searchable' => true,
            ];
        }

        $groups[] = [
            'key' => 'departments',
            'label' => get_string('filter:departments', 'block_dashboardanalytics'),
            'multiple' => true,
            'options' => $this->profile_field_options($scopefilters, ['Department', 'department'], 'u.department'),
            'searchable' => false,
        ];

        $groups[] = [
            'key' => 'locations',
            'label' => get_string('filter:locations', 'block_dashboardanalytics'),
            'multiple' => true,
            'options' => $this->profile_field_options($scopefilters, ['Region'], 'u.city'),
            'searchable' => false,
        ];

        $positions = $this->profile_field_options(
            $scopefilters,
            array_values(array_filter(['Job_Title', trim((string)get_config('block_dashboardanalytics', 'positionfield'))])),
            ''
        );
        if ($positions) {
            $groups[] = [
                'key' => 'positions',
                'label' => get_string('filter:positions', 'block_dashboardanalytics'),
                'multiple' => true,
                'options' => $positions,
                'searchable' => false,
            ];
        }

        $groups[] = [
            'key' => 'personnelcategories',
            'label' => get_string('filter:personnelcategories', 'block_dashboardanalytics'),
            'multiple' => true,
            'options' => $this->profile_field_options($scopefilters, ['PersonnelCategory'], ''),
            'searchable' => false,
        ];

        $groups[] = [
            'key' => 'sites',
            'label' => get_string('filter:sites', 'block_dashboardanalytics'),
            'multiple' => true,
            'options' => $this->profile_field_options($scopefilters, ['Site'], ''),
            'searchable' => false,
        ];

        $groups[] = [
            'key' => 'educations',
            'label' => get_string('filter:educations', 'block_dashboardanalytics'),
            'multiple' => true,
            'options' => $this->profile_field_options($scopefilters, ['edu'], ''),
            'searchable' => false,
        ];

        $courses = $this->courses($scopefilters);
        if ($courses) {
            $groups[] = [
                'key' => 'courseids',
                'label' => get_string('filter:courses', 'block_dashboardanalytics'),
                'multiple' => true,
                'options' => $courses,
                'searchable' => true,
            ];
        }

        $groups[] = [
            'key' => 'daterange',
            'label' => get_string('filter:daterange', 'block_dashboardanalytics'),
            'multiple' => false,
            'searchable' => false,
            'options' => [
                ['value' => 'day', 'label' => get_string('filter:day', 'block_dashboardanalytics')],
                ['value' => 'week', 'label' => get_string('filter:week', 'block_dashboardanalytics')],
                ['value' => 'month', 'label' => get_string('filter:month', 'block_dashboardanalytics')],
                ['value' => '6months', 'label' => get_string('filter:6months', 'block_dashboardanalytics')],
                ['value' => 'year', 'label' => get_string('filter:year', 'block_dashboardanalytics')],
                ['value' => 'alltime', 'label' => get_string('filter:alltime', 'block_dashboardanalytics')],
                ['value' => 'customrange', 'label' => get_string('filter:customrange', 'block_dashboardanalytics')],
            ],
        ];

        return array_values(array_filter($groups, static function(array $group): bool {
            return !empty($group['options']);
        }));
    }

    private function users(array $scopefilters): array {
        global $DB;

        $employee = new employee_repository();
        $filter = $employee->user_filter_sql($scopefilters, 'u', 'dimfltuser');

        $sql = "SELECT u.id,
                       u.firstname,
                       u.lastname,
                       u.email
                  FROM {user} u
                 WHERE {$filter['sql']}
              ORDER BY u.lastname ASC, u.firstname ASC, u.email ASC";

        $records = $DB->get_records_sql($sql, $filter['params'], 0, 1000);
        $options = [];
        foreach ($records as $record) {
            $name = trim((string)$record->firstname . ' ' . (string)$record->lastname);
            $label = $name !== '' ? $name : (string)$record->email;
            if ((string)$record->email !== '' && stripos($label, (string)$record->email) === false) {
                $label .= ' (' . (string)$record->email . ')';
            }

            $options[] = [
                'value' => (string)(int)$record->id,
                'label' => $label,
            ];
        }

        return $options;
    }

    private function profile_field_options(array $scopefilters, array $shortnames, string $fallbackexpr): array {
        global $DB;

        $employee = new employee_repository();
        $seed = implode('_', $shortnames);
        if ($seed === '') {
            $seed = $fallbackexpr !== '' ? $fallbackexpr : 'generic';
        }
        $prefix = 'dimflt' . substr(sha1($seed), 0, 8);
        $filter = $employee->user_filter_sql($scopefilters, 'u', $prefix);

        $profilefield = $this->existing_profile_field($shortnames);
        if ($profilefield !== '') {
            $sql = "SELECT DISTINCT uid.data
                      FROM {user_info_data} uid
                      JOIN {user_info_field} uif ON uif.id = uid.fieldid
                      JOIN {user} u ON u.id = uid.userid
                     WHERE uif.shortname = :shortname
                       AND uid.data <> ''
                       AND {$filter['sql']}
                  ORDER BY uid.data ASC";

            return $this->text_options($DB->get_fieldset_sql($sql, ['shortname' => $profilefield] + $filter['params']));
        }

        if ($fallbackexpr === '') {
            return [];
        }

        $sql = "SELECT DISTINCT {$fallbackexpr} AS value
                  FROM {user} u
                 WHERE {$filter['sql']}
                   AND {$fallbackexpr} <> ''
              ORDER BY value ASC";

        return $this->text_options($DB->get_fieldset_sql($sql, $filter['params']));
    }

    private function courses(array $scopefilters): array {
        global $DB;

        $analytics = new course_analytics_repository();
        $join = $analytics->eligibility_join_sql('c', 'cfcoursefilter', 'cdcoursefilter');
        $sql = "SELECT c.id, c.fullname
                  FROM {course} c
                  {$join}
                 WHERE c.id > 1
                   AND " . $analytics->eligibility_where_sql('c', 'cfcoursefilter', 'cdcoursefilter') . "
              ORDER BY c.fullname ASC";

        $records = $DB->get_records_sql($sql);
        $options = [];
        foreach ($records as $record) {
            $options[] = [
                'value' => (string)(int)$record->id,
                'label' => format_string((string)$record->fullname),
            ];
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

    private function existing_profile_field(array $shortnames): string {
        foreach ($shortnames as $shortname) {
            if ($this->profile_field_exists($shortname)) {
                return $shortname;
            }
        }
        return '';
    }

    private function profile_field_exists(string $shortname): bool {
        global $DB;

        return $DB->record_exists('user_info_field', ['shortname' => $shortname]);
    }
}
