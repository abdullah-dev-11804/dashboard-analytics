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
        $hascompletion = $this->has_completion_sql('ccdash');
        $params += [
            'expirednow' => $now,
            'expiringnow' => $now,
            'expiringsoon' => $soon,
            'activesoon' => $soon,
        ];

        $sql = "SELECT SUM(CASE WHEN {$hascompletion} THEN 1 ELSE 0 END) AS total,
                       SUM(CASE WHEN {$hascompletion}
                                  AND {$expiry} < :expirednow THEN 1 ELSE 0 END) AS expired,
                       SUM(CASE WHEN {$hascompletion}
                                  AND {$expiry} >= :expiringnow
                                  AND {$expiry} <= :expiringsoon THEN 1 ELSE 0 END) AS expiring,
                       SUM(CASE WHEN {$hascompletion}
                                  AND {$expiry} > :activesoon THEN 1 ELSE 0 END) AS active,
                       COUNT(DISTINCT CASE WHEN {$hascompletion} THEN d.{$useridcolumn} ELSE NULL END) AS userswithdocuments
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
        if ($this->source() === null) {
            return [
                'configured' => false,
                'totalactiveusers' => $totalactiveusers,
                'validusers' => 0,
                'compliance' => 0.0,
                'status' => 'muted',
            ];
        }

        $overview = new overview_repository();
        $summary = $overview->overall_employee_compliance_summary($filters);
        $compliance = (float)$summary['percent'];

        return [
            'configured' => true,
            'totalactiveusers' => (int)$summary['total'],
            'validusers' => (int)$summary['compliant'],
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
        $where[] = $this->has_completion_sql('ccdash');
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

    public function status_items(array $filters): array {
        $counts = $this->status_counts($filters);
        $total = max(1, (int)$counts['total'] + (int)$counts['nodocument']);

        return [
            $this->visual_item(get_string('label:active', 'block_dashboardanalytics'), (int)$counts['active'], $total, 'ok'),
            $this->visual_item(get_string('label:expiring', 'block_dashboardanalytics'), (int)$counts['expiring'], $total, 'warning'),
            $this->visual_item(get_string('label:expired', 'block_dashboardanalytics'), (int)$counts['expired'], $total, 'danger'),
            $this->visual_item(get_string('label:nodocument', 'block_dashboardanalytics'), (int)$counts['nodocument'], $total, 'muted'),
        ];
    }

    public function risk_by_company_items(array $filters, int $limit = 10): array {
        global $DB;

        $source = $this->source();
        if ($source === null) {
            return [];
        }

        $employee = new employee_repository();
        $userfilter = $employee->user_filter_sql($filters, 'u', 'riskcompany');
        $company = new company_repository();
        $companysql = $company->company_name_sql('u', 'riskcompany');
        $where = [$userfilter['sql']];
        $params = $userfilter['params'];
        $this->append_origin_filter($where, $params, $source, 'riskcompanyorigin');

        $expiry = $this->expiry_sql('d', $source);
        $expiryjoin = $this->expiry_join_sql('d', $source);
        $now = time();
        $soon = $now + (30 * DAYSECS);
        $params += [
            'riskexpirednow' => $now,
            'riskexpiringnow' => $now,
            'risksoon' => $soon,
        ];

        $table = $source['table'];
        $sql = "SELECT COALESCE({$companysql['expr']}, 'Unassigned') AS companyname,
                       SUM(CASE WHEN {$expiry} < :riskexpirednow THEN 1 ELSE 0 END) AS expired,
                       SUM(CASE WHEN {$expiry} >= :riskexpiringnow AND {$expiry} <= :risksoon THEN 1 ELSE 0 END) AS expiring
                  FROM {{$table}} d
                  JOIN {user} u ON u.id = d.{$source['userid']}
                       {$companysql['join']}
                       {$expiryjoin}
                 WHERE " . implode(' AND ', $where) . "
              GROUP BY COALESCE({$companysql['expr']}, 'Unassigned')";

        $records = $DB->get_records_sql($sql, $params);
        $items = [];
        $max = 1;
        foreach ($records as $record) {
            $total = (int)$record->expired + (int)$record->expiring;
            $max = max($max, $total);
            $items[] = [
                'label' => (string)$record->companyname,
                'value' => (string)$total,
                'rawtotal' => $total,
                'expired' => (int)$record->expired,
                'expiring' => (int)$record->expiring,
            ];
        }

        usort($items, static function(array $a, array $b): int {
            return $b['rawtotal'] <=> $a['rawtotal'];
        });

        $items = array_slice($items, 0, $limit);
        foreach ($items as $index => $item) {
            $items[$index] = [
                'label' => $item['label'],
                'value' => $item['value'],
                'percent' => round(($item['rawtotal'] / $max) * 100, 1),
                'status' => $item['expired'] > 0 ? 'danger' : 'warning',
                'meta' => get_string('meta:expiredexpiring', 'block_dashboardanalytics', (object)[
                    'expired' => $item['expired'],
                    'expiring' => $item['expiring'],
                ]),
            ];
        }

        return $items;
    }

    public function noncompliance_by_course_items(array $filters, int $limit = 10): array {
        global $DB;

        $source = $this->source();
        if ($source === null || empty($source['courseid'])) {
            return [];
        }

        $employee = new employee_repository();
        $userfilter = $employee->user_filter_sql($filters, 'u', 'riskcourse');
        $where = [$userfilter['sql']];
        $params = $userfilter['params'];
        $this->append_origin_filter($where, $params, $source, 'riskcourseorigin');

        $expiry = $this->expiry_sql('d', $source);
        $expiryjoin = $this->expiry_join_sql('d', $source);
        $params['riskcoursenow'] = time() + (30 * DAYSECS);
        $table = $source['table'];

        $sql = "SELECT c.fullname AS coursename,
                       COUNT(1) AS affected
                  FROM {{$table}} d
                  JOIN {user} u ON u.id = d.{$source['userid']}
                  JOIN {course} c ON c.id = d.{$source['courseid']}
                       {$expiryjoin}
                 WHERE " . implode(' AND ', $where) . "
                   AND {$expiry} <= :riskcoursenow
              GROUP BY c.fullname
              ORDER BY affected DESC";

        $records = $DB->get_records_sql($sql, $params, 0, $limit);
        $max = 1;
        foreach ($records as $record) {
            $max = max($max, (int)$record->affected);
        }

        $items = [];
        foreach ($records as $record) {
            $affected = (int)$record->affected;
            $items[] = [
                'label' => format_string((string)$record->coursename),
                'value' => (string)$affected,
                'percent' => round(($affected / $max) * 100, 1),
                'status' => $affected > 20 ? 'danger' : ($affected > 10 ? 'warning' : 'ok'),
                'meta' => get_string('meta:expiredorsoon', 'block_dashboardanalytics'),
            ];
        }

        return $items;
    }

    public function forecast_window_items(array $filters): array {
        $counts = [
            '30 days' => $this->count_expiring_between($filters, 0, 30),
            '60 days' => $this->count_expiring_between($filters, 31, 60),
            '90 days' => $this->count_expiring_between($filters, 61, 90),
        ];
        $max = max(1, max($counts));
        $items = [];
        foreach ($counts as $label => $count) {
            $items[] = [
                'label' => $label,
                'value' => (string)$count,
                'percent' => round(($count / $max) * 100, 1),
                'status' => $label === '30 days' ? 'danger' : ($label === '60 days' ? 'warning' : 'info'),
                'meta' => 'documents expiring',
            ];
        }
        return $items;
    }

    public function compliance_by_dimension_items(array $filters, string $dimension, int $limit = 12): array {
        global $DB;

        $allowed = [
            'department' => "COALESCE(NULLIF(u.department, ''), 'Unassigned')",
            'location' => "COALESCE(NULLIF(u.city, ''), 'Unassigned')",
        ];
        $expr = $allowed[$dimension] ?? $allowed['department'];

        $employee = new employee_repository();
        $userfilter = $employee->user_filter_sql($filters, 'u', 'compdim' . $dimension);

        $sql = "SELECT {$expr} AS label,
                       COUNT(1) AS activeusers
                  FROM {user} u
                 WHERE {$userfilter['sql']}
              GROUP BY {$expr}
              ORDER BY label ASC";

        $records = $DB->get_records_sql($sql, $userfilter['params'], 0, $limit);
        $items = [];
        foreach ($records as $record) {
            $dimensionfilters = $filters;
            if ($dimension === 'location') {
                $dimensionfilters['locations'] = [(string)$record->label];
            } else {
                $dimensionfilters['departments'] = [(string)$record->label];
            }
            $summary = $this->compliance_summary($dimensionfilters);
            $items[] = [
                'label' => (string)$record->label,
                'value' => $summary['compliance'] . '%',
                'percent' => (float)$summary['compliance'],
                'status' => strtolower($summary['status']),
                'meta' => get_string('meta:fullycompliantemployees', 'block_dashboardanalytics', (object)[
                    'compliant' => $summary['validusers'],
                    'total' => $summary['totalactiveusers'],
                ]),
            ];
        }

        usort($items, static function(array $a, array $b): int {
            return $a['percent'] <=> $b['percent'];
        });

        return $items;
    }

    public function compliance_heatmap_items(array $filters, int $limit = 18): array {
        global $DB;

        $employee = new employee_repository();
        $userfilter = $employee->user_filter_sql($filters, 'u', 'heatmap');

        $sql = "SELECT " . $DB->sql_concat("COALESCE(NULLIF(u.department, ''), 'Unassigned')", "' / '", "COALESCE(NULLIF(u.city, ''), 'Unassigned')") . " AS label,
                       COALESCE(NULLIF(u.department, ''), 'Unassigned') AS department,
                       COALESCE(NULLIF(u.city, ''), 'Unassigned') AS location,
                       COUNT(1) AS activeusers
                  FROM {user} u
                 WHERE {$userfilter['sql']}
              GROUP BY COALESCE(NULLIF(u.department, ''), 'Unassigned'),
                       COALESCE(NULLIF(u.city, ''), 'Unassigned')
              ORDER BY activeusers DESC";

        $records = $DB->get_records_sql($sql, $userfilter['params'], 0, $limit);
        $items = [];
        foreach ($records as $record) {
            $cellfilters = $filters;
            $cellfilters['departments'] = [(string)$record->department];
            $cellfilters['locations'] = [(string)$record->location];
            $summary = $this->compliance_summary($cellfilters);
            $items[] = [
                'label' => (string)$record->label,
                'value' => $summary['compliance'] . '%',
                'percent' => (float)$summary['compliance'],
                'status' => strtolower($summary['status']),
                'meta' => get_string('meta:fullycompliantemployees', 'block_dashboardanalytics', (object)[
                    'compliant' => $summary['validusers'],
                    'total' => $summary['totalactiveusers'],
                ]),
            ];
        }

        return $items;
    }

    public function weekly_expiry_histogram_items(array $filters, int $weeks = 13): array {
        $items = [];
        $threshold = max(1, (int)get_config('block_dashboardanalytics', 'forecastthreshold'));
        for ($week = 1; $week <= $weeks; $week++) {
            $start = ($week - 1) * 7;
            $end = ($week * 7) - 1;
            $count = $this->count_expiring_between($filters, $start, $end);
            $items[] = [
                'label' => 'W' . $week,
                'value' => (string)$count,
                'percent' => 0.0,
                'status' => $count >= $threshold ? 'danger' : 'info',
                'meta' => 'week ' . $week,
            ];
        }

        $max = max(1, max(array_map(static function(array $item): int {
            return (int)$item['value'];
        }, $items)));

        foreach ($items as $index => $item) {
            $items[$index]['percent'] = round(((int)$item['value'] / $max) * 100, 1);
        }

        return $items;
    }

    public function expired_expiring_grouped_items(array $filters, string $dimension, int $limit = 10): array {
        global $DB;

        $source = $this->source();
        if ($source === null) {
            return [];
        }

        $dimensionexpr = $this->dimension_expr($dimension);
        $employee = new employee_repository();
        $userfilter = $employee->user_filter_sql($filters, 'u', 'grouped' . $dimension);
        $where = [$userfilter['sql']];
        $params = $userfilter['params'];
        $this->append_origin_filter($where, $params, $source, 'groupedorigin' . $dimension);

        $expiry = $this->expiry_sql('d', $source);
        $expiryjoin = $this->expiry_join_sql('d', $source);
        $now = time();
        $soon = $now + (30 * DAYSECS);
        $params += [
            'groupedexpirednow' . $dimension => $now,
            'groupedexpiringnow' . $dimension => $now,
            'groupedsoon' . $dimension => $soon,
        ];

        $coursejoin = $dimension === 'course' && !empty($source['courseid']) ? "LEFT JOIN {course} cdim ON cdim.id = d.{$source['courseid']}" : '';
        $table = $source['table'];
        $sql = "SELECT {$dimensionexpr} AS label,
                       SUM(CASE WHEN {$expiry} < :groupedexpirednow{$dimension} THEN 1 ELSE 0 END) AS expired,
                       SUM(CASE WHEN {$expiry} >= :groupedexpiringnow{$dimension} AND {$expiry} <= :groupedsoon{$dimension} THEN 1 ELSE 0 END) AS expiring
                  FROM {{$table}} d
                  JOIN {user} u ON u.id = d.{$source['userid']}
                       {$coursejoin}
                       {$expiryjoin}
                 WHERE " . implode(' AND ', $where) . "
              GROUP BY {$dimensionexpr}";

        $records = $DB->get_records_sql($sql, $params);
        return $this->grouped_count_items($records, $limit);
    }

    public function certification_status_stacked_items(array $filters, string $dimension, int $limit = 10): array {
        global $DB;

        $source = $this->source();
        if ($source === null) {
            return [];
        }

        $dimensionexpr = $this->dimension_expr($dimension);
        $employee = new employee_repository();
        $userfilter = $employee->user_filter_sql($filters, 'u', 'stacked' . $dimension);
        $where = [$userfilter['sql']];
        $params = $userfilter['params'];
        $this->append_origin_filter($where, $params, $source, 'stackedorigin' . $dimension);

        $expiry = $this->expiry_sql('d', $source);
        $expiryjoin = $this->expiry_join_sql('d', $source);
        $now = time();
        $soon = $now + (30 * DAYSECS);
        $params += [
            'stackedexpirednow' . $dimension => $now,
            'stackedexpiringnow' . $dimension => $now,
            'stackedsoon' . $dimension => $soon,
            'stackedactivesoon' . $dimension => $soon,
        ];

        $table = $source['table'];
        $sql = "SELECT {$dimensionexpr} AS label,
                       SUM(CASE WHEN {$expiry} > :stackedactivesoon{$dimension} THEN 1 ELSE 0 END) AS active,
                       SUM(CASE WHEN {$expiry} >= :stackedexpiringnow{$dimension} AND {$expiry} <= :stackedsoon{$dimension} THEN 1 ELSE 0 END) AS expiring,
                       SUM(CASE WHEN {$expiry} < :stackedexpirednow{$dimension} THEN 1 ELSE 0 END) AS expired
                  FROM {{$table}} d
                  JOIN {user} u ON u.id = d.{$source['userid']}
                       {$expiryjoin}
                 WHERE " . implode(' AND ', $where) . "
              GROUP BY {$dimensionexpr}";

        $records = $DB->get_records_sql($sql, $params, 0, $limit);
        $items = [];
        foreach ($records as $record) {
            $active = (int)$record->active;
            $expiring = (int)$record->expiring;
            $expired = (int)$record->expired;
            $total = max(1, $active + $expiring + $expired);
            $items[] = [
                'label' => (string)$record->label,
                'value' => (string)$total,
                'percent' => 100.0,
                'status' => 'info',
                'meta' => 'certification status',
                'segments' => [
                    ['label' => 'Active', 'value' => (string)$active, 'percent' => round(($active / $total) * 100, 1), 'status' => 'ok'],
                    ['label' => 'Expiring within 30 days', 'value' => (string)$expiring, 'percent' => round(($expiring / $total) * 100, 1), 'status' => 'warning'],
                    ['label' => 'Expired', 'value' => (string)$expired, 'percent' => round(($expired / $total) * 100, 1), 'status' => 'danger'],
                ],
            ];
        }

        return $items;
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
              ORDER BY CASE WHEN {$expiry} IS NULL THEN 1 ELSE 0 END, {$expiry} ASC";

        $records = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
        $rows = [];
        foreach ($records as $record) {
            $expiry = $record->expirytime !== null ? (int)$record->expirytime : null;
            $days = $expiry !== null ? (int)floor(($expiry - time()) / DAYSECS) : null;
            $rows[] = [
                'cells' => [
                    ['key' => 'employee', 'value' => $showidentity ? fullname($record) : get_string('hiddenuser')],
                    ['key' => 'company', 'value' => (string)$record->companyname],
                    ['key' => 'department', 'value' => (string)$record->department],
                    ['key' => 'location', 'value' => (string)$record->city],
                    ['key' => 'course', 'value' => (string)$record->coursename],
                    ['key' => 'expiry', 'value' => $expiry !== null ? userdate($expiry, get_string('strftimedate')) : '-'],
                    ['key' => 'days', 'value' => $days !== null ? (string)$days : '-'],
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

    public function source(): ?array {
        global $CFG, $DB;

        $table = $this->identifier(get_config('block_dashboardanalytics', 'documenttable'));
        $userid = $this->identifier(get_config('block_dashboardanalytics', 'documentuseridcolumn') ?: 'userid');
        $courseid = $this->identifier(get_config('block_dashboardanalytics', 'documentcourseidcolumn') ?: 'courseid');
        if ($table === '' && $this->table_exists('local_ncasign_jobs')) {
            $table = 'local_ncasign_jobs';
            $userid = 'userid';
            $courseid = 'courseid';
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

        if ($courseid !== '' && !isset($columns[$courseid])) {
            $courseid = '';
        }

        return [
            'table' => $table,
            'userid' => $userid,
            'courseid' => $courseid,
            'expiry' => '',
            'origin' => isset($columns['origin']) ? 'origin' : '',
            'status' => isset($columns['status']) ? 'status' : '',
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

    private function count_expiring_between(array $filters, int $startdays, int $enddays): int {
        global $DB;

        $source = $this->source();
        if ($source === null) {
            return 0;
        }

        $employee = new employee_repository();
        $prefix = 'forecast' . $startdays;
        $userfilter = $employee->user_filter_sql($filters, 'u', $prefix);
        $where = [$userfilter['sql']];
        $params = $userfilter['params'];
        $this->append_origin_filter($where, $params, $source, $prefix . 'origin');

        $expiry = $this->expiry_sql('d', $source);
        $expiryjoin = $this->expiry_join_sql('d', $source);
        $params[$prefix . 'start'] = time() + ($startdays * DAYSECS);
        $params[$prefix . 'end'] = time() + ($enddays * DAYSECS);
        $table = $source['table'];

        $sql = "SELECT COUNT(1)
                  FROM {{$table}} d
                  JOIN {user} u ON u.id = d.{$source['userid']}
                       {$expiryjoin}
                 WHERE " . implode(' AND ', $where) . "
                   AND {$expiry} BETWEEN :{$prefix}start AND :{$prefix}end";

        return (int)$DB->count_records_sql($sql, $params);
    }

    private function visual_item(string $label, int $count, int $total, string $status): array {
        return [
            'label' => $label,
            'value' => (string)$count,
            'percent' => round(($count / max(1, $total)) * 100, 1),
            'status' => $status,
            'meta' => round(($count / max(1, $total)) * 100, 1) . '%',
        ];
    }

    private function dimension_expr(string $dimension): string {
        if ($dimension === 'location') {
            return "COALESCE(NULLIF(u.city, ''), 'Unassigned')";
        }

        if ($dimension === 'course') {
            return "COALESCE(NULLIF(cdim.fullname, ''), 'Unassigned')";
        }

        return "COALESCE(NULLIF(u.department, ''), 'Unassigned')";
    }

    private function grouped_count_items(array $records, int $limit): array {
        $items = [];
        $max = 1;
        foreach ($records as $record) {
            $expired = (int)$record->expired;
            $expiring = (int)$record->expiring;
            $total = $expired + $expiring;
            $max = max($max, $total);
            $items[] = [
                'label' => (string)$record->label,
                'value' => (string)$total,
                'rawtotal' => $total,
                'expired' => $expired,
                'expiring' => $expiring,
            ];
        }

        usort($items, static function(array $a, array $b): int {
            return $b['rawtotal'] <=> $a['rawtotal'];
        });

        $items = array_slice($items, 0, $limit);
        foreach ($items as $index => $item) {
            $items[$index] = [
                'label' => $item['label'],
                'value' => $item['value'],
                'percent' => round(($item['rawtotal'] / $max) * 100, 1),
                'status' => $item['expired'] > 0 ? 'danger' : 'warning',
                'meta' => $item['expired'] . ' expired, ' . $item['expiring'] . ' expiring',
                'segments' => [
                    ['label' => 'Expired now', 'value' => (string)$item['expired'], 'percent' => round(($item['expired'] / $max) * 100, 1), 'status' => 'danger'],
                    ['label' => 'Expiring within 30 days', 'value' => (string)$item['expiring'], 'percent' => round(($item['expiring'] / $max) * 100, 1), 'status' => 'warning'],
                ],
            ];
        }

        return $items;
    }

    public function expiry_sql(string $alias, array $source): string {
        if (empty($source['courseid'])) {
            return '0';
        }

        return "CASE
                    WHEN ccdash.timecompleted IS NULL OR ccdash.timecompleted <= 0 THEN NULL
                    ELSE ccdash.timecompleted + (COALESCE(NULLIF(cfdash.intvalue, 0), NULLIF(cfdash.decvalue, 0), NULLIF(cfdash.value, ''), 1) * 86400)
                END";
    }

    public function expiry_join_sql(string $alias, array $source): string {
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
            return get_string('label:green', 'block_dashboardanalytics');
        }

        if ($compliance >= 70) {
            return get_string('label:amber', 'block_dashboardanalytics');
        }

        return get_string('label:red', 'block_dashboardanalytics');
    }

    private function has_completion_sql(string $alias): string {
        return "{$alias}.timecompleted IS NOT NULL AND {$alias}.timecompleted > 0";
    }

    private function status_label(?int $expiry): string {
        if ($expiry === null || $expiry <= 0) {
            return get_string('label:nodocument', 'block_dashboardanalytics');
        }

        if ($expiry < time()) {
            return get_string('label:expired', 'block_dashboardanalytics');
        }

        if ($expiry <= time() + (30 * DAYSECS)) {
            return get_string('label:expiring', 'block_dashboardanalytics');
        }

        return get_string('label:active', 'block_dashboardanalytics');
    }

    private function columns(): array {
        return [
            ['key' => 'employee', 'label' => get_string('label:employee', 'block_dashboardanalytics')],
            ['key' => 'company', 'label' => get_string('label:company', 'block_dashboardanalytics')],
            ['key' => 'department', 'label' => get_string('label:department', 'block_dashboardanalytics')],
            ['key' => 'location', 'label' => get_string('label:location', 'block_dashboardanalytics')],
            ['key' => 'course', 'label' => get_string('label:course', 'block_dashboardanalytics')],
            ['key' => 'expiry', 'label' => get_string('label:expirydate', 'block_dashboardanalytics')],
            ['key' => 'days', 'label' => get_string('label:daysremaining', 'block_dashboardanalytics')],
            ['key' => 'status', 'label' => get_string('label:status', 'block_dashboardanalytics')],
        ];
    }
}
