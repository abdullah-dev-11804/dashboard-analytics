<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\repository;

use block_dashboardanalytics\permissions;

defined('MOODLE_INTERNAL') || die();

class document_repository {

    public function is_configured(): bool {
        return !empty($this->sources());
    }

    public function sources(): array {
        $sources = [];
        $primary = $this->source();
        if ($primary !== null) {
            $primary['kind'] = 'ncasign';
            $primary['null_expiry_means_active'] = false;
            $sources[] = $primary;
        }

        $legacy = $this->legacy_source();
        if ($legacy !== null) {
            $sources[] = $legacy;
        }

        return $sources;
    }

    public function status_counts(array $filters): array {
        if (!$this->is_configured()) {
            return [
                'configured' => false,
                'total' => 0,
                'active' => 0,
                'expiring' => 0,
                'expired' => 0,
                'nodocument' => 0,
            ];
        }

        $counts = [
            'active' => 0,
            'expiring' => 0,
            'expired' => 0,
            'nodocument' => 0,
        ];
        foreach ($this->overview_rows($filters) as $row) {
            if ($row['status'] === 'Active') {
                $counts['active']++;
            } else if ($row['status'] === 'Expiring') {
                $counts['expiring']++;
            } else if ($row['status'] === 'Expired') {
                $counts['expired']++;
            } else {
                $counts['nodocument']++;
            }
        }

        return [
            'configured' => true,
            'total' => (int)$counts['active'] + (int)$counts['expiring'] + (int)$counts['expired'],
            'active' => (int)$counts['active'],
            'expiring' => (int)$counts['expiring'],
            'expired' => (int)$counts['expired'],
            'nodocument' => (int)$counts['nodocument'],
        ];
    }

    public function compliance_summary(array $filters): array {
        $employee = new employee_repository();
        $totalactiveusers = $employee->count_active_users($filters);
        if (!$this->is_configured()) {
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
            'status' => $this->compliance_status($compliance, $filters),
        ];
    }

    public function count_valid_signed_users(array $filters): int {
        if (!$this->is_configured()) {
            return 0;
        }

        $userids = [];
        foreach ($this->overview_rows($filters) as $row) {
            if ($row['status'] === 'Active' || $row['status'] === 'Expiring') {
                $userids[(int)$row['userid']] = true;
            }
        }
        return count($userids);
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
        if (!$this->is_configured()) {
            return [];
        }

        $companies = [];
        foreach ($this->overview_rows($filters) as $row) {
            $companyname = trim((string)($row['company'] ?? '')) !== '' ? (string)$row['company'] : 'Unassigned';
            if (!isset($companies[$companyname])) {
                $companies[$companyname] = [
                    'companyid' => (int)($row['companyid'] ?? 0),
                    'companyname' => $companyname,
                    'expired' => 0,
                    'expiring' => 0,
                ];
            }

            if ($row['status'] === 'Expired') {
                $companies[$companyname]['expired']++;
            } else if ($row['status'] === 'Expiring') {
                $companies[$companyname]['expiring']++;
            }
        }

        $items = [];
        $max = 1;
        foreach ($companies as $record) {
            $total = (int)$record['expired'] + (int)$record['expiring'];
            $max = max($max, $total);
            $items[] = [
                'label' => (string)$record['companyname'],
                'value' => (string)$total,
                'rawtotal' => $total,
                'companyid' => (int)$record['companyid'],
                'companyname' => (string)$record['companyname'],
                'expired' => (int)$record['expired'],
                'expiring' => (int)$record['expiring'],
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
                'companyid' => (int)$item['companyid'],
                'companyname' => (string)$item['companyname'],
                'segments' => [
                    [
                        'label' => get_string('label:expired', 'block_dashboardanalytics'),
                        'value' => (string)$item['expired'],
                        'percent' => round(($item['expired'] / $max) * 100, 1),
                        'status' => 'danger',
                        'drilldownkey' => 'company_expired_documents',
                        'companyid' => (int)$item['companyid'],
                        'companyname' => (string)$item['companyname'],
                    ],
                    [
                        'label' => get_string('label:expiring', 'block_dashboardanalytics'),
                        'value' => (string)$item['expiring'],
                        'percent' => round(($item['expiring'] / $max) * 100, 1),
                        'status' => 'warning',
                        'drilldownkey' => 'company_expiring_documents',
                        'companyid' => (int)$item['companyid'],
                        'companyname' => (string)$item['companyname'],
                    ],
                ],
            ];
        }

        return $items;
    }

    public function noncompliance_by_course_items(array $filters, int $limit = 10): array {
        $overview = new overview_repository();
        $items = [];
        foreach ($this->company_tabs($filters, 8) as $tab) {
            $scopefilters = $this->heatmap_tab_filters($filters, $tab);
            $rows = $overview->enrolment_status_snapshot_rows($scopefilters);
            $courses = [];

            foreach ($rows as $row) {
                $courseid = (int)$row['courseid'];
                $coursename = (string)$row['course'];
                if (!isset($courses[$courseid])) {
                    $courses[$courseid] = [
                        'courseid' => $courseid,
                        'label' => $coursename,
                        'total' => 0,
                        'valid' => 0,
                    ];
                }

                $courses[$courseid]['total']++;
                if ($row['status'] === 'Active' || $row['status'] === 'Expiring') {
                    $courses[$courseid]['valid']++;
                }
            }

            $courseitems = [];
            foreach ($courses as $course) {
                if ($course['total'] <= 0) {
                    continue;
                }

                $percent = round(($course['valid'] / $course['total']) * 100, 1);
                $courseitems[] = [
                    'courseid' => (int)$course['courseid'],
                    'label' => (string)$course['label'],
                    'value' => $percent . '%',
                    'percent' => $percent,
                    'status' => $this->visual_status_for_percent($percent, $filters, true),
                    'meta' => get_string('meta:coursewithvaliddoc', 'block_dashboardanalytics', (object)[
                        'valid' => $course['valid'],
                        'total' => $course['total'],
                    ]),
                ];
            }

            usort($courseitems, static function(array $a, array $b): int {
                return $a['percent'] <=> $b['percent'];
            });

            foreach (array_slice($courseitems, 0, $limit) as $item) {
                $item['groupkey'] = (string)$tab['key'];
                $item['drilldownkey'] = 'company_course_noncompliance';
                $item['companyid'] = (int)($tab['companyid'] ?? 0);
                $item['companyname'] = (string)($tab['companyname'] ?? '');
                $items[] = $item;
            }
        }

        return $items;
    }

    public function company_tabs(array $filters, int $limit = 8): array {
        return $this->compliance_heatmap_tabs($filters, $limit);
    }

    public function forecast_window_items(array $filters): array {
        $label30 = get_string('forecast:window:30days', 'block_dashboardanalytics');
        $label60 = get_string('forecast:window:60days', 'block_dashboardanalytics');
        $label90 = get_string('forecast:window:90days', 'block_dashboardanalytics');
        $counts = [
            $label30 => $this->count_expiring_between($filters, 0, 30),
            $label60 => $this->count_expiring_between($filters, 31, 60),
            $label90 => $this->count_expiring_between($filters, 61, 90),
        ];
        $max = max(1, max($counts));
        $items = [];
        foreach ($counts as $label => $count) {
            $status = 'ok';
            if ($label === $label30) {
                $status = 'warning';
            } else if ($label === $label60) {
                $status = 'emerald';
            }

            $items[] = [
                'label' => $label,
                'value' => (string)$count,
                'percent' => round(($count / $max) * 100, 1),
                'status' => $status,
                'meta' => get_string('forecast:documents_expiring', 'block_dashboardanalytics'),
            ];
        }
        return $items;
    }

    public function forecast_scope_tabs(array $filters, int $limit = 8): array {
        $tabs = $this->company_tabs($filters, $limit);
        $companytabs = array_values(array_filter($tabs, static function(array $tab): bool {
            return !empty($tab['companyid']) || !empty($tab['companyname']);
        }));

        if (count($companytabs) <= 1) {
            if ($companytabs) {
                $companytabs[0]['active'] = true;
                return [$companytabs[0]];
            }

            return [[
                'key' => 'scope',
                'label' => get_string('forecast:scope:current', 'block_dashboardanalytics'),
                'active' => true,
                'companyid' => 0,
                'companyname' => '',
            ]];
        }

        foreach ($tabs as $index => $tab) {
            $tabs[$index]['active'] = $index === 0;
        }

        return $tabs;
    }

    public function forecast_stacked_items(array $filters, int $limit = 8): array {
        $items = [];
        foreach ($this->forecast_scope_tabs($filters, $limit) as $tab) {
            $scopefilters = $this->heatmap_tab_filters($filters, $tab);
            foreach ($this->forecast_period_definitions() as $periodkey => $definition) {
                $intervalitems = $this->forecast_interval_items($scopefilters, $periodkey, $definition);
                foreach ($intervalitems as $intervalitem) {
                    $intervalitem['groupkey'] = (string)$tab['key'];
                    $items[] = $intervalitem;
                }
            }
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
        $items = [];
        foreach ($this->compliance_heatmap_tabs($filters, $limit) as $tab) {
            $scopefilters = $this->heatmap_tab_filters($filters, $tab);
            foreach ($this->compliance_heatmap_group_items($scopefilters, $tab, $limit) as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    public function compliance_heatmap_tabs(array $filters, int $limit = 8): array {
        $employee = new employee_repository();
        $tabs = [];
        $iscompanyowner = permissions::is_company_owner(\context_system::instance());
        if (!$iscompanyowner && $employee->count_active_users($filters) > 0) {
            $tabs[] = [
                'key' => 'all',
                'label' => get_string('filter:allcompanieslabel', 'block_dashboardanalytics'),
                'active' => true,
                'companyid' => 0,
                'companyname' => '',
            ];
        }

        $companyrepo = new company_repository();
        $options = array_slice($companyrepo->get_company_options($filters), 0, $limit);
        foreach ($options as $index => $option) {
            $value = (string)($option['value'] ?? '');
            $label = (string)($option['label'] ?? $value);
            $companyid = ctype_digit($value) ? (int)$value : 0;
            $scopefilters = $filters;
            if ($companyid > 0) {
                $scopefilters['companyids'] = [$companyid];
                unset($scopefilters['companies']);
            } else if ($label !== '') {
                $scopefilters['companies'] = [$label];
                unset($scopefilters['companyids']);
            }
            if ($employee->count_active_users($scopefilters) <= 0) {
                continue;
            }
            $tabs[] = [
                'key' => $companyid > 0 ? 'companyid_' . $companyid : 'company_' . md5($label),
                'label' => $label,
                'active' => !$tabs,
                'companyid' => $companyid,
                'companyname' => $companyid > 0 ? '' : $label,
            ];
        }

        return $tabs;
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
        if (!$this->is_configured()) {
            return [];
        }

        $groups = [];
        foreach ($this->overview_rows($filters) as $row) {
            $label = $this->row_dimension_label($row, $dimension);
            if ($label === '') {
                $label = 'Unassigned';
            }
            if (!isset($groups[$label])) {
                $groups[$label] = (object)[
                    'label' => $label,
                    'expired' => 0,
                    'expiring' => 0,
                ];
            }
            if ($row['status'] === 'Expired') {
                $groups[$label]->expired++;
            } else if ($row['status'] === 'Expiring') {
                $groups[$label]->expiring++;
            }
        }

        return $this->grouped_count_items(array_values($groups), $limit);
    }

    public function certification_status_stacked_items(array $filters, string $dimension, int $limit = 10): array {
        if (!$this->is_configured()) {
            return [];
        }

        $groups = [];
        foreach ($this->overview_rows($filters) as $row) {
            $label = $this->row_dimension_label($row, $dimension);
            if ($label === '') {
                $label = 'Unassigned';
            }
            if (!isset($groups[$label])) {
                $groups[$label] = (object)[
                    'label' => $label,
                    'active' => 0,
                    'expiring' => 0,
                    'expired' => 0,
                ];
            }
            if ($row['status'] === 'Active') {
                $groups[$label]->active++;
            } else if ($row['status'] === 'Expiring') {
                $groups[$label]->expiring++;
            } else if ($row['status'] === 'Expired') {
                $groups[$label]->expired++;
            }
        }

        $records = array_slice(array_values($groups), 0, $limit);
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
        if (!$this->is_configured()) {
            return [
                'columns' => $this->columns(),
                'rows' => [],
                'totalcount' => 0,
                'notice' => get_string('settings:documentheading_desc', 'block_dashboardanalytics'),
            ];
        }

        $groups = $this->document_matrix_groups($filters, $status);
        $totalcount = count($groups);
        $groups = array_slice($groups, $page * $perpage, $perpage);
        $rows = $this->document_matrix_rows($groups, $showidentity);

        return [
            'columns' => $this->columns(),
            'rows' => $rows,
            'totalcount' => $totalcount,
            'notice' => '',
            'exporturl' => '',
        ];
    }

    public function document_export_rows(array $filters, string $status, bool $showidentity): array {
        $records = $this->filtered_document_records($filters, $status);
        $rows = [];

        foreach ($records as $record) {
            [$expirytext, $daystext] = $this->document_date_cells($record);
            $rows[] = [
                'employee' => $showidentity ? (string)$record['employee'] : get_string('hiddenuser'),
                'position' => (string)($record['position'] ?? ''),
                'company' => (string)($record['company'] ?? ''),
                'region' => (string)($record['location'] ?? ''),
                'department' => (string)($record['department'] ?? ''),
                'site' => (string)($record['site'] ?? ''),
                'course' => (string)($record['course'] ?? ''),
                'expiry' => $expirytext,
                'days' => $daystext,
                'status' => $this->status_display((string)$record['status']),
            ];
        }

        return [
            'columns' => [
                'employee' => get_string('label:employee', 'block_dashboardanalytics'),
                'position' => get_string('label:position', 'block_dashboardanalytics'),
                'company' => get_string('label:company', 'block_dashboardanalytics'),
                'region' => get_string('label:location', 'block_dashboardanalytics'),
                'department' => get_string('label:department', 'block_dashboardanalytics'),
                'site' => get_string('label:site', 'block_dashboardanalytics'),
                'course' => get_string('label:course', 'block_dashboardanalytics'),
                'expiry' => get_string('label:expirydate', 'block_dashboardanalytics'),
                'days' => get_string('label:daysremaining', 'block_dashboardanalytics'),
                'status' => get_string('label:status', 'block_dashboardanalytics'),
            ],
            'rows' => $rows,
        ];
    }

    public function document_table_export_rows(
        array $filters,
        string $status,
        bool $showidentity,
        ?int $page = null,
        ?int $perpage = null
    ): array {
        $groups = $this->document_matrix_groups($filters, $status);
        if ($page !== null && $perpage !== null && $perpage > 0) {
            $groups = array_slice($groups, max(0, $page) * $perpage, $perpage);
        }

        $rows = [];
        foreach ($groups as $group) {
            foreach ($group['courses'] as $record) {
                [$expirytext, $daystext] = $this->document_date_cells($record);
                $rows[] = [
                    'employee' => $showidentity ? (string)$group['employee'] : get_string('hiddenuser'),
                    'position' => (string)$group['position'],
                    'company' => (string)$group['company'],
                    'location' => (string)$group['region'],
                    'department' => (string)$group['department'],
                    'site' => (string)$group['site'],
                    'course' => (string)($record['course'] ?? ''),
                    'expiry' => $expirytext,
                    'days' => $daystext,
                    'status' => $this->status_display((string)($record['status'] ?? '')),
                ];
            }
        }

        return [
            'columns' => [
                'employee' => get_string('label:employee', 'block_dashboardanalytics'),
                'position' => get_string('label:position', 'block_dashboardanalytics'),
                'company' => get_string('label:company', 'block_dashboardanalytics'),
                'location' => get_string('label:location', 'block_dashboardanalytics'),
                'department' => get_string('label:department', 'block_dashboardanalytics'),
                'site' => get_string('label:site', 'block_dashboardanalytics'),
                'course' => get_string('label:course', 'block_dashboardanalytics'),
                'expiry' => get_string('label:expirydate', 'block_dashboardanalytics'),
                'days' => get_string('label:daysremaining', 'block_dashboardanalytics'),
                'status' => get_string('label:status', 'block_dashboardanalytics'),
            ],
            'rows' => $rows,
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

    public function legacy_source(): ?array {
        global $CFG, $DB;

        require_once($CFG->libdir . '/xmldb/xmldb_table.php');
        $manager = $DB->get_manager();
        $required = ['sental_modeb_doc', 'sental_modeb_doc_user'];
        foreach ($required as $tablename) {
            if (!$manager->table_exists(new \xmldb_table($tablename))) {
                return null;
            }
        }

        return [
            'kind' => 'legacy_type1',
            'table' => 'sental_modeb_doc',
            'usertable' => 'sental_modeb_doc_user',
            'versiontable' => $manager->table_exists(new \xmldb_table('sental_modeb_doc_version')) ? 'sental_modeb_doc_version' : '',
            'courseid' => 'courseid',
            'userid' => 'userid',
            'documentid' => 'id',
            'documenttype' => 'documenttype',
            'currentversion' => 'currentversion',
            'null_expiry_means_active' => true,
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

    private function forecast_period_definitions(): array {
        return [
            '30days' => [
                'interval' => 'week',
                'count' => 5,
                'days' => 30,
                'label' => get_string('forecast:period:30days', 'block_dashboardanalytics'),
            ],
            '60days' => [
                'interval' => 'week',
                'count' => 9,
                'days' => 60,
                'label' => get_string('forecast:period:60days', 'block_dashboardanalytics'),
            ],
            '90days' => [
                'interval' => 'week',
                'count' => 13,
                'days' => 90,
                'label' => get_string('forecast:period:90days', 'block_dashboardanalytics'),
            ],
            '6months' => [
                'interval' => 'month',
                'count' => 6,
                'label' => get_string('forecast:period:6months', 'block_dashboardanalytics'),
            ],
            '12months' => [
                'interval' => 'month',
                'count' => 12,
                'label' => get_string('forecast:period:12months', 'block_dashboardanalytics'),
            ],
            '3years' => [
                'interval' => 'year',
                'count' => 3,
                'label' => get_string('forecast:period:3years', 'block_dashboardanalytics'),
            ],
        ];
    }

    private function forecast_interval_items(array $filters, string $periodkey, array $definition): array {
        $intervals = $this->forecast_intervals($definition);
        $rows = $this->forecast_candidate_rows($filters);
        $groupmax = 1;
        $items = [];

        foreach ($intervals as $interval) {
            $courses = [];
            $total = 0;
            foreach ($rows as $row) {
                $expiry = (int)($row['expirytime'] ?? 0);
                if ($expiry <= 0 || $expiry < $interval['fromts'] || $expiry > $interval['tots']) {
                    continue;
                }

                $courseid = (int)($row['courseid'] ?? 0);
                if (!isset($courses[$courseid])) {
                    $courses[$courseid] = [
                        'courseid' => $courseid,
                        'label' => (string)($row['course'] ?? ''),
                        'count' => 0,
                    ];
                }

                $courses[$courseid]['count']++;
                $total++;
            }

            uasort($courses, static function(array $a, array $b): int {
                if ((int)$a['count'] !== (int)$b['count']) {
                    return (int)$b['count'] <=> (int)$a['count'];
                }

                return \core_text::strtolower((string)$a['label']) <=> \core_text::strtolower((string)$b['label']);
            });

            $segments = [];
            $courseindex = 0;
            foreach ($courses as $course) {
                $colour = $this->forecast_course_colour($courseindex);
                $segments[] = [
                    'label' => (string)$course['label'],
                    'value' => (string)$course['count'],
                    'percent' => $total > 0 ? round((((int)$course['count']) / $total) * 100, 1) : 0.0,
                    'status' => 'info',
                    'courseid' => (int)$course['courseid'],
                    'colour' => $colour,
                    'fromts' => (int)$interval['fromts'],
                    'tots' => (int)$interval['tots'],
                    'drilldownkey' => 'company_forecast_documents',
                ];
                $courseindex++;
            }

            $items[] = [
                'label' => (string)$interval['label'],
                'value' => (string)$total,
                'percent' => 0.0,
                'status' => $total > 0 ? 'info' : 'muted',
                'meta' => (string)$interval['meta'],
                'periodkey' => $periodkey,
                'rowlabel' => (string)($definition['label'] ?? $periodkey),
                'fromts' => (int)$interval['fromts'],
                'tots' => (int)$interval['tots'],
                'segments' => $segments,
            ];
            $groupmax = max($groupmax, $total);
        }

        foreach ($items as $index => $item) {
            $items[$index]['percent'] = $groupmax > 0
                ? round((((int)$item['value']) / $groupmax) * 100, 1)
                : 0.0;
        }

        return $items;
    }

    private function forecast_intervals(array $definition): array {
        $timezone = new \DateTimeZone('Asia/Almaty');
        $today = new \DateTimeImmutable('today', $timezone);
        $intervals = [];

        if (($definition['interval'] ?? '') === 'week') {
            $days = max(1, (int)($definition['days'] ?? 0));
            $count = max(1, (int)($definition['count'] ?? 1));
            for ($index = 0; $index < $count; $index++) {
                $start = $today->modify('+' . ($index * 7) . ' days');
                $tentativeend = $start->modify('+6 days');
                $absoluteend = $today->modify('+' . ($days - 1) . ' days');
                $end = $tentativeend < $absoluteend ? $tentativeend : $absoluteend;
                $intervals[] = [
                    'label' => userdate($start->getTimestamp(), '%d %b'),
                    'meta' => userdate($start->getTimestamp(), '%d %b %Y') . ' - ' . userdate($end->getTimestamp(), '%d %b %Y'),
                    'fromts' => $start->setTime(0, 0, 0)->getTimestamp(),
                    'tots' => $end->setTime(23, 59, 59)->getTimestamp(),
                ];
            }
            return $intervals;
        }

        if (($definition['interval'] ?? '') === 'month') {
            $count = max(1, (int)($definition['count'] ?? 1));
            $cursor = $today->modify('first day of this month');
            $labelformat = $count > 12 ? '%b %y' : '%b';
            for ($index = 0; $index < $count; $index++) {
                $start = $cursor->modify('+' . $index . ' months');
                $end = $start->modify('last day of this month');
                $intervals[] = [
                    'label' => userdate($start->getTimestamp(), $labelformat),
                    'meta' => userdate($start->getTimestamp(), '%B %Y'),
                    'fromts' => $start->setTime(0, 0, 0)->getTimestamp(),
                    'tots' => $end->setTime(23, 59, 59)->getTimestamp(),
                ];
            }
            return $intervals;
        }

        $count = max(1, (int)($definition['count'] ?? 1));
        $cursor = $today->setDate((int)$today->format('Y'), 1, 1);
        for ($index = 0; $index < $count; $index++) {
            $start = $cursor->modify('+' . $index . ' years');
            $end = $start->modify('last day of December this year');
            $intervals[] = [
                'label' => $start->format('Y'),
                'meta' => $start->format('Y'),
                'fromts' => $start->setTime(0, 0, 0)->getTimestamp(),
                'tots' => $end->setTime(23, 59, 59)->getTimestamp(),
            ];
        }

        return $intervals;
    }

    private function forecast_candidate_rows(array $filters): array {
        $now = time();

        return array_values(array_filter($this->overview_rows($filters), static function(array $row) use ($now): bool {
            $expiry = (int)($row['expirytime'] ?? 0);
            return $expiry > $now;
        }));
    }

    private function forecast_course_colour(int $index): string {
        $palette = [
            '#0d8f61',
            '#f59e0b',
            '#ef4444',
            '#3b82f6',
            '#8b5cf6',
            '#14b8a6',
            '#f97316',
            '#84cc16',
            '#ec4899',
            '#06b6d4',
        ];

        return $palette[$index % count($palette)];
    }

    private function count_expiring_between(array $filters, int $startdays, int $enddays): int {
        if (!$this->is_configured()) {
            return 0;
        }

        $start = time() + ($startdays * DAYSECS);
        $end = time() + ($enddays * DAYSECS);
        $count = 0;
        foreach ($this->overview_rows($filters) as $row) {
            $expiry = !empty($row['expirytime']) ? (int)$row['expirytime'] : 0;
            if ($expiry >= $start && $expiry <= $end) {
                $count++;
            }
        }

        return $count;
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

    private function overview_rows(array $filters): array {
        return (new overview_repository())->enrolment_status_snapshot_rows($filters);
    }

    private function filtered_document_records(array $filters, string $status): array {
        $records = $this->overview_rows($filters);
        if ($status === 'expired') {
            $records = array_values(array_filter($records, static function(array $row): bool {
                return $row['status'] === 'Expired';
            }));
        } else if ($status === 'expiring') {
            $records = array_values(array_filter($records, static function(array $row): bool {
                return $row['status'] === 'Expiring';
            }));
        } else if ($status === 'active') {
            $records = array_values(array_filter($records, static function(array $row): bool {
                return $row['status'] === 'Active';
            }));
        } else if ($status === 'noncompliant') {
            $records = array_values(array_filter($records, static function(array $row): bool {
                return $row['status'] === 'Expired' || $row['status'] === 'No document';
            }));
        } else if ($status === 'nodocument') {
            $records = array_values(array_filter($records, static function(array $row): bool {
                return $row['status'] === 'No document';
            }));
        }

        $expirystart = (int)($filters['expirystartts'] ?? 0);
        $expiryend = (int)($filters['expiryendts'] ?? 0);
        if ($expirystart > 0 || $expiryend > 0) {
            $records = array_values(array_filter($records, static function(array $row) use ($expirystart, $expiryend): bool {
                $expiry = (int)($row['expirytime'] ?? 0);
                if ($expiry <= 0) {
                    return false;
                }
                if ($expirystart > 0 && $expiry < $expirystart) {
                    return false;
                }
                if ($expiryend > 0 && $expiry > $expiryend) {
                    return false;
                }
                return true;
            }));
        }

        usort($records, static function(array $a, array $b): int {
            $astatus = self::matrix_status_weight((string)($a['status'] ?? ''));
            $bstatus = self::matrix_status_weight((string)($b['status'] ?? ''));
            if ($astatus !== $bstatus) {
                return $astatus <=> $bstatus;
            }

            $aexpiry = !empty($a['expirytime']) ? (int)$a['expirytime'] : PHP_INT_MAX;
            $bexpiry = !empty($b['expirytime']) ? (int)$b['expirytime'] : PHP_INT_MAX;
            if ($aexpiry !== $bexpiry) {
                return $aexpiry <=> $bexpiry;
            }

            $aname = mb_strtolower(trim((string)($a['employee'] ?? '')));
            $bname = mb_strtolower(trim((string)($b['employee'] ?? '')));
            if ($aname !== $bname) {
                return $aname <=> $bname;
            }

            return ((int)($a['courseid'] ?? 0)) <=> ((int)($b['courseid'] ?? 0));
        });

        return $records;
    }

    private function document_matrix_groups(array $filters, string $status): array {
        $records = $this->filtered_document_records($filters, $status);
        $groups = [];

        foreach ($records as $record) {
            $groupid = 'u' . (int)$record['userid'];
            if (!isset($groups[$groupid])) {
                $groups[$groupid] = [
                    'groupid' => $groupid,
                    'userid' => (int)$record['userid'],
                    'employee' => (string)$record['employee'],
                    'position' => (string)($record['position'] ?? ''),
                    'company' => (string)($record['company'] ?? ''),
                    'region' => (string)($record['location'] ?? ''),
                    'department' => (string)($record['department'] ?? ''),
                    'site' => (string)($record['site'] ?? ''),
                    'courses' => [],
                    'summarysort' => self::matrix_status_weight((string)($record['status'] ?? '')),
                    'minexpiry' => !empty($record['expirytime']) ? (int)$record['expirytime'] : PHP_INT_MAX,
                ];
            }

            $groups[$groupid]['courses'][] = $record;
            $groups[$groupid]['summarysort'] = min(
                (int)$groups[$groupid]['summarysort'],
                self::matrix_status_weight((string)($record['status'] ?? ''))
            );
            $groups[$groupid]['minexpiry'] = min(
                (int)$groups[$groupid]['minexpiry'],
                !empty($record['expirytime']) ? (int)$record['expirytime'] : PHP_INT_MAX
            );
        }

        foreach ($groups as $groupid => $group) {
            usort($group['courses'], static function(array $a, array $b): int {
                $astatus = self::matrix_status_weight((string)($a['status'] ?? ''));
                $bstatus = self::matrix_status_weight((string)($b['status'] ?? ''));
                if ($astatus !== $bstatus) {
                    return $astatus <=> $bstatus;
                }

                $aexpiry = !empty($a['expirytime']) ? (int)$a['expirytime'] : PHP_INT_MAX;
                $bexpiry = !empty($b['expirytime']) ? (int)$b['expirytime'] : PHP_INT_MAX;
                if ($aexpiry !== $bexpiry) {
                    return $aexpiry <=> $bexpiry;
                }

                return mb_strtolower((string)($a['course'] ?? '')) <=> mb_strtolower((string)($b['course'] ?? ''));
            });
            $groups[$groupid] = $group;
        }

        $groups = array_values($groups);
        usort($groups, static function(array $a, array $b): int {
            if ($a['summarysort'] !== $b['summarysort']) {
                return $a['summarysort'] <=> $b['summarysort'];
            }
            if ($a['minexpiry'] !== $b['minexpiry']) {
                return $a['minexpiry'] <=> $b['minexpiry'];
            }
            return mb_strtolower((string)$a['employee']) <=> mb_strtolower((string)$b['employee']);
        });

        return $groups;
    }

    private function document_matrix_rows(array $groups, bool $showidentity): array {
        $rows = [];
        foreach ($groups as $group) {
            $coursecount = count($group['courses']);
            $summaryrecord = $coursecount === 1 ? $group['courses'][0] : null;
            $summarystatus = $this->matrix_user_status($group['courses']);
            [$summaryexpiry, $summarydays] = $summaryrecord ? $this->document_date_cells($summaryrecord) : ['', ''];
            $summarycourse = $coursecount === 1
                ? (string)($group['courses'][0]['course'] ?? '')
                : get_string('label:coursecount', 'block_dashboardanalytics', $coursecount);
            $rows[] = [
                'rowtype' => 'summary',
                'groupid' => (string)$group['groupid'],
                'expanded' => false,
                'cells' => [
                    [
                        'key' => 'employee',
                        'value' => $showidentity ? (string)$group['employee'] : get_string('hiddenuser'),
                        'profileurl' => $showidentity ? (new \moodle_url('/user/profile.php', ['id' => (int)$group['userid']]))->out(false) : '',
                    ],
                    ['key' => 'position', 'value' => (string)$group['position']],
                    ['key' => 'company', 'value' => (string)$group['company']],
                    ['key' => 'location', 'value' => (string)$group['region']],
                    ['key' => 'department', 'value' => (string)$group['department']],
                    ['key' => 'site', 'value' => (string)$group['site']],
                    [
                        'key' => 'course',
                        'value' => $summarycourse,
                        'coursecount' => $coursecount,
                        'togglelabel' => get_string('label:expandcourses', 'block_dashboardanalytics'),
                        'courseurl' => ($coursecount === 1 && !empty($summaryrecord['courseid']))
                            ? (new \moodle_url('/course/view.php', ['id' => (int)$summaryrecord['courseid']]))->out(false)
                            : '',
                    ],
                    ['key' => 'expiry', 'value' => $summaryexpiry],
                    ['key' => 'days', 'value' => $summarydays],
                    [
                        'key' => 'status',
                        'value' => $this->status_display($summarystatus),
                        'statuskey' => $this->status_badge_key($summarystatus),
                    ],
                ],
            ];

            if ($coursecount <= 1) {
                continue;
            }

            foreach ($group['courses'] as $record) {
                [$expirytext, $daystext] = $this->document_date_cells($record);
                $statuskey = (string)$record['status'];
                $rows[] = [
                    'rowtype' => 'course',
                    'groupid' => (string)$group['groupid'],
                    'expanded' => false,
                    'cells' => [
                        ['key' => 'employee', 'value' => ''],
                        ['key' => 'position', 'value' => ''],
                        ['key' => 'company', 'value' => ''],
                        ['key' => 'location', 'value' => ''],
                        ['key' => 'department', 'value' => ''],
                        ['key' => 'site', 'value' => ''],
                        [
                            'key' => 'course',
                            'value' => (string)$record['course'],
                            'courseurl' => !empty($record['courseid'])
                                ? (new \moodle_url('/course/view.php', ['id' => (int)$record['courseid']]))->out(false)
                                : '',
                        ],
                        ['key' => 'expiry', 'value' => $expirytext],
                        ['key' => 'days', 'value' => $daystext],
                        [
                            'key' => 'status',
                            'value' => $this->status_display($statuskey),
                            'statuskey' => $this->status_badge_key($statuskey),
                        ],
                    ],
                ];
            }
        }

        return $rows;
    }

    private function matrix_user_status(array $courses): string {
        $hasactive = false;
        $hasnodocument = false;

        foreach ($courses as $course) {
            $status = (string)($course['status'] ?? '');
            if ($status === 'Expired') {
                return 'Expired';
            }
            if ($status === 'Expiring') {
                return 'Expiring';
            }
            if ($status === 'Active') {
                $hasactive = true;
                continue;
            }
            $hasnodocument = true;
        }

        if ($hasnodocument) {
            return 'No document';
        }

        if ($hasactive) {
            return 'Active';
        }

        return 'No document';
    }

    private function document_date_cells(array $record): array {
        $expiry = !empty($record['expirytime']) ? (int)$record['expirytime'] : null;
        if ($expiry === null || $expiry <= 0) {
            return ['', ''];
        }

        $days = (int)floor(($expiry - time()) / DAYSECS);
        return [
            userdate($expiry, get_string('strftimedate')),
            (string)$days,
        ];
    }

    private static function matrix_status_weight(string $status): int {
        if ($status === 'Expired') {
            return 1;
        }
        if ($status === 'Expiring') {
            return 2;
        }
        if ($status === 'No document') {
            return 3;
        }
        if ($status === 'Active') {
            return 4;
        }
        return 5;
    }

    private function status_display(string $status): string {
        if ($status === 'Active') {
            return get_string('label:active', 'block_dashboardanalytics');
        }
        if ($status === 'Expiring') {
            return get_string('label:expiring', 'block_dashboardanalytics');
        }
        if ($status === 'Expired') {
            return get_string('label:expired', 'block_dashboardanalytics');
        }
        return get_string('label:nodocument', 'block_dashboardanalytics');
    }

    private function status_badge_key(string $status): string {
        if ($status === 'Active') {
            return 'active';
        }
        if ($status === 'Expiring') {
            return 'expiring';
        }
        if ($status === 'Expired') {
            return 'expired';
        }
        return 'nodocument';
    }

    private function row_dimension_label(array $row, string $dimension): string {
        if ($dimension === 'location') {
            return trim((string)($row['location'] ?? ''));
        }

        if ($dimension === 'course') {
            return trim((string)($row['course'] ?? ''));
        }

        if ($dimension === 'company') {
            return trim((string)($row['company'] ?? ''));
        }

        return trim((string)($row['department'] ?? ''));
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

    private function compliance_status(float $compliance, array $filters): string {
        $thresholds = \block_dashboardanalytics\filters::compliance_thresholds($filters);
        if ($compliance >= $thresholds['compliant']) {
            return get_string('label:green', 'block_dashboardanalytics');
        }

        if ($compliance >= $thresholds['critical']) {
            return get_string('label:amber', 'block_dashboardanalytics');
        }

        return get_string('label:red', 'block_dashboardanalytics');
    }

    private function has_completion_sql(string $alias): string {
        return "{$alias}.timecompleted IS NOT NULL AND {$alias}.timecompleted > 0";
    }

    private function compliance_heatmap_group_items(array $filters, array $tab, int $limit): array {
        $employee = new employee_repository();
        $personnelcategories = array_values(array_filter(
            array_slice($employee->active_users_by_dimension_items($filters, 'personnelcategory', $limit), 0, $limit),
            static function(array $item): bool {
                return trim((string)($item['label'] ?? '')) !== '';
            }
        ));
        $sites = array_values(array_filter(
            array_slice($employee->active_users_by_dimension_items($filters, 'site', $limit), 0, $limit),
            static function(array $item): bool {
                return trim((string)($item['label'] ?? '')) !== '';
            }
        ));

        if (!$personnelcategories || !$sites) {
            return [];
        }

        $items = [];
        foreach ($personnelcategories as $rowindex => $personnelcategoryitem) {
            $personnelcategory = (string)$personnelcategoryitem['label'];
            foreach ($sites as $columnindex => $siteitem) {
                $site = (string)$siteitem['label'];
                $cellfilters = $filters;
                $cellfilters['personnelcategories'] = [$personnelcategory];
                $cellfilters['sites'] = [$site];
                $summary = $this->compliance_summary($cellfilters);
                $hasstaff = (int)$summary['totalactiveusers'] > 0;
                $compliance = $hasstaff ? round((float)$summary['compliance'], 1) : 0.0;
                $items[] = [
                    'label' => $personnelcategory . ' / ' . $site,
                    'value' => $hasstaff ? $compliance . '%' : '—',
                    'percent' => $compliance,
                    'status' => $this->visual_status_for_percent($compliance, $filters, $hasstaff),
                    'meta' => $hasstaff
                        ? get_string('meta:fullycompliantemployees', 'block_dashboardanalytics', (object)[
                            'compliant' => $summary['validusers'],
                            'total' => $summary['totalactiveusers'],
                        ])
                        : get_string('kpi:value:nostaff', 'block_dashboardanalytics'),
                    'groupkey' => (string)$tab['key'],
                    'rowlabel' => $personnelcategory,
                    'columnlabel' => $site,
                    'drilldownkey' => 'company_compliance',
                    'companyid' => (int)($tab['companyid'] ?? 0),
                    'companyname' => (string)($tab['companyname'] ?? ''),
                ];
            }
        }

        return $items;
    }

    private function heatmap_tab_filters(array $filters, array $tab): array {
        if (!empty($tab['companyid'])) {
            $filters['companyids'] = [(int)$tab['companyid']];
            unset($filters['companies']);
            return $filters;
        }

        if (!empty($tab['companyname'])) {
            $filters['companies'] = [(string)$tab['companyname']];
            unset($filters['companyids']);
        }

        return $filters;
    }

    private function visual_status_for_percent(float $percent, array $filters, bool $hasstaff = true): string {
        if (!$hasstaff) {
            return 'muted';
        }
        $thresholds = \block_dashboardanalytics\filters::compliance_thresholds($filters);
        if ($percent >= $thresholds['compliant']) {
            return 'ok';
        }
        if ($percent >= $thresholds['critical']) {
            return 'warning';
        }
        return 'danger';
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
            ['key' => 'position', 'label' => get_string('label:position', 'block_dashboardanalytics')],
            ['key' => 'company', 'label' => get_string('label:company', 'block_dashboardanalytics')],
            ['key' => 'location', 'label' => get_string('label:location', 'block_dashboardanalytics')],
            ['key' => 'department', 'label' => get_string('label:department', 'block_dashboardanalytics')],
            ['key' => 'site', 'label' => get_string('label:site', 'block_dashboardanalytics')],
            ['key' => 'course', 'label' => get_string('label:course', 'block_dashboardanalytics')],
            ['key' => 'expiry', 'label' => get_string('label:expirydate', 'block_dashboardanalytics')],
            ['key' => 'days', 'label' => get_string('label:daysremaining', 'block_dashboardanalytics')],
            ['key' => 'status', 'label' => get_string('label:status', 'block_dashboardanalytics')],
        ];
    }
}
