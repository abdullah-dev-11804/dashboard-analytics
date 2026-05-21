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
        $this->append_origin_filter($where, $params, $source, 'doccountorigin');
        $this->append_course_filter($where, $params, $filters, $source, 'doccountcourse');

        $now = time();
        $soon = $now + (30 * DAYSECS);
        $table = $source['table'];
        $useridcolumn = $source['userid'];
        $expiry = $this->expiry_sql('d', $source);
        $expiryjoin = $this->expiry_join_sql('d', $source);
        $params += [
            'expirednow' => $now,
            'expiringnow' => $now,
            'expiringsoon' => $soon,
            'activesoon' => $soon,
        ];

        $sql = "SELECT COUNT(1) AS total,
                       SUM(CASE WHEN {$expiry} < :expirednow THEN 1 ELSE 0 END) AS expired,
                       SUM(CASE WHEN {$expiry} >= :expiringnow
                                  AND {$expiry} <= :expiringsoon THEN 1 ELSE 0 END) AS expiring,
                       SUM(CASE WHEN {$expiry} > :activesoon THEN 1 ELSE 0 END) AS active,
                       COUNT(DISTINCT d.{$useridcolumn}) AS userswithdocuments
                  FROM {{$table}} d
                  JOIN {user} u ON u.id = d.{$useridcolumn}
                       {$expiryjoin}
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

    public function compliance_summary(array $filters): array {
        $employee = new employee_repository();
        $totalactiveusers = $employee->count_active_users($filters);
        $validusers = $this->count_valid_signed_users($filters);
        $compliance = $totalactiveusers > 0 ? round(($validusers / $totalactiveusers) * 100, 1) : 0.0;

        return [
            'configured' => $this->source() !== null,
            'totalactiveusers' => $totalactiveusers,
            'validusers' => $validusers,
            'compliance' => $compliance,
            'status' => $this->compliance_status($compliance),
        ];
    }

    public function count_valid_signed_users(array $filters): int {
        global $DB;

        $source = $this->source();
        if ($source === null) {
            return 0;
        }

        $employee = new employee_repository();
        $userfilter = $employee->user_filter_sql($filters, 'u', 'validdocs');
        $where = [
            $userfilter['sql'],
            "d.status IN (:validstatusmanual, :validstatusauto)",
        ];
        $params = $userfilter['params'] + [
            'validstatusmanual' => 'completed_manual',
            'validstatusauto' => 'completed_auto',
            'validnow' => time(),
        ];

        if (!empty($source['origin'])) {
            $where[] = "d.{$source['origin']} = :validorigin";
            $params['validorigin'] = 'course_completion';
        }

        $this->append_course_filter($where, $params, $filters, $source, 'validdoccourse');
        $expiry = $this->expiry_sql('d', $source);
        $expiryjoin = $this->expiry_join_sql('d', $source);
        $where[] = "{$expiry} >= :validnow";

        $table = $source['table'];
        $useridcolumn = $source['userid'];
        $sql = "SELECT COUNT(DISTINCT d.{$useridcolumn})
                  FROM {{$table}} d
                  JOIN {user} u ON u.id = d.{$useridcolumn}
                       {$expiryjoin}
                 WHERE " . implode(' AND ', $where);

        return (int)$DB->count_records_sql($sql, $params);
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
        $this->append_origin_filter($where, $params, $source, 'docrowsorigin');
        $this->append_course_filter($where, $params, $filters, $source, 'docrowscourse');
        $this->append_status_filter($where, $params, $status, $source, 'docrowsstatus');

        $table = $source['table'];
        $useridcolumn = $source['userid'];
        $expiry = $this->expiry_sql('d', $source);
        $expiryjoin = $this->expiry_join_sql('d', $source);
        $coursejoin = '';
        $courseselect = "'' AS coursename";
        $company = new company_repository();
        $companysql = $company->company_name_sql('u', 'docrowscompany');
        if ($source['courseid'] !== '') {
            $coursejoin = "LEFT JOIN {course} c ON c.id = d.{$source['courseid']}";
            $courseselect = 'c.fullname AS coursename';
        }

        $wheresql = implode(' AND ', $where);
        $countsql = "SELECT COUNT(1)
                       FROM {{$table}} d
                       JOIN {user} u ON u.id = d.{$useridcolumn}
                            {$coursejoin}
                            {$expiryjoin}
                      WHERE {$wheresql}";
        $totalcount = (int)$DB->count_records_sql($countsql, $params);

        $sql = "SELECT d.id,
                       {$expiry} AS expirytime,
                       u.firstname,
                       u.lastname,
                       u.department,
                       u.city,
                       {$companysql['select']},
                       {$courseselect}
                  FROM {{$table}} d
                  JOIN {user} u ON u.id = d.{$useridcolumn}
                       {$coursejoin}
                       {$companysql['join']}
                       {$expiryjoin}
                 WHERE {$wheresql}
              ORDER BY {$expiry} ASC";

        $records = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
        $rows = [];
        foreach ($records as $record) {
            $expiry = (int)$record->expirytime;
            $days = (int)floor(($expiry - time()) / DAYSECS);
            $rows[] = [
                'cells' => [
                    ['key' => 'employee', 'value' => $showidentity ? fullname($record) : get_string('hiddenuser')],
                    ['key' => 'company', 'value' => (string)$record->companyname],
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
        $expiry = $this->identifier(get_config('block_dashboardanalytics', 'documentexpirycolumn') ?: 'expirydate');

        if ($table === '' && $this->table_exists('local_ncasign_jobs')) {
            $table = 'local_ncasign_jobs';
            $userid = 'userid';
            $courseid = 'courseid';
            $expiry = 'expirydate';
        }

        if ($table === '' || $userid === '') {
            return null;
        }

        if (!$this->table_exists($table)) {
            return null;
        }

        $columns = $DB->get_columns($table);
        if (!isset($columns[$userid])) {
            return null;
        }

        if ($expiry !== '' && !isset($columns[$expiry])) {
            $expiry = '';
        }

        if ($courseid !== '' && !isset($columns[$courseid])) {
            $courseid = '';
        }

        return [
            'table' => $table,
            'userid' => $userid,
            'courseid' => $courseid,
            'expiry' => $expiry,
            'origin' => isset($columns['origin']) ? 'origin' : '',
        ];
    }

    private function table_exists(string $tablename): bool {
        global $CFG, $DB;

        require_once($CFG->libdir . '/xmldb/xmldb_table.php');
        return $DB->get_manager()->table_exists(new \xmldb_table($tablename));
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

    private function append_origin_filter(array &$where, array &$params, array $source, string $prefix): void {
        if (empty($source['origin'])) {
            return;
        }

        $where[] = "(d.{$source['origin']} <> :{$prefix}demo OR d.{$source['origin']} IS NULL)";
        $params[$prefix . 'demo'] = 'demo_job';
    }

    private function append_status_filter(array &$where, array &$params, string $status, array $source, string $prefix): void {
        $now = time();
        $soon = $now + (30 * DAYSECS);
        $expiry = $this->expiry_sql('d', $source);

        if ($status === 'expired') {
            $where[] = "{$expiry} < :{$prefix}now";
            $params[$prefix . 'now'] = $now;
        } else if ($status === 'expiring') {
            $where[] = "{$expiry} >= :{$prefix}now";
            $where[] = "{$expiry} <= :{$prefix}soon";
            $params[$prefix . 'now'] = $now;
            $params[$prefix . 'soon'] = $soon;
        } else if ($status === 'active') {
            $where[] = "{$expiry} > :{$prefix}soon";
            $params[$prefix . 'soon'] = $soon;
        }
    }

    private function expiry_sql(string $alias, array $source): string {
        $fallback = '0';
        if (!empty($source['courseid'])) {
            $fallback = "COALESCE(ccdash.timecompleted, 0) + (COALESCE(cfdash.intvalue, cfdash.decvalue, cfdash.value, 0) * 86400)";
        }

        if (!empty($source['expiry'])) {
            return "COALESCE(NULLIF({$alias}.{$source['expiry']}, 0), {$fallback})";
        }

        return $fallback;
    }

    private function expiry_join_sql(string $alias, array $source): string {
        if (empty($source['courseid'])) {
            return '';
        }

        return "LEFT JOIN {course_completions} ccdash
                       ON ccdash.userid = {$alias}.{$source['userid']}
                      AND ccdash.course = {$alias}.{$source['courseid']}
                LEFT JOIN {customfield_field} cffdash
                       ON cffdash.shortname = 'validity_period'
                LEFT JOIN {customfield_data} cfdash
                       ON cfdash.fieldid = cffdash.id
                      AND cfdash.instanceid = {$alias}.{$source['courseid']}";
    }

    private function compliance_status(float $compliance): string {
        if ($compliance >= 80) {
            return 'Green';
        }

        if ($compliance >= 70) {
            return 'Amber';
        }

        return 'Red';
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
            ['key' => 'company', 'label' => 'Company'],
            ['key' => 'department', 'label' => 'Department'],
            ['key' => 'location', 'label' => 'Location'],
            ['key' => 'course', 'label' => 'Course'],
            ['key' => 'expiry', 'label' => 'Expiry date'],
            ['key' => 'days', 'label' => 'Days remaining'],
            ['key' => 'status', 'label' => 'Status'],
        ];
    }
}
