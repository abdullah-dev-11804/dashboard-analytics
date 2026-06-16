<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\repository;

defined('MOODLE_INTERNAL') || die();
ini_set('log_errors', '1');
ini_set('error_log', '/tmp/ncasign-debug.log');
class overview_repository {

    public function overall_employee_compliance_summary(array $filters, ?int $reportdate = null): array {
        $reportdate = $reportdate ?? $this->current_report_date();
        $rows = $this->enrolment_status_rows($filters, $reportdate);
        $users = [];

        foreach ($rows as $row) {
            if (!isset($users[$row['userid']])) {
                $users[$row['userid']] = ['total' => 0, 'bad' => 0];
            }

            $users[$row['userid']]['total']++;
            if ($row['status'] === 'Expired' || $row['status'] === 'No document') {
                $users[$row['userid']]['bad']++;
            }
        }

        $total = 0;
        $compliant = 0;
        foreach ($users as $user) {
            if ($user['total'] <= 0) {
                continue;
            }

            $total++;
            if ($user['bad'] === 0) {
                $compliant++;
            }
        }

        return [
            'total' => $total,
            'compliant' => $compliant,
            'percent' => $total > 0 ? round(($compliant / $total) * 100, 1) : 0.0,
        ];
    }

    public function status_counts(array $filters, ?int $reportdate = null): array {
        $reportdate = $reportdate ?? $this->current_report_date();
        $rows = $this->enrolment_status_rows($filters, $reportdate);
        $counts = [
            'active' => 0,
            'expiring' => 0,
            'expired' => 0,
            'nodocument' => 0,
        ];

        foreach ($rows as $row) {
            if ($row['status'] === 'Active') {
                $counts['active']++;
            } else if ($row['status'] === 'Expiring') {
                $counts['expiring']++;
            } else if ($row['status'] === 'Expired') {
                $counts['expired']++;
            } else if ($row['status'] === 'No document') {
                $counts['nodocument']++;
            }
        }

        return $counts;
    }

    public function compliance_trend_items(array $filters): array {
        $months = $this->month_windows($filters);
        $current = $this->company_compliance_items($filters, 8);
        $companies = array_slice(array_column($current, 'label'), 0, 4);
        $seriesstatuses = ['info', 'danger', 'warning', 'ok', 'muted'];

        $items = [];
        foreach ($months as $month) {
            $summaries = $this->company_summaries($filters, $month['end']);
            $summarymap = [];
            foreach ($summaries as $summary) {
                $summarymap[$summary['label']] = $summary;
            }

            $segments = [];
            $maxpercent = 1.0;
            foreach ($companies as $index => $company) {
                $summary = $summarymap[$company] ?? ['percent' => 0.0, 'total' => 0, 'compliant' => 0];
                $percent = (float)$summary['percent'];
                $maxpercent = max($maxpercent, $percent);
                $segments[] = [
                    'label' => $company,
                    'value' => $summary['total'] > 0 ? $summary['percent'] . '%' : '0%',
                    'percent' => $percent,
                    'status' => $seriesstatuses[$index % count($seriesstatuses)],
                ];
            }

            $items[] = [
                'label' => $month['label'],
                'value' => '',
                'percent' => $maxpercent,
                'status' => 'info',
                'meta' => 'Monthly compliance by company',
                'segments' => $segments,
            ];
        }

        return $items;
    }

    public function company_compliance_items(array $filters, int $limit = 12): array {
        $summaries = $this->company_summaries($filters, $this->current_report_date());
        usort($summaries, static function(array $a, array $b): int {
            return $a['percent'] <=> $b['percent'];
        });

        $items = [];
        foreach (array_slice($summaries, 0, $limit) as $summary) {
            $items[] = [
                'label' => $summary['label'],
                'value' => $summary['total'] > 0 ? $summary['percent'] . '%' : 'No enrolled users',
                'percent' => (float)$summary['percent'],
                'status' => $summary['total'] > 0 ? $this->status_for_percent((float)$summary['percent']) : 'muted',
                'meta' => $summary['compliant'] . ' compliant / ' . $summary['total'] . ' enrolled employees',
            ];
        }

        return $items;
    }

    public function status_distribution_items(array $filters): array {
        $rows = $this->enrolment_status_rows($filters, $this->current_report_date());
        $counts = [
            'Active' => 0,
            'Expiring' => 0,
            'Expired' => 0,
            'No document' => 0,
        ];

        foreach ($rows as $row) {
            $counts[$row['status']]++;
        }

        $this->debug_log('status_distribution_items counts', [
            'filters' => $filters,
            'rowcount' => count($rows),
            'counts' => $counts,
        ]);

        $total = max(1, array_sum($counts));
        return [
            $this->status_item('Active', $counts['Active'], $total, 'ok'),
            $this->status_item('Expiring', $counts['Expiring'], $total, 'warning'),
            $this->status_item('Expired', $counts['Expired'], $total, 'danger'),
            $this->status_item('No document', $counts['No document'], $total, 'muted'),
        ];
    }

    public function expired_expiring_by_company_items(array $filters, int $limit = 10): array {
        $rows = $this->enrolment_status_rows($filters, $this->current_report_date());
        $companies = [];
        foreach ($rows as $row) {
            $company = $row['company'] ?: 'Unassigned';
            if (!isset($companies[$company])) {
                $companies[$company] = ['expired' => 0, 'expiring' => 0];
            }
            if ($row['status'] === 'Expired') {
                $companies[$company]['expired']++;
            } else if ($row['status'] === 'Expiring') {
                $companies[$company]['expiring']++;
            }
        }

        $items = [];
        $max = 1;
        foreach ($companies as $company => $counts) {
            $total = $counts['expired'] + $counts['expiring'];
            $max = max($max, $total);
            $items[] = [
                'label' => $company,
                'rawtotal' => $total,
                'expired' => $counts['expired'],
                'expiring' => $counts['expiring'],
            ];
        }

        usort($items, static function(array $a, array $b): int {
            return $b['rawtotal'] <=> $a['rawtotal'];
        });

        $items = array_slice($items, 0, $limit);
        foreach ($items as $index => $item) {
            $items[$index] = [
                'label' => $item['label'],
                'value' => (string)$item['rawtotal'],
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

    public function course_non_compliance_items(array $filters, int $limit = 10): array {
        $rows = $this->enrolment_status_rows($filters, $this->current_report_date());
        $courses = [];
        foreach ($rows as $row) {
            $course = $row['course'] ?: 'Unassigned';
            if (!isset($courses[$course])) {
                $courses[$course] = ['total' => 0, 'affected' => 0];
            }
            $courses[$course]['total']++;
            if ($row['status'] === 'Expired' || $row['status'] === 'No document') {
                $courses[$course]['affected']++;
            }
        }

        $items = [];
        foreach ($courses as $course => $counts) {
            $percent = $counts['total'] > 0 ? round(($counts['affected'] / $counts['total']) * 100, 1) : 0.0;
            $items[] = [
                'label' => $course,
                'value' => $percent . '%',
                'percent' => $percent,
                'status' => $percent > 40 ? 'danger' : ($percent >= 20 ? 'warning' : 'ok'),
                'meta' => $counts['affected'] . ' affected / ' . $counts['total'] . ' enrolled',
            ];
        }

        usort($items, static function(array $a, array $b): int {
            return $b['percent'] <=> $a['percent'];
        });

        return array_slice($items, 0, $limit);
    }

    private function company_summaries(array $filters, int $reportdate): array {
        $rows = $this->enrolment_status_rows($filters, $reportdate);
        $companies = [];
        foreach ($rows as $row) {
            $company = $row['company'] ?: 'Unassigned';
            if (!isset($companies[$company])) {
                $companies[$company] = [];
            }
            if (!isset($companies[$company][$row['userid']])) {
                $companies[$company][$row['userid']] = ['total' => 0, 'bad' => 0];
            }
            $companies[$company][$row['userid']]['total']++;
            if ($row['status'] === 'Expired' || $row['status'] === 'No document') {
                $companies[$company][$row['userid']]['bad']++;
            }
        }

        $summaries = [];
        foreach ($companies as $company => $users) {
            $total = 0;
            $compliant = 0;
            foreach ($users as $user) {
                if ($user['total'] <= 0) {
                    continue;
                }
                $total++;
                if ($user['bad'] === 0) {
                    $compliant++;
                }
            }

            $summaries[] = [
                'label' => $company,
                'total' => $total,
                'compliant' => $compliant,
                'percent' => $total > 0 ? round(($compliant / $total) * 100, 1) : 0.0,
            ];
        }

        return $summaries;
    }

    private function enrolment_status_rows(array $filters, int $reportdate): array {
        global $DB;

        $employee = new employee_repository();
        $documents = new document_repository();
        $companyrepo = new company_repository();
        $source = $documents->source();
        if ($source === null || $source['courseid'] === '') {
            $this->debug_log('enrolment_status_rows source unavailable', [
                'source' => $source,
                'filters' => $filters,
            ]);
            return [];
        }

        $userfilter = $employee->user_filter_sql($filters, 'u', 'overview');
        $companysql = $companyrepo->company_name_sql('u', 'overview');
        $params = $userfilter['params'] + ['siteid' => SITEID];
        $positionselect = "'' AS positionname";
        $positionfield = trim((string)get_config('block_dashboardanalytics', 'positionfield'));
        $positionjoin = '';
        if ($positionfield !== '') {
            $positionjoin = "LEFT JOIN {user_info_field} uifpos ON uifpos.shortname = :positionfield
                             LEFT JOIN {user_info_data} uidpos ON uidpos.fieldid = uifpos.id AND uidpos.userid = u.id";
            $positionselect = 'uidpos.data AS positionname';
            $params['positionfield'] = $positionfield;
        }

        $departmentselect = 'u.department AS departmentname';
        $departmentjoin = '';
        if ($DB->record_exists('user_info_field', ['shortname' => 'department'])) {
            $departmentjoin = "LEFT JOIN {user_info_field} uifdep ON uifdep.shortname = :departmentfield
                               LEFT JOIN {user_info_data} uiddep ON uiddep.fieldid = uifdep.id AND uiddep.userid = u.id";
            $departmentselect = 'COALESCE(NULLIF(uiddep.data, \'\'), u.department) AS departmentname';
            $params['departmentfield'] = 'department';
        }

        $validitysql = $this->validity_days_sql('cfd');
        $expiryselect = "COALESCE(cc.timecompleted, 0) + ({$validitysql} * 86400) AS expirytime";
        $docjoin = $this->latest_document_join_sql($source, 'u', 'c', 'd');
        $basewhere = [
            $userfilter['sql'],
            'c.id <> :siteid',
        ];

        if (!empty($filters['courseids'])) {
            [$insql, $inparams] = $DB->get_in_or_equal($filters['courseids'], SQL_PARAMS_NAMED, 'overviewcourse');
            $basewhere[] = "c.id {$insql}";
            $params += $inparams;
        }

        $this->debug_log('enrolment_status_rows starting', [
            'source' => $source,
            'filters' => $filters,
            'basewhere' => $basewhere,
            'params' => $params,
        ]);

        $documentssql = "SELECT " . $DB->sql_concat("'doc-'", 'd.id') . " AS rowid,
                                u.id AS userid,
                                c.id AS courseid,
                                c.fullname AS coursename,
                                u.firstname,
                                u.lastname,
                                u.city,
                                {$departmentselect},
                                {$positionselect},
                                {$companysql['select']},
                                d.id AS documentid,
                                {$expiryselect}
                           FROM {{$source['table']}} d
                           JOIN {user} u ON u.id = d.{$source['userid']}
                           JOIN {course} c ON c.id = d.{$source['courseid']}
                      LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id
                      LEFT JOIN {customfield_field} cff ON cff.shortname = 'validity_period'
                      LEFT JOIN {customfield_data} cfd ON cfd.fieldid = cff.id AND cfd.instanceid = c.id
                                {$companysql['join']}
                                {$departmentjoin}
                                {$positionjoin}
                          WHERE " . implode(' AND ', array_merge($basewhere, [
                              "d.id = (
                                  SELECT MAX(d2.id)
                                    FROM {{$source['table']}} d2
                                   WHERE d2.{$source['userid']} = d.{$source['userid']}
                                     AND d2.{$source['courseid']} = d.{$source['courseid']}
                                     AND (d2.{$source['origin']} <> 'demo_job' OR d2.{$source['origin']} IS NULL)
                                     AND d2.{$source['status']} IN ('completed_manual', 'completed_auto')
                              )",
                              "(d.{$source['origin']} <> 'demo_job' OR d.{$source['origin']} IS NULL)",
                              "d.{$source['status']} IN ('completed_manual', 'completed_auto')",
                          ]));

        $nodocssql = "SELECT " . $DB->sql_concat("'nodoc-'", 'ue.id') . " AS rowid,
                             u.id AS userid,
                             c.id AS courseid,
                             c.fullname AS coursename,
                             u.firstname,
                             u.lastname,
                             u.city,
                             {$departmentselect},
                             {$positionselect},
                             {$companysql['select']},
                             COALESCE(d.id, 0) AS documentid,
                             {$expiryselect}
                        FROM {user} u
                        JOIN {user_enrolments} ue ON ue.userid = u.id AND ue.status = 0
                        JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
                        JOIN {course} c ON c.id = e.courseid
                   LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id
                   LEFT JOIN {customfield_field} cff ON cff.shortname = 'validity_period'
                   LEFT JOIN {customfield_data} cfd ON cfd.fieldid = cff.id AND cfd.instanceid = c.id
                             {$companysql['join']}
                             {$departmentjoin}
                             {$positionjoin}
                             {$docjoin}
                       WHERE " . implode(' AND ', array_merge($basewhere, [
                           'ue.status = 0',
                           'e.status = 0',
                           'd.id IS NULL',
                       ]));

        $documentrecords = $DB->get_records_sql($documentssql, $params, 0, 5000);
        $nodocumentrecords = $DB->get_records_sql($nodocssql, $params, 0, 5000);
        $records = $documentrecords + $nodocumentrecords;

        $this->debug_log('enrolment_status_rows query results', [
            'documentrowcount' => count($documentrecords),
            'nodocumentrowcount' => count($nodocumentrecords),
            'mergedrowcount' => count($records),
            'documentssql' => $documentssql,
            'nodocssql' => $nodocssql,
        ]);

        $rows = [];
        $samples = [];
        foreach ($records as $record) {
            $status = $this->status_for_row((int)$record->documentid, (int)$record->expirytime, $reportdate);
            $rows[] = [
                'userid' => (int)$record->userid,
                'courseid' => (int)$record->courseid,
                'employee' => fullname($record),
                'company' => (string)$record->companyname,
                'department' => (string)$record->departmentname,
                'location' => (string)$record->city,
                'position' => (string)$record->positionname,
                'course' => format_string((string)$record->coursename),
                'documentid' => (int)$record->documentid,
                'expirytime' => (int)$record->expirytime,
                'status' => $status,
            ];

            if (count($samples) < 10) {
                $samples[] = [
                    'userid' => (int)$record->userid,
                    'courseid' => (int)$record->courseid,
                    'course' => $this->truncate_text((string)$record->coursename, 120),
                    'documentid' => (int)$record->documentid,
                    'expirytime' => (int)$record->expirytime,
                    'status' => $status,
                    'company' => $this->truncate_text((string)$record->companyname, 80),
                ];
            }
        }

        $this->debug_log('enrolment_status_rows samples', [
            'reportdate' => $reportdate,
            'samplecount' => count($samples),
            'samples' => $samples,
        ]);

        return $rows;
    }

    private function status_for_row(int $documentid, int $expirytime, int $reportdate): string {
        if ($documentid <= 0 || $expirytime <= 0) {
            return 'No document';
        }

        if ($expirytime <= $reportdate) {
            return 'Expired';
        }

        if ($expirytime <= $reportdate + (30 * DAYSECS)) {
            return 'Expiring';
        }
        return 'Active';
    }

    private function company_summary_by_label(array $filters, string $company, int $reportdate): array {
        foreach ($this->company_summaries($filters, $reportdate) as $summary) {
            if ($summary['label'] === $company) {
                return $summary;
            }
        }

        return ['total' => 0, 'compliant' => 0, 'percent' => 0.0];
    }

    private function month_windows(array $filters): array {
        $daterange = $filters['daterange'] ?? 'last12months';
        $count = $daterange === 'last6months' ? 6 : 12;
        if ($daterange === 'last90days') {
            $count = 3;
        } else if ($daterange === 'last30days') {
            $count = 1;
        }

        $months = [];
        $base = new \DateTimeImmutable('first day of this month 00:00:00', new \DateTimeZone('Asia/Almaty'));
        for ($offset = $count - 1; $offset >= 0; $offset--) {
            $start = $base->modify('-' . $offset . ' months');
            $end = $start->modify('last day of this month 23:59:59');
            $months[] = [
                'label' => userdate($end->getTimestamp(), '%b %y'),
                'end' => $end->getTimestamp(),
            ];
        }
        return $months;
    }

    private function current_report_date(): int {
        return (new \DateTimeImmutable('today 23:59:59', new \DateTimeZone('Asia/Almaty')))->getTimestamp();
    }

    private function status_item(string $label, int $count, int $total, string $status): array {
        return [
            'label' => $label,
            'value' => (string)$count,
            'percent' => round(($count / max(1, $total)) * 100, 1),
            'status' => $status,
            'meta' => round(($count / max(1, $total)) * 100, 1) . '% of checks',
        ];
    }

    private function status_for_percent(float $percent): string {
        if ($percent >= 80) {
            return 'ok';
        }
        if ($percent >= 70) {
            return 'warning';
        }
        return 'danger';
    }

    private function latest_document_join_sql(array $source, string $useralias, string $coursealias, string $alias): string {
        $table = $source['table'];
        $originfilter = $source['origin'] !== ''
            ? "AND (d2.{$source['origin']} <> 'demo_job' OR d2.{$source['origin']} IS NULL)"
            : '';
        $statusfilter = $source['status'] !== ''
            ? "AND d2.{$source['status']} IN ('completed_manual', 'completed_auto')"
            : '';

        return "LEFT JOIN {{$table}} {$alias}
                       ON {$alias}.id = (
                              SELECT MAX(d2.id)
                                FROM {{$table}} d2
                               WHERE d2.{$source['userid']} = {$useralias}.id
                                 AND d2.{$source['courseid']} = {$coursealias}.id
                                     {$originfilter}
                                     {$statusfilter}
                          )";
    }

    private function validity_days_sql(string $alias): string {
        return "COALESCE(NULLIF({$alias}.intvalue, 0), NULLIF({$alias}.decvalue, 0), NULLIF({$alias}.value, ''), 1)";
    }

    private function debug_log(string $label, array $context = []): void {
        $encoded = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            $encoded = 'context_encoding_failed';
        }

        $line = '[' . date('Y-m-d H:i:s') . '] [block_dashboardanalytics][overview_repository] ' . $label . ' ' . $encoded . PHP_EOL;
        @file_put_contents('/tmp/dashboardanalytics-overview-debug.log', $line, FILE_APPEND);
    }

    private function truncate_text(string $value, int $limit): string {
        $value = trim($value);
        if (class_exists('\core_text')) {
            if (\core_text::strlen($value) <= $limit) {
                return $value;
            }
            return \core_text::substr($value, 0, $limit - 3) . '...';
        }

        if (strlen($value) <= $limit) {
            return $value;
        }

        return substr($value, 0, $limit - 3) . '...';
    }
}
