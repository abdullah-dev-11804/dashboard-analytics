<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\repository;

defined('MOODLE_INTERNAL') || die();

class dimension_repository {

    public function get_filter_groups(): array {
        return [
            [
                'key' => 'companyids',
                'label' => get_string('filter:companies', 'block_dashboardanalytics'),
                'multiple' => true,
                'options' => $this->companies(),
            ],
            [
                'key' => 'departments',
                'label' => get_string('filter:departments', 'block_dashboardanalytics'),
                'multiple' => true,
                'options' => $this->departments(),
            ],
            [
                'key' => 'locations',
                'label' => get_string('filter:locations', 'block_dashboardanalytics'),
                'multiple' => true,
                'options' => $this->locations(),
            ],
            [
                'key' => 'positions',
                'label' => get_string('filter:positions', 'block_dashboardanalytics'),
                'multiple' => true,
                'options' => $this->positions(),
            ],
            [
                'key' => 'courseids',
                'label' => get_string('filter:courses', 'block_dashboardanalytics'),
                'multiple' => true,
                'options' => $this->courses(),
            ],
        ];
    }

    private function companies(): array {
        global $DB;

        $records = $DB->get_records('cohort', null, 'name ASC', 'id, name', 0, 300);
        $options = [];
        foreach ($records as $record) {
            $options[] = ['value' => (string)$record->id, 'label' => format_string($record->name)];
        }
        return $options;
    }

    private function departments(): array {
        global $DB;

        $sql = "SELECT DISTINCT department
                  FROM {user}
                 WHERE deleted = 0
                   AND department <> ''
              ORDER BY department ASC";

        return $this->text_options($DB->get_fieldset_sql($sql));
    }

    private function locations(): array {
        global $DB;

        $sql = "SELECT DISTINCT city
                  FROM {user}
                 WHERE deleted = 0
                   AND city <> ''
              ORDER BY city ASC";

        return $this->text_options($DB->get_fieldset_sql($sql));
    }

    private function positions(): array {
        global $DB;

        $positionfield = trim((string)get_config('block_dashboardanalytics', 'positionfield'));
        if ($positionfield === '') {
            return [];
        }

        $sql = "SELECT DISTINCT uid.data
                  FROM {user_info_data} uid
                  JOIN {user_info_field} uif ON uif.id = uid.fieldid
                 WHERE uif.shortname = :shortname
                   AND uid.data <> ''
              ORDER BY uid.data ASC";

        return $this->text_options($DB->get_fieldset_sql($sql, ['shortname' => $positionfield]));
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

