<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\repository;

defined('MOODLE_INTERNAL') || die();

class course_analytics_repository {
    public const FIELD_SHORTNAME = 'include_analytics';

    /**
     * Build a safe customfield_data payload for a checkbox-style course field.
     *
     * Different Moodle installs can have slightly different nullable/default
     * expectations on customfield_data columns, so we populate the common ones
     * explicitly and only set optional fields when they exist in this schema.
     *
     * @param int $fieldid
     * @param int $courseid
     * @param bool $enabled
     * @param int $now
     * @param object|null $existing
     * @return \stdClass
     */
    protected function build_customfield_record(
        int $fieldid,
        int $courseid,
        bool $enabled,
        int $now,
        ?\stdClass $existing = null
    ): \stdClass {
        global $DB;

        $record = (object)[
            'fieldid' => $fieldid,
            'instanceid' => $courseid,
            'intvalue' => $enabled ? 1 : 0,
            'value' => $enabled ? '1' : '0',
            'valueformat' => 0,
            'timemodified' => $now,
        ];

        if ($existing && property_exists($existing, 'id')) {
            $record->id = (int)$existing->id;
        }

        if (!$existing) {
            $record->timecreated = $now;
        }

        $table = new \xmldb_table('customfield_data');
        $optionalvalues = [
            'decvalue' => $enabled ? 1 : 0,
            'charvalue' => $enabled ? '1' : '0',
            'shortcharvalue' => $enabled ? '1' : '0',
        ];

        foreach ($optionalvalues as $fieldname => $value) {
            if ($DB->get_manager()->field_exists($table, new \xmldb_field($fieldname))) {
                $record->{$fieldname} = $value;
            }
        }

        return $record;
    }

    public function eligibility_join_sql(
        string $coursealias = 'c',
        string $fieldalias = 'cfanalytics',
        string $dataalias = 'cdanalytics'
    ): string {
        return "LEFT JOIN {customfield_field} {$fieldalias}
                       ON {$fieldalias}.shortname = '" . self::FIELD_SHORTNAME . "'
                LEFT JOIN {customfield_data} {$dataalias}
                       ON {$dataalias}.fieldid = {$fieldalias}.id
                      AND {$dataalias}.instanceid = {$coursealias}.id";
    }

    public function eligibility_where_sql(
        string $coursealias = 'c',
        string $fieldalias = 'cfanalytics',
        string $dataalias = 'cdanalytics'
    ): string {
        return "{$coursealias}.visible = 1 AND " . $this->enabled_expression($fieldalias, $dataalias) . " = 1";
    }

    public function enabled_expression(string $fieldalias = 'cfanalytics', string $dataalias = 'cdanalytics'): string {
        return "CASE
                    WHEN {$fieldalias}.id IS NULL THEN 1
                    WHEN COALESCE({$dataalias}.intvalue, {$dataalias}.decvalue) IS NOT NULL
                        THEN CASE WHEN COALESCE({$dataalias}.intvalue, {$dataalias}.decvalue) > 0 THEN 1 ELSE 0 END
                    WHEN TRIM(COALESCE({$dataalias}.value, '')) IN ('0', 'false', 'False', 'no', 'No')
                        THEN 0
                    ELSE 1
                END";
    }

    public function list_courses(string $search = '', int $page = 0, int $perpage = 20): array {
        global $DB;

        $page = max(0, $page);
        $perpage = max(10, min(100, $perpage));
        $params = ['siteid' => SITEID];
        $where = ['c.id <> :siteid'];

        if ($search !== '') {
            $searchlike = '%' . $DB->sql_like_escape($search) . '%';
            $where[] = '(' . $DB->sql_like('c.fullname', ':coursesearch', false) . ' OR ' .
                $DB->sql_like('c.shortname', ':coursesearchshort', false) . ')';
            $params['coursesearch'] = $searchlike;
            $params['coursesearchshort'] = $searchlike;
        }

        $join = $this->eligibility_join_sql('c', 'cfmanage', 'cdmanage');
        $enabledexpr = $this->enabled_expression('cfmanage', 'cdmanage');

        $sql = "SELECT c.id,
                       c.fullname,
                       c.shortname,
                       c.visible,
                       {$enabledexpr} AS analyticsenabled
                  FROM {course} c
                  {$join}
                 WHERE " . implode(' AND ', $where) . "
              ORDER BY c.fullname ASC, c.id ASC";

        $totalcount = (int)$DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {course} c
               {$join}
              WHERE " . implode(' AND ', $where),
            $params
        );

        $records = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
        $rows = [];
        foreach ($records as $record) {
            $rows[] = [
                'courseid' => (int)$record->id,
                'fullname' => format_string((string)$record->fullname),
                'shortname' => (string)$record->shortname,
                'visible' => (int)$record->visible === 1,
                'analyticsenabled' => (int)$record->analyticsenabled === 1,
            ];
        }

        return [
            'rows' => $rows,
            'totalcount' => $totalcount,
            'page' => $page,
            'perpage' => $perpage,
        ];
    }

    public function set_course_enabled(int $courseid, bool $enabled): void {
        global $DB;

        $course = $DB->get_record('course', ['id' => $courseid], 'id', IGNORE_MISSING);
        if (!$course || (int)$course->id === SITEID) {
            throw new \moodle_exception('error:invalidcourse', 'block_dashboardanalytics');
        }

        $field = $DB->get_record('customfield_field', ['shortname' => self::FIELD_SHORTNAME], 'id', IGNORE_MISSING);
        if (!$field) {
            throw new \moodle_exception('error:analyticsfieldmissing', 'block_dashboardanalytics');
        }

        $now = time();
        $data = $DB->get_record('customfield_data', ['fieldid' => (int)$field->id, 'instanceid' => $courseid], '*', IGNORE_MISSING);
        $record = $this->build_customfield_record((int)$field->id, $courseid, $enabled, $now, $data ?: null);

        if ($data) {
            $DB->update_record('customfield_data', $record);
            return;
        }

        $DB->insert_record('customfield_data', $record);
    }
}
