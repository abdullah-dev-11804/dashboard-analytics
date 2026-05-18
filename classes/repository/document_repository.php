<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\repository;

defined('MOODLE_INTERNAL') || die();

class document_repository {

    public function is_configured(): bool {
        return $this->source() !== null;
    }

    public function status_counts(array $filters): array {
        global $DB;

        $source = $this->source();
        if ($source === null) {
            return [
                'configured' => false,
                'total' => 0,
                'active' => 0,
                'expiring' => 0,
                'expired' => 0,
                'nodocument' => 0,
            ];
        }

        $employee = new employee_repository();
        $userfilter = $employee->user_filter_sql($filters, 'u', 'doccount');
        $where = [$userfilter['sql']];
        $params = $userfilter['params'];
        $this->append_course_filter($where, $params, $filters, $source, 'doccountcourse');

        $now = time();
        $soon = $now + (30 * DAYSECS);
        $table = $source['table'];
        $useridcolumn = $source['userid'];
        $expirycolumn = $source['expiry'];
        $params += [
            'expirednow' => $now,
            'expiringnow' => $now,
            'expiringsoon' => $soon,
            'activesoon' => $soon,
        ];

        $sql = "SELECT COUNT(1) AS total,
                       SUM(CASE WHEN d.{$expirycolumn} < :expirednow THEN 1 ELSE 0 END) AS expired,
                       SUM(CASE WHEN d.{$expirycolumn} >= :expiringnow
                                  AND d.{$expirycolumn} <= :expiringsoon THEN 1 ELSE 0 END) AS expiring,
                       SUM(CASE WHEN d.{$expirycolumn} > :activesoon THEN 1 ELSE 0 END) AS active,
                       COUNT(DISTINCT d.{$useridcolumn}) AS userswithdocuments
                  FROM {{$table}} d
                  JOIN {user} u ON u.id = d.{$useridcolumn}
                 WHERE " . implode(' AND ', $where);

        $record = $DB->get_record_sql($sql, $params);
        $totalstaff = $employee->count_active_users($filters);

        return [
            'configured' => true,
            'total' => (int)($record->total ?? 0),
            'active' => (int)($record->active ?? 0),
            'expiring' => (int)($record->expiring ?? 0),
            'expired' => (int)($record->expired ?? 0),
            'nodocument' => max(0, $totalstaff - (int)($record->userswithdocuments ?? 0)),
        ];
    }

    public function document_rows(array $filters, string $status, int $page, int $perpage, bool $showidentity): array {
        global $DB;

        $source = $this->source();
        if ($source === null) {
            return [
                'columns' => $this->columns(),
                'rows' => [],
                'totalcount' => 0,
                'notice' => get_string('settings:documentheading_desc', 'block_dashboardanalytics'),
            ];
        }

        $employee = new employee_repository();
        $userfilter = $employee->user_filter_sql($filters, 'u', 'docrows');
        $where = [$userfilter['sql']];
        $params = $userfilter['params'];
        $this->append_course_filter($where, $params, $filters, $source, 'docrowscourse');
        $this->append_status_filter($where, $params, $status, $source, 'docrowsstatus');

        $table = $source['table'];
        $useridcolumn = $source['userid'];
        $expirycolumn = $source['expiry'];
        $coursejoin = '';
        $courseselect = "'' AS coursename";
        if ($source['courseid'] !== '') {
            $coursejoin = "LEFT JOIN {course} c ON c.id = d.{$source['courseid']}";
            $courseselect = 'c.fullname AS coursename';
        }

        $wheresql = implode(' AND ', $where);
        $countsql = "SELECT COUNT(1)
                       FROM {{$table}} d
                       JOIN {user} u ON u.id = d.{$useridcolumn}
                            {$coursejoin}
                      WHERE {$wheresql}";
        $totalcount = (int)$DB->count_records_sql($countsql, $params);

        $sql = "SELECT d.id,
                       d.{$expirycolumn} AS expirytime,
                       u.firstname,
                       u.lastname,
                       u.department,
                       u.city,
                       {$courseselect}
                  FROM {{$table}} d
                  JOIN {user} u ON u.id = d.{$useridcolumn}
                       {$coursejoin}
                 WHERE {$wheresql}
              ORDER BY d.{$expirycolumn} ASC";

        $records = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
        $rows = [];
        foreach ($records as $record) {
            $expiry = (int)$record->expirytime;
            $days = (int)floor(($expiry - time()) / DAYSECS);
            $rows[] = [
                'cells' => [
                    ['key' => 'employee', 'value' => $showidentity ? fullname($record) : get_string('hiddenuser')],
                    ['key' => 'department', 'value' => (string)$record->department],
                    ['key' => 'location', 'value' => (string)$record->city],
                    ['key' => 'course', 'value' => (string)$record->coursename],
                    ['key' => 'expiry', 'value' => userdate($expiry, get_string('strftimedate'))],
                    ['key' => 'days', 'value' => (string)$days],
                    ['key' => 'status', 'value' => $this->status_label($expiry)],
                ],
            ];
        }

        return [
            'columns' => $this->columns(),
            'rows' => $rows,
            'totalcount' => $totalcount,
            'notice' => '',
        ];
    }

    private function source(): ?array {
        global $CFG, $DB;

        $table = $this->identifier(get_config('block_dashboardanalytics', 'documenttable'));
        $userid = $this->identifier(get_config('block_dashboardanalytics', 'documentuseridcolumn') ?: 'userid');
        $courseid = $this->identifier(get_config('block_dashboardanalytics', 'documentcourseidcolumn') ?: 'courseid');
        $expiry = $this->identifier(get_config('block_dashboardanalytics', 'documentexpirycolumn') ?: 'timeexpires');

        if ($table === '' || $userid === '' || $expiry === '') {
            return null;
        }

        require_once($CFG->libdir . '/ddl/xmldb_table.php');
        if (!$DB->get_manager()->table_exists(new \xmldb_table($table))) {
            return null;
        }

        $columns = $DB->get_columns($table);
        if (!isset($columns[$userid]) || !isset($columns[$expiry])) {
            return null;
        }

        if ($courseid !== '' && !isset($columns[$courseid])) {
            $courseid = '';
        }

        return [
            'table' => $table,
            'userid' => $userid,
            'courseid' => $courseid,
            'expiry' => $expiry,
        ];
    }

    private function identifier($value): string {
        $value = trim((string)$value);
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $value)) {
            return '';
        }

        return strtolower($value);
    }

    private function append_course_filter(array &$where, array &$params, array $filters, array $source, string $prefix): void {
        global $DB;

        if ($source['courseid'] === '' || empty($filters['courseids'])) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($filters['courseids'], SQL_PARAMS_NAMED, $prefix);
        $where[] = "d.{$source['courseid']} {$insql}";
        $params += $inparams;
    }

    private function append_status_filter(array &$where, array &$params, string $status, array $source, string $prefix): void {
        $now = time();
        $soon = $now + (30 * DAYSECS);

        if ($status === 'expired') {
            $where[] = "d.{$source['expiry']} < :{$prefix}now";
            $params[$prefix . 'now'] = $now;
        } else if ($status === 'expiring') {
            $where[] = "d.{$source['expiry']} >= :{$prefix}now";
            $where[] = "d.{$source['expiry']} <= :{$prefix}soon";
            $params[$prefix . 'now'] = $now;
            $params[$prefix . 'soon'] = $soon;
        } else if ($status === 'active') {
            $where[] = "d.{$source['expiry']} > :{$prefix}soon";
            $params[$prefix . 'soon'] = $soon;
        }
    }

    private function status_label(int $expiry): string {
        if ($expiry < time()) {
            return 'Expired';
        }

        if ($expiry <= time() + (30 * DAYSECS)) {
            return 'Expiring';
        }

        return 'Active';
    }

    private function columns(): array {
        return [
            ['key' => 'employee', 'label' => 'Employee'],
            ['key' => 'department', 'label' => 'Department'],
            ['key' => 'location', 'label' => 'Location'],
            ['key' => 'course', 'label' => 'Course'],
            ['key' => 'expiry', 'label' => 'Expiry date'],
            ['key' => 'days', 'label' => 'Days remaining'],
            ['key' => 'status', 'label' => 'Status'],
        ];
    }
}
