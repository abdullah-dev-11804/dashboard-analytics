<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\repository;

use block_dashboardanalytics\name_formatter;
use block_dashboardanalytics\permissions;

defined('MOODLE_INTERNAL') || die();

class dimension_repository {

    public function get_filter_groups(array $scopefilters = []): array {
        global $USER;

        $companyrepo = new company_repository();
        $iscompanyowner = permissions::is_company_owner(\context_system::instance());
        $companyownerscope = $iscompanyowner ? $companyrepo->scope_details_for_user((int)$USER->id) : [];

        $groups = [];

        if (!$iscompanyowner || !empty($companyownerscope['selectorvisible'])) {
            $companykey = $companyrepo->company_filter_key($scopefilters);
            $companyoptions = $companyrepo->get_company_options($this->filters_without_keys($scopefilters, [$companykey, 'companies']));
            if ($iscompanyowner && !empty($companyownerscope['companyids'])) {
                $companyoptions = array_values(array_filter($companyoptions, static function(array $option) use ($companyownerscope): bool {
                    return in_array((int)($option['value'] ?? 0), array_map('intval', $companyownerscope['companyids']), true);
                }));
            }

            if (count($companyoptions) > 1) {
                $groups[] = [
                    'key' => $companykey,
                    'label' => get_string('filter:companies', 'block_dashboardanalytics'),
                    'multiple' => !$iscompanyowner,
                    'allowblank' => !$iscompanyowner,
                    'options' => $companyoptions,
                    'searchable' => false,
                ];
            }
        }

        $groups[] = [
            'key' => 'locations',
            'label' => get_string('filter:locations', 'block_dashboardanalytics'),
            'multiple' => true,
            'allowblank' => true,
            'options' => $this->profile_field_options(
                $this->filters_without_keys($scopefilters, ['locations']),
                ['Region'],
                'u.city'
            ),
            'searchable' => false,
        ];

        $groups[] = [
            'key' => 'sites',
            'label' => get_string('filter:sites', 'block_dashboardanalytics'),
            'multiple' => true,
            'allowblank' => true,
            'options' => $this->profile_field_options(
                $this->filters_without_keys($scopefilters, ['sites']),
                ['Site'],
                ''
            ),
            'searchable' => false,
        ];

        $groups[] = [
            'key' => 'departments',
            'label' => get_string('filter:departments', 'block_dashboardanalytics'),
            'multiple' => true,
            'allowblank' => true,
            'options' => $this->profile_field_options(
                $this->filters_without_keys($scopefilters, ['departments']),
                ['Department', 'department'],
                'u.department'
            ),
            'searchable' => false,
        ];

        $groups[] = [
            'key' => 'personnelcategories',
            'label' => get_string('filter:personnelcategories', 'block_dashboardanalytics'),
            'multiple' => true,
            'allowblank' => true,
            'options' => $this->profile_field_options(
                $this->filters_without_keys($scopefilters, ['personnelcategories']),
                ['PersonnelCategory'],
                ''
            ),
            'searchable' => false,
        ];

        $positions = $this->profile_field_options(
            $this->filters_without_keys($scopefilters, ['positions']),
            array_values(array_filter(['Job_Title', trim((string)get_config('block_dashboardanalytics', 'positionfield'))])),
            ''
        );
        if ($positions) {
            $groups[] = [
                'key' => 'positions',
                'label' => get_string('filter:positions', 'block_dashboardanalytics'),
                'multiple' => true,
                'allowblank' => true,
                'options' => $positions,
                'searchable' => false,
            ];
        }

        $groups[] = [
            'key' => 'daterange',
            'label' => get_string('filter:daterange', 'block_dashboardanalytics'),
            'multiple' => false,
            'allowblank' => false,
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

        $groups[] = [
            'key' => 'educations',
            'label' => get_string('filter:educations', 'block_dashboardanalytics'),
            'multiple' => true,
            'allowblank' => true,
            'options' => $this->profile_field_options(
                $this->filters_without_keys($scopefilters, ['educations']),
                ['edu'],
                ''
            ),
            'searchable' => false,
        ];

        $courses = $this->courses($this->filters_without_keys($scopefilters, ['courseids']));
        if ($courses) {
            $groups[] = [
                'key' => 'courseids',
                'label' => get_string('filter:courses', 'block_dashboardanalytics'),
                'multiple' => true,
                'allowblank' => true,
                'options' => $courses,
                'searchable' => true,
            ];
        }

        return array_values(array_filter($groups, static function(array $group): bool {
            return !empty($group['options']);
        }));
    }

    private function filters_without_keys(array $filters, array $keys): array {
        foreach ($keys as $key) {
            unset($filters[$key]);
        }
        return $filters;
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
            $name = name_formatter::last_first($record, (string)$record->email);
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
        $employee = new employee_repository();
        $filter = $employee->user_filter_sql($scopefilters, 'u', 'dimfltcourse');
        $join = $analytics->eligibility_join_sql('c', 'cfcoursefilter', 'cdcoursefilter');
        $sql = "SELECT DISTINCT c.id, c.fullname
                  FROM {user} u
                  JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
                  JOIN {course} c ON c.id = e.courseid
                  {$join}
                 WHERE {$filter['sql']}
                   AND c.id > 1
                   AND " . $analytics->eligibility_where_sql('c', 'cfcoursefilter', 'cdcoursefilter') . "
              ORDER BY c.fullname ASC";

        $records = $DB->get_records_sql($sql, $filter['params']);
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
