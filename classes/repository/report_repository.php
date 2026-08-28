<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\repository;

use block_dashboardanalytics\name_formatter;

defined('MOODLE_INTERNAL') || die();

class report_repository {
    private const TEMPLATE_TABLE = 'block_da_reptemplate';

    public function available_columns(): array {
        $columns = [
            'lastname' => 'reportsbuilder:column:lastname',
            'firstname' => 'reportsbuilder:column:firstname',
            'middlename' => 'reportsbuilder:column:middlename',
            'fullname' => 'reportsbuilder:column:fullname',
            'course' => 'reportsbuilder:column:course',
            'status' => 'reportsbuilder:column:status',
            'completiondate' => 'reportsbuilder:column:completiondate',
            'completiontime' => 'reportsbuilder:column:completiontime',
            'email' => 'reportsbuilder:column:email',
            'company' => 'reportsbuilder:column:company',
            'region' => 'reportsbuilder:column:region',
            'site' => 'reportsbuilder:column:site',
            'department' => 'reportsbuilder:column:department',
            'personnelcategory' => 'reportsbuilder:column:personnelcategory',
            'jobtitle' => 'reportsbuilder:column:jobtitle',
            'protocolnumber' => 'reportsbuilder:column:protocolnumber',
            'certificatenumber' => 'reportsbuilder:column:certificatenumber',
            'bookid' => 'reportsbuilder:column:bookid',
            'education' => 'reportsbuilder:column:education',
            'phone' => 'reportsbuilder:column:phone',
            'trainingstart' => 'reportsbuilder:column:trainingstart',
            'trainingtype' => 'reportsbuilder:column:trainingtype',
        ];

        $result = [];
        foreach ($columns as $key => $stringkey) {
            $result[] = [
                'key' => $key,
                'label' => get_string($stringkey, 'block_dashboardanalytics'),
            ];
        }

        return $result;
    }

    public function default_column_keys(): array {
        return [
            'lastname',
            'firstname',
            'middlename',
            'course',
            'status',
            'completiondate',
            'email',
            'company',
            'region',
            'site',
            'department',
            'personnelcategory',
            'jobtitle',
            'trainingtype',
        ];
    }

    public function templates_for_user(int $userid): array {
        global $DB;

        if (!$this->table_exists(self::TEMPLATE_TABLE)) {
            return [];
        }

        $records = $DB->get_records(self::TEMPLATE_TABLE, ['userid' => $userid], 'name ASC, id ASC');
        $templates = [];
        foreach ($records as $record) {
            $templates[] = $this->template_to_array($record);
        }

        return $templates;
    }

    public function save_template(int $userid, int $templateid, string $name, array $columns, array $filters): array {
        global $DB;

        if (!$this->table_exists(self::TEMPLATE_TABLE)) {
            throw new \moodle_exception('error:missingreporttemplatetable', 'block_dashboardanalytics');
        }

        $name = trim($name);
        if ($name === '') {
            $name = get_string('reportsbuilder:untitledtemplate', 'block_dashboardanalytics');
        }

        $columns = $this->normalize_columns($columns);
        $now = time();

        if ($templateid > 0) {
            $existing = $DB->get_record(self::TEMPLATE_TABLE, ['id' => $templateid, 'userid' => $userid], '*', IGNORE_MISSING);
            if (!$existing) {
                throw new \moodle_exception('error:noaccess', 'block_dashboardanalytics');
            }

            $existing->name = $name;
            $existing->columnsjson = json_encode(array_values($columns));
            $existing->filtersjson = json_encode($filters);
            $existing->timemodified = $now;
            $DB->update_record(self::TEMPLATE_TABLE, $existing);

            return $this->template_to_array($existing);
        }

        $record = (object)[
            'userid' => $userid,
            'name' => $name,
            'columnsjson' => json_encode(array_values($columns)),
            'filtersjson' => json_encode($filters),
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = $DB->insert_record(self::TEMPLATE_TABLE, $record);

        return $this->template_to_array($record);
    }

    public function delete_template(int $userid, int $templateid): void {
        global $DB;

        if ($templateid <= 0 || !$this->table_exists(self::TEMPLATE_TABLE)) {
            return;
        }

        $DB->delete_records(self::TEMPLATE_TABLE, ['id' => $templateid, 'userid' => $userid]);
    }

    public function report_rows(
        array $filters,
        array $options,
        int $page = 0,
        int $perpage = 20,
        string $sortkey = 'completiondate',
        string $sortdir = 'asc'
    ): array {
        $page = max(0, $page);
        $perpage = max(10, min(100, $perpage));
        $columns = $this->normalize_columns($options['columns'] ?? []);

        $rawrows = $this->source_report_rows($filters, $options);
        $summary = $this->summary_from_rows($rawrows);
        $search = trim((string)($options['search'] ?? ''));
        if ($search !== '') {
            $rawrows = array_values(array_filter($rawrows, function(array $row) use ($search): bool {
                return $this->row_matches_search($row, $search);
            }));
        }

        $this->sort_rows($rawrows, $sortkey, $sortdir);
        $total = count($rawrows);
        $paged = array_slice($rawrows, $page * $perpage, $perpage);

        return [
            'columns' => $this->column_definitions($columns),
            'rows' => array_map(function(array $row) use ($columns): array {
                return $this->row_to_external($row, $columns);
            }, $paged),
            'totalcount' => $total,
            'page' => $page,
            'perpage' => $perpage,
            'summary' => $summary,
        ];
    }

    public function export_rows(array $filters, array $options): array {
        $columns = $this->normalize_columns($options['columns'] ?? []);
        $rawrows = $this->source_report_rows($filters, $options);
        $search = trim((string)($options['search'] ?? ''));
        if ($search !== '') {
            $rawrows = array_values(array_filter($rawrows, function(array $row) use ($search): bool {
                return $this->row_matches_search($row, $search);
            }));
        }

        $this->sort_rows(
            $rawrows,
            (string)($options['sortkey'] ?? 'completiondate'),
            (string)($options['sortdir'] ?? 'asc')
        );
        $summary = $this->summary_from_rows($rawrows);

        return [
            'columns' => $this->column_definitions($columns),
            'rows' => array_map(function(array $row) use ($columns): array {
                return $this->row_to_export($row, $columns);
            }, $rawrows),
            'rawrows' => $rawrows,
            'summary' => $summary,
        ];
    }

    private function source_report_rows(array $filters, array $options): array {
        $filters['statusmode'] = 'course';
        unset($filters['status']);

        $overview = new overview_repository();
        $rows = $overview->enrolment_status_snapshot_rows($filters);
        $profiledata = $this->profile_values(array_column($rows, 'userid'));
        $period = $this->period_filter($options);
        $result = [];

        foreach ($rows as $row) {
            if (empty($row['sourcekind']) || empty($row['documentid'])) {
                continue;
            }

            $completiontime = (int)($row['issuedate'] ?? 0);
            if ($completiontime <= 0 || !$this->matches_period($completiontime, $period)) {
                continue;
            }

            $userid = (int)$row['userid'];
            $extra = $profiledata[$userid] ?? [];
            $fullname = name_formatter::last_first_from_parts(
                (string)($row['firstname'] ?? ''),
                (string)($row['lastname'] ?? ''),
                (string)($row['email'] ?? '')
            );

            $result[] = [
                'userid' => $userid,
                'courseid' => (int)($row['courseid'] ?? 0),
                'lastname' => (string)($row['lastname'] ?? ''),
                'firstname' => (string)($row['firstname'] ?? ''),
                'middlename' => (string)($extra['middlename'] ?? ''),
                'fullname' => $fullname,
                'course' => html_entity_decode(format_string((string)($row['course'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'courseshortname' => (string)($row['courseshortname'] ?? ''),
                'status' => $this->status_label((string)($row['status'] ?? '')),
                'statuskey' => $this->status_key((string)($row['status'] ?? '')),
                'completiondate' => userdate($completiontime, get_string('strftimedate', 'langconfig')),
                'completiontime' => userdate($completiontime, get_string('strftimetime', 'langconfig')),
                'completiontimestamp' => $completiontime,
                'email' => (string)($row['email'] ?? ''),
                'company' => (string)($row['company'] ?? ''),
                'region' => (string)($row['location'] ?? ''),
                'site' => (string)($row['site'] ?? ''),
                'department' => (string)($row['department'] ?? ''),
                'personnelcategory' => (string)($row['personnelcategory'] ?? ''),
                'jobtitle' => (string)($row['position'] ?? ''),
                'protocolnumber' => '',
                'certificatenumber' => '',
                'bookid' => '',
                'education' => (string)($extra['edu'] ?? ''),
                'phone' => (string)($extra['Phone'] ?? ''),
                'trainingstart' => $this->training_start_label($userid, (int)($row['courseid'] ?? 0)),
                'trainingtype' => $this->training_type_label((string)($row['sourcekind'] ?? '')),
                'sourcekind' => (string)($row['sourcekind'] ?? ''),
                'sourceid' => (int)($row['sourceid'] ?? $row['documentid'] ?? 0),
                'documentid' => (int)($row['documentid'] ?? 0),
                'profileurl' => (new \moodle_url('/user/profile.php', ['id' => $userid]))->out(false),
                'courseurl' => (new \moodle_url('/local/sentaldocupload/course_record.php', [
                    'courseid' => (int)($row['courseid'] ?? 0),
                    'userid' => $userid,
                ]))->out(false),
            ];
        }

        return $result;
    }

    private function column_definitions(array $columns): array {
        $available = [];
        foreach ($this->available_columns() as $column) {
            $available[$column['key']] = $column['label'];
        }

        $definitions = [];
        foreach ($columns as $key) {
            if (isset($available[$key])) {
                $definitions[] = ['key' => $key, 'label' => $available[$key]];
            }
        }

        return $definitions;
    }

    private function row_to_external(array $row, array $columns): array {
        $cells = [];
        foreach ($columns as $key) {
            $cell = [
                'key' => $key,
                'value' => (string)($row[$key] ?? ''),
            ];
            if (in_array($key, ['lastname', 'firstname', 'fullname'], true)) {
                $cell['url'] = (string)$row['profileurl'];
            } else if ($key === 'course') {
                $cell['url'] = (string)$row['courseurl'];
            }
            $cells[] = $cell;
        }

        return [
            'userid' => (int)$row['userid'],
            'courseid' => (int)$row['courseid'],
            'statuskey' => (string)$row['statuskey'],
            'cells' => $cells,
        ];
    }

    private function row_to_export(array $row, array $columns): array {
        $values = [];
        foreach ($columns as $key) {
            $values[$key] = (string)($row[$key] ?? '');
        }
        return $values;
    }

    private function normalize_columns(array $columns): array {
        $available = array_column($this->available_columns(), 'key');
        $columns = array_values(array_unique(array_filter(array_map('strval', $columns))));
        $columns = array_values(array_intersect($columns, $available));

        return $columns ?: $this->default_column_keys();
    }

    private function period_filter(array $options): array {
        $mode = (string)($options['periodmode'] ?? 'month');
        if ($mode === 'custom') {
            $start = $this->parse_date((string)($options['customstart'] ?? ''), false);
            $end = $this->parse_date((string)($options['customend'] ?? ''), true);
            return [
                'mode' => 'custom',
                'start' => $start,
                'end' => $end,
            ];
        }

        $months = array_values(array_filter(array_map('intval', $options['months'] ?? []), static function(int $month): bool {
            return $month >= 1 && $month <= 12;
        }));
        $years = array_values(array_filter(array_map('intval', $options['years'] ?? []), static function(int $year): bool {
            return $year >= 2000 && $year <= 2100;
        }));

        if (!$months) {
            $months = [(int)date('n')];
        }
        if (!$years) {
            $years = [(int)date('Y')];
        }

        return [
            'mode' => 'month',
            'months' => array_unique($months),
            'years' => array_unique($years),
        ];
    }

    private function parse_date(string $date, bool $endofday): int {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return 0;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $date));
        return make_timestamp($year, $month, $day, $endofday ? 23 : 0, $endofday ? 59 : 0, $endofday ? 59 : 0);
    }

    private function matches_period(int $timestamp, array $period): bool {
        if (($period['mode'] ?? '') === 'custom') {
            $start = (int)($period['start'] ?? 0);
            $end = (int)($period['end'] ?? 0);
            if ($start > 0 && $timestamp < $start) {
                return false;
            }
            if ($end > 0 && $timestamp > $end) {
                return false;
            }
            return true;
        }

        return in_array((int)date('n', $timestamp), $period['months'] ?? [], true)
            && in_array((int)date('Y', $timestamp), $period['years'] ?? [], true);
    }

    private function row_matches_search(array $row, string $search): bool {
        $haystack = implode(' ', [
            $row['lastname'] ?? '',
            $row['firstname'] ?? '',
            $row['middlename'] ?? '',
            $row['fullname'] ?? '',
            $row['email'] ?? '',
            $row['course'] ?? '',
            $row['courseshortname'] ?? '',
            $row['company'] ?? '',
            $row['department'] ?? '',
            $row['region'] ?? '',
            $row['site'] ?? '',
            $row['jobtitle'] ?? '',
        ]);

        return mb_stripos($haystack, $search) !== false;
    }

    private function sort_rows(array &$rows, string $sortkey, string $sortdir): void {
        $sortdir = strtolower($sortdir) === 'desc' ? 'desc' : 'asc';
        $allowed = array_column($this->available_columns(), 'key');
        if (!in_array($sortkey, $allowed, true)) {
            $sortkey = 'completiondate';
        }

        usort($rows, function(array $a, array $b) use ($sortkey, $sortdir): int {
            if ($sortkey === 'completiondate' || $sortkey === 'completiontime' || $sortkey === 'trainingstart') {
                $cmp = ((int)($a['completiontimestamp'] ?? 0)) <=> ((int)($b['completiontimestamp'] ?? 0));
            } else if ($sortkey === 'status') {
                $cmp = $this->status_sort_rank((string)($a['statuskey'] ?? '')) <=> $this->status_sort_rank((string)($b['statuskey'] ?? ''));
            } else {
                $cmp = strcasecmp((string)($a[$sortkey] ?? ''), (string)($b[$sortkey] ?? ''));
            }

            return $sortdir === 'desc' ? -$cmp : $cmp;
        });
    }

    private function status_sort_rank(string $statuskey): int {
        $map = ['active' => 1, 'expiring' => 2, 'expired' => 3];
        return $map[$statuskey] ?? 99;
    }

    private function status_key(string $status): string {
        $status = strtolower(trim($status));
        if ($status === 'active') {
            return 'active';
        }
        if ($status === 'expiring') {
            return 'expiring';
        }
        if ($status === 'expired') {
            return 'expired';
        }
        return 'inprogress';
    }

    private function status_label(string $status): string {
        $key = $this->status_key($status);
        $map = [
            'active' => 'label:active',
            'expiring' => 'label:expiring',
            'expired' => 'label:expired',
            'inprogress' => 'label:nodocument',
        ];
        return get_string($map[$key], 'block_dashboardanalytics');
    }

    private function training_type_label(string $sourcekind): string {
        return $sourcekind === 'legacy_type1'
            ? get_string('reportsbuilder:trainingtype:offline', 'block_dashboardanalytics')
            : get_string('reportsbuilder:trainingtype:online', 'block_dashboardanalytics');
    }

    private function training_start_label(int $userid, int $courseid): string {
        global $DB;

        if ($userid <= 0 || $courseid <= 0) {
            return '';
        }

        $sql = "SELECT MIN(ue.timestart) AS timestart
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid = :userid
                   AND e.courseid = :courseid
                   AND ue.timestart > 0";
        $timestamp = (int)$DB->get_field_sql($sql, ['userid' => $userid, 'courseid' => $courseid]);
        return $timestamp > 0 ? userdate($timestamp, get_string('strftimedate', 'langconfig')) : '';
    }

    private function summary_from_rows(array $rows): array {
        $summary = [
            'total' => count($rows),
            'online' => 0,
            'offline' => 0,
            'active' => 0,
            'expiring' => 0,
            'expired' => 0,
        ];

        foreach ($rows as $row) {
            if (($row['sourcekind'] ?? '') === 'legacy_type1') {
                $summary['offline']++;
            } else {
                $summary['online']++;
            }
            $statuskey = (string)($row['statuskey'] ?? '');
            if (isset($summary[$statuskey])) {
                $summary[$statuskey]++;
            }
        }

        return $summary;
    }

    private function profile_values(array $userids): array {
        global $DB;

        $userids = array_values(array_unique(array_filter(array_map('intval', $userids))));
        if (!$userids) {
            return [];
        }

        [$userinsql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'reportuser');
        [$fieldinsql, $fieldparams] = $DB->get_in_or_equal(
            ['middlename', 'edu', 'Phone'],
            SQL_PARAMS_NAMED,
            'reportfield'
        );

        $sql = "SELECT uid.id,
                       uid.userid,
                       uif.shortname,
                       uid.data
                  FROM {user_info_data} uid
                  JOIN {user_info_field} uif ON uif.id = uid.fieldid
                 WHERE uid.userid {$userinsql}
                   AND uif.shortname {$fieldinsql}";

        $records = $DB->get_records_sql($sql, $userparams + $fieldparams);
        $values = [];
        foreach ($records as $record) {
            $userid = (int)$record->userid;
            if (!isset($values[$userid])) {
                $values[$userid] = [];
            }
            $values[$userid][(string)$record->shortname] = (string)$record->data;
        }

        return $values;
    }

    private function template_to_array(\stdClass $record): array {
        return [
            'id' => (int)$record->id,
            'name' => format_string((string)$record->name),
            'columns' => json_decode((string)($record->columnsjson ?? '[]'), true) ?: [],
            'filters' => json_decode((string)($record->filtersjson ?? '{}'), true) ?: [],
            'timemodified' => (int)($record->timemodified ?? 0),
        ];
    }

    public function company_options(): array {
        global $DB;

        if (!$this->table_exists('company')) {
            return [];
        }

        $records = $DB->get_records('company', null, 'name ASC', 'id, name', 0, 1000);
        $options = [];

        foreach ($records as $record) {
            $options[] = [
                'value' => (string)$record->id,
                'label' => format_string($record->name),
            ];
        }

        return $options;
    }

    public function company_name(int $companyid): string {
        global $DB;

        if ($companyid <= 0 || !$this->table_exists('company')) {
            return '';
        }

        return (string)$DB->get_field('company', 'name', ['id' => $companyid], IGNORE_MISSING);
    }

    public function act_service_rows(int $companyid, int $month, int $year): array {
        global $DB;

        if ($companyid <= 0 || $month < 1 || $month > 12 || $year < 2000) {
            return [];
        }

        if (!$this->table_exists('company_users')) {
            return [];
        }

        $start = make_timestamp($year, $month, 1, 0, 0, 0);
        $end = strtotime('+1 month', $start) - 1;
        $employee = new employee_repository();
        $where = [
            'c.visible = 1',
            'c.id <> :siteid',
        ];
        $params = [
            'companyid' => $companyid,
            'starttime' => $start,
            'endtime' => $end,
            'siteid' => SITEID,
        ];
        $employee->append_sental_student_only_filter($where, $params, 'u', 'reportsact');

        $sql = "SELECT c.id AS courseid,
                    c.fullname AS coursename,
                    COUNT(DISTINCT cc.userid) AS lmscount
                FROM {course} c
                JOIN {enrol} e
                    ON e.courseid = c.id
                AND e.status = 0
                JOIN {user_enrolments} ue
                    ON ue.enrolid = e.id
                AND ue.status = 0
                JOIN {user} u
                    ON u.id = ue.userid
                JOIN {company_users} cu
                    ON cu.userid = ue.userid
                AND cu.companyid = :companyid
            LEFT JOIN {course_completions} cc
                    ON cc.userid = ue.userid
                AND cc.course = c.id
                AND cc.timecompleted IS NOT NULL
                AND cc.timecompleted BETWEEN :starttime AND :endtime
                WHERE " . implode(' AND ', $where) . "
            GROUP BY c.id, c.fullname
            ORDER BY c.fullname ASC";

        $records = $DB->get_records_sql($sql, $params);
        $rows = [];
        $index = 1;

        foreach ($records as $record) {
            $lmscount = (int)$record->lmscount;

            /*
             * Keep courses even when count is zero because the TechSpec says rows come from
             * visible courses employees are enrolled in.
             */
            $rows[] = [
                'number' => $index,
                'courseid' => (int)$record->courseid,
                'coursename' => format_string($record->coursename),
                'unit' => get_string('reportsact:unitservice', 'block_dashboardanalytics'),
                'lmscount' => $lmscount,
                'actqty' => $lmscount,
            ];

            $index++;
        }

        return $rows;
    }

    private function table_exists(string $tablename): bool {
        global $DB;

        $manager = $DB->get_manager();
        return $manager->table_exists(new \xmldb_table($tablename));
    }
}
