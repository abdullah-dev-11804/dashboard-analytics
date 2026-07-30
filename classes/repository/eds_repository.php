<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\repository;

defined('MOODLE_INTERNAL') || die();

class eds_repository {

    public function count_pending_manual(array $filters): int {
        $summary = $this->queue_summary($filters);
        return $summary['count'];
    }

    public function queue_summary(array $filters): array {
        global $DB;

        if (!$this->has_tables()) {
            return [
                'count' => 0,
                'oldestdays' => 0,
                'status' => 'muted',
                'badge' => get_string('label:ok', 'block_dashboardanalytics'),
            ];
        }

        [$fromsql, $params] = $this->pending_manual_from_sql($filters);
        $record = $DB->get_record_sql(
            "SELECT COUNT(1) AS totalcount,
                    MIN(j.timecreated) AS oldestcreated
               {$fromsql}",
            $params
        );

        $count = (int)($record->totalcount ?? 0);
        $oldestcreated = (int)($record->oldestcreated ?? 0);
        $oldestdays = $count > 0 && $oldestcreated > 0
            ? max(0, (int)floor((time() - $oldestcreated) / DAYSECS))
            : 0;

        return [
            'count' => $count,
            'oldestdays' => $oldestdays,
            'status' => $count > 0 ? $this->status_key($oldestdays) : 'ok',
            'badge' => $count > 0 ? $this->status_badge($oldestdays) : get_string('label:ok', 'block_dashboardanalytics'),
        ];
    }

    public function pending_manual_rows(array $filters, int $page, int $perpage): array {
        global $DB;

        if (!$this->has_tables()) {
            return [
                'columns' => $this->columns(),
                'rows' => [],
                'totalcount' => 0,
                'notice' => 'NCASign EDS tables were not found: local_ncasign_jobs and local_ncasign_signers.',
            ];
        }

        $employee = new employee_repository();
        $userfilter = $employee->user_filter_sql($filters, 'u', 'eds');
        $company = new company_repository();
        $companysql = $company->company_name_sql('u', 'eds');

        $where = [
            $userfilter['sql'],
            "j.status = :status",
            "j.origin = :origin",
            "s.status = :signerstatus",
            "s.notifiedat IS NOT NULL",
            "s.signorder = (
                SELECT MIN(s2.signorder)
                  FROM {local_ncasign_signers} s2
                 WHERE s2.jobid = j.id
                   AND s2.status = :pendingstatus
            )",
        ];

        $params = $userfilter['params'] + [
            'status' => 'pending_manual',
            'origin' => 'course_completion',
            'signerstatus' => 'pending',
            'pendingstatus' => 'pending',
        ];

        $wheresql = implode(' AND ', $where);
        $fromsql = "FROM {local_ncasign_jobs} j
                    JOIN {local_ncasign_signers} s ON s.jobid = j.id
                    JOIN {user} u ON u.id = j.userid
                    LEFT JOIN {course} c ON c.id = j.courseid
                    {$companysql['join']}
                   WHERE {$wheresql}";

        $totalcount = (int)$DB->count_records_sql("SELECT COUNT(1) {$fromsql}", $params);
        $records = $DB->get_records_sql(
            "SELECT j.id,
                    j.courseid,
                    j.userid,
                    j.timecreated,
                    j.manualdeadline,
                    s.signerposition AS expectedsigner,
                    c.fullname AS coursename,
                    {$companysql['select']}
               {$fromsql}
           ORDER BY j.timecreated ASC",
            $params,
            $page * $perpage,
            $perpage
        );

        $rows = [];
        foreach ($records as $record) {
            $dayswaiting = max(0, (int)floor((time() - (int)$record->timecreated) / DAYSECS));
            $rows[] = [
                'cells' => [
                    ['key' => 'docid', 'value' => '#EDS-' . $record->id],
                    ['key' => 'company', 'value' => (string)$record->companyname],
                    [
                        'key' => 'course',
                        'value' => format_string((string)$record->coursename),
                        'courseurl' => !empty($record->courseid)
                            ? (new \moodle_url('/course/view.php', ['id' => (int)$record->courseid]))->out(false)
                            : '',
                    ],
                    ['key' => 'expectedsigner', 'value' => (string)$record->expectedsigner],
                    ['key' => 'dayswaiting', 'value' => (string)$dayswaiting],
                    ['key' => 'statusbadge', 'value' => $this->status_badge($dayswaiting)],
                ],
            ];
        }

        return [
            'columns' => $this->columns(),
            'rows' => $rows,
            'totalcount' => $totalcount,
            'notice' => '',
            'description' => 'Pending manual EDS signatures from NCASign, showing the current expected signer only.',
        ];
    }

    private function pending_manual_from_sql(array $filters): array {
        $employee = new employee_repository();
        $userfilter = $employee->user_filter_sql($filters, 'u', 'edsq');
        $where = [
            $userfilter['sql'],
            "j.status = :status",
            "j.origin = :origin",
            "s.status = :signerstatus",
            "s.notifiedat IS NOT NULL",
            "s.signorder = (
                SELECT MIN(s2.signorder)
                  FROM {local_ncasign_signers} s2
                 WHERE s2.jobid = j.id
                   AND s2.status = :pendingstatus
            )",
        ];

        $params = $userfilter['params'] + [
            'status' => 'pending_manual',
            'origin' => 'course_completion',
            'signerstatus' => 'pending',
            'pendingstatus' => 'pending',
        ];

        return [
            "FROM {local_ncasign_jobs} j
              JOIN {local_ncasign_signers} s ON s.jobid = j.id
              JOIN {user} u ON u.id = j.userid
             WHERE " . implode(' AND ', $where),
            $params,
        ];
    }

    private function status_key(int $dayswaiting): string {
        if ($dayswaiting < 2) {
            return 'ok';
        }

        if ($dayswaiting <= 5) {
            return 'warning';
        }

        return 'danger';
    }

    private function status_badge(int $dayswaiting): string {
        if ($dayswaiting < 2) {
            return get_string('label:ok', 'block_dashboardanalytics');
        }

        if ($dayswaiting <= 5) {
            return get_string('label:urgent', 'block_dashboardanalytics');
        }

        return get_string('label:critical', 'block_dashboardanalytics');
    }

    private function columns(): array {
        return [
            ['key' => 'docid', 'label' => get_string('label:document', 'block_dashboardanalytics')],
            ['key' => 'company', 'label' => get_string('label:company', 'block_dashboardanalytics')],
            ['key' => 'course', 'label' => get_string('label:course', 'block_dashboardanalytics')],
            ['key' => 'expectedsigner', 'label' => get_string('label:expectedsigner', 'block_dashboardanalytics')],
            ['key' => 'dayswaiting', 'label' => get_string('label:dayswaiting', 'block_dashboardanalytics')],
            ['key' => 'statusbadge', 'label' => get_string('label:status', 'block_dashboardanalytics')],
        ];
    }

    private function has_tables(): bool {
        global $CFG, $DB;

        require_once($CFG->libdir . '/xmldb/xmldb_table.php');
        return $DB->get_manager()->table_exists(new \xmldb_table('local_ncasign_jobs'))
            && $DB->get_manager()->table_exists(new \xmldb_table('local_ncasign_signers'));
    }
}
