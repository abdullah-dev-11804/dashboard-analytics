<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\repository;

defined('MOODLE_INTERNAL') || die();
ini_set('log_errors', '1');
ini_set('error_log', '/tmp/ncasign-debug.log');
class overview_repository {

    public function enrolment_status_snapshot_rows(array $filters, ?int $reportdate = null): array {
        $reportdate = $reportdate ?? $this->current_report_date();
        return $this->enrolment_status_rows($filters, $reportdate);
    }

    public function compliance_gap_rows(array $filters, array $allowedstatuses, int $page, int $perpage, bool $showidentity): array {
        $rows = $this->enrolment_status_rows($filters, $this->current_report_date());
        $allowedstatuses = array_values(array_unique(array_map('strval', $allowedstatuses)));

        $rows = array_values(array_filter($rows, static function(array $row) use ($allowedstatuses): bool {
            return in_array((string)$row['status'], $allowedstatuses, true);
        }));

        usort($rows, static function(array $a, array $b): int {
            $aprimary = $a['status'] === 'Expired' ? 0 : 1;
            $bprimary = $b['status'] === 'Expired' ? 0 : 1;
            if ($aprimary !== $bprimary) {
                return $aprimary <=> $bprimary;
            }

            $aexpiry = !empty($a['expirytime']) ? (int)$a['expirytime'] : PHP_INT_MAX;
            $bexpiry = !empty($b['expirytime']) ? (int)$b['expirytime'] : PHP_INT_MAX;
            return $aexpiry <=> $bexpiry;
        });

        $totalcount = count($rows);
        $rows = array_slice($rows, $page * $perpage, $perpage);
        $tablerows = [];
        foreach ($rows as $row) {
            $expirytime = !empty($row['expirytime']) ? (int)$row['expirytime'] : null;
            $days = $expirytime !== null ? (int)floor(($expirytime - time()) / DAYSECS) : null;
            $tablerows[] = [
                'cells' => [
                    [
                        'key' => 'employee',
                        'value' => $showidentity ? (string)$row['employee'] : get_string('hiddenuser'),
                        'profileurl' => $showidentity ? (new \moodle_url('/user/profile.php', ['id' => (int)$row['userid']]))->out(false) : '',
                    ],
                    ['key' => 'company', 'value' => (string)$row['company']],
                    ['key' => 'department', 'value' => (string)$row['department']],
                    ['key' => 'location', 'value' => (string)$row['location']],
                    ['key' => 'position', 'value' => (string)$row['position']],
                    ['key' => 'course', 'value' => (string)$row['course']],
                    ['key' => 'expiry', 'value' => $expirytime !== null ? userdate($expirytime, get_string('strftimedate')) : '-'],
                    ['key' => 'days', 'value' => $days !== null ? (string)$days : '-'],
                    ['key' => 'status', 'value' => (string)$row['status']],
                ],
            ];
        }

        return [
            'columns' => [
                ['key' => 'employee', 'label' => get_string('label:employee', 'block_dashboardanalytics')],
                ['key' => 'company', 'label' => get_string('label:company', 'block_dashboardanalytics')],
                ['key' => 'department', 'label' => get_string('label:department', 'block_dashboardanalytics')],
                ['key' => 'location', 'label' => get_string('label:location', 'block_dashboardanalytics')],
                ['key' => 'position', 'label' => get_string('label:position', 'block_dashboardanalytics')],
                ['key' => 'course', 'label' => get_string('label:course', 'block_dashboardanalytics')],
                ['key' => 'expiry', 'label' => get_string('label:expirydate', 'block_dashboardanalytics')],
                ['key' => 'days', 'label' => get_string('label:daysremaining', 'block_dashboardanalytics')],
                ['key' => 'status', 'label' => get_string('label:status', 'block_dashboardanalytics')],
            ],
            'rows' => $tablerows,
            'totalcount' => $totalcount,
            'notice' => '',
            'description' => '',
        ];
    }

    public function overall_employee_compliance_summary(array $filters, ?int $reportdate = null): array {
        $reportdate = $reportdate ?? $this->current_report_date();
        return $this->compliance_rollup_from_rows($this->enrolment_status_rows($filters, $reportdate));
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
        $seriesstatuses = ['danger', 'warning', 'ok', 'info', 'muted'];
        $monthlabels = array_column($months, 'label');
        $monthkeys = array_column($months, 'key');
        $monthsummarymap = [];

        foreach ($months as $month) {
            $summaries = $this->company_summaries($filters, $month['end']);
            foreach ($summaries as $summary) {
                $monthsummarymap[$month['key']][$summary['label']] = $summary;
            }
        }

        $items = [];
        foreach ($companies as $index => $company) {
            $currentitem = null;
            foreach ($current as $candidate) {
                if ($candidate['label'] === $company) {
                    $currentitem = $candidate;
                    break;
                }
            }

            $segments = [];
            foreach ($monthkeys as $monthindex => $monthkey) {
                $summary = $monthsummarymap[$monthkey][$company] ?? ['percent' => 0.0, 'total' => 0, 'compliant' => 0];
                $segments[] = [
                    'label' => $monthlabels[$monthindex],
                    'value' => $summary['total'] > 0 ? round((float)$summary['percent'], 1) . '%' : '0%',
                    'percent' => (float)$summary['percent'],
                    'status' => $seriesstatuses[$index % count($seriesstatuses)],
                ];
            }

            $items[] = [
                'label' => $company,
                'value' => $currentitem ? (string)$currentitem['value'] : '0%',
                'percent' => $currentitem ? (float)$currentitem['percent'] : 0.0,
                'status' => $seriesstatuses[$index % count($seriesstatuses)],
                'meta' => get_string('panel:compliancetrendchart:meta', 'block_dashboardanalytics'),
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
                'value' => $summary['total'] > 0 ? $summary['percent'] . '%' : get_string('kpi:value:nostaff', 'block_dashboardanalytics'),
                'percent' => (float)$summary['percent'],
                'status' => $summary['total'] > 0 ? $this->status_for_percent((float)$summary['percent']) : 'muted',
                'meta' => get_string('meta:fullycompliantemployees', 'block_dashboardanalytics', (object)[
                    'compliant' => $summary['compliant'],
                    'total' => $summary['total'],
                ]),
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
            $this->status_item(get_string('label:active', 'block_dashboardanalytics'), $counts['Active'], $total, 'ok'),
            $this->status_item(get_string('label:expiring', 'block_dashboardanalytics'), $counts['Expiring'], $total, 'warning'),
            $this->status_item(get_string('label:expired', 'block_dashboardanalytics'), $counts['Expired'], $total, 'danger'),
            $this->status_item(get_string('label:nodocument', 'block_dashboardanalytics'), $counts['No document'], $total, 'muted'),
        ];
    }

    public function expired_expiring_by_company_items(array $filters, int $limit = 10): array {
        $rows = $this->enrolment_status_rows($filters, $this->current_report_date());
        $companies = [];
        foreach ($rows as $row) {
            $company = $row['company'] ?: get_string('label:unassigned', 'block_dashboardanalytics');
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

    public function overview_summary_items(array $filters): array {
        $employees = new employee_repository();
        $totalusers = $employees->count_active_users($filters);
        $uptime = $this->uptime_summary();
        $averagecompliance = $this->average_company_compliance_summary($filters);
        $activecompanies = $this->active_company_summary($filters);

        return [
            [
                'label' => get_string('overview:totalusers', 'block_dashboardanalytics'),
                'value' => (string)$totalusers,
                'percent' => 0.0,
                'status' => 'info',
                'meta' => get_string('overview:totalusersmeta', 'block_dashboardanalytics'),
                'segments' => [],
            ],
            [
                'label' => get_string('overview:platformuptime', 'block_dashboardanalytics'),
                'value' => $uptime['value'],
                'percent' => $uptime['percent'],
                'status' => $uptime['status'],
                'meta' => $uptime['meta'],
                'segments' => [],
            ],
            [
                'label' => get_string('overview:avgcompliance', 'block_dashboardanalytics'),
                'value' => $averagecompliance['value'],
                'percent' => $averagecompliance['percent'],
                'status' => $averagecompliance['status'],
                'meta' => $averagecompliance['meta'],
                'segments' => [],
            ],
            [
                'label' => get_string('overview:activecompanies', 'block_dashboardanalytics'),
                'value' => $activecompanies['value'],
                'percent' => $activecompanies['percent'],
                'status' => $activecompanies['status'],
                'meta' => $activecompanies['meta'],
                'segments' => [],
            ],
        ];
    }

    public function platform_growth_items(array $filters): array {
        global $DB;

        $windows = $this->platform_growth_windows($filters);
        if (!$windows) {
            return [];
        }

        $employee = new employee_repository();
        $companyrepo = new company_repository();
        $userfilter = $employee->user_filter_sql($filters, 'u', 'platformgrowth');
        $companysql = $companyrepo->company_name_sql('u', 'platformgrowth');
        $params = $userfilter['params'] + [
            'platformgrowthstart' => $windows[0]['start'],
            'platformgrowthend' => $windows[count($windows) - 1]['end'],
        ];

        $sql = "SELECT u.id AS userid,
                       u.timecreated,
                       {$companysql['select']}
                  FROM {user} u
                       {$companysql['join']}
                 WHERE {$userfilter['sql']}
                   AND u.timecreated >= :platformgrowthstart
                   AND u.timecreated <= :platformgrowthend
              ORDER BY u.timecreated ASC";

        $records = $DB->get_records_sql($sql, $params);
        if (!$records) {
            return [];
        }

        $windowmap = [];
        foreach ($windows as $window) {
            $windowmap[$window['key']] = $window;
        }

        $counts = [];
        $companytotals = [];
        $timezone = new \DateTimeZone('Asia/Almaty');
        foreach ($records as $record) {
            $monthkey = (new \DateTimeImmutable('@' . (int)$record->timecreated))->setTimezone($timezone)->format('Y-m');
            if (!isset($windowmap[$monthkey])) {
                continue;
            }

            $companyname = trim((string)$record->companyname);
            if ($companyname === '') {
                $companyname = get_string('label:unassigned', 'block_dashboardanalytics');
            }

            if (!isset($counts[$monthkey])) {
                $counts[$monthkey] = [];
            }

            if (!isset($counts[$monthkey][$companyname])) {
                $counts[$monthkey][$companyname] = 0;
            }

            $counts[$monthkey][$companyname]++;
            if (!isset($companytotals[$companyname])) {
                $companytotals[$companyname] = 0;
            }
            $companytotals[$companyname]++;
        }

        arsort($companytotals);
        $companies = array_slice(array_keys($companytotals), 0, 3);
        if (!$companies) {
            return [];
        }

        $seriesstatuses = ['info', 'danger', 'warning', 'ok', 'muted'];
        $globalmax = 1;
        foreach ($counts as $monthcounts) {
            foreach ($companies as $company) {
                $globalmax = max($globalmax, (int)($monthcounts[$company] ?? 0));
            }
        }

        $items = [];
        foreach ($windows as $window) {
            $monthcounts = $counts[$window['key']] ?? [];
            $segments = [];
            $monthmax = 0;

            foreach ($companies as $index => $company) {
                $value = (int)($monthcounts[$company] ?? 0);
                $monthmax = max($monthmax, $value);
                $segments[] = [
                    'label' => $company,
                    'value' => (string)$value,
                    'percent' => round(($value / $globalmax) * 100, 1),
                    'status' => $seriesstatuses[$index % count($seriesstatuses)],
                ];
            }

            $items[] = [
                'label' => $window['label'],
                'value' => '',
                'percent' => round(($monthmax / $globalmax) * 100, 1),
                'status' => 'info',
                'meta' => get_string('overview:platformgrowthmeta', 'block_dashboardanalytics'),
                'segments' => $segments,
            ];
        }

        return $items;
    }

    public function activity_snapshot_items(array $filters): array {
        $todaystart = (new \DateTimeImmutable('today 00:00:00', new \DateTimeZone('Asia/Almaty')))->getTimestamp();
        $todayend = (new \DateTimeImmutable('today 23:59:59', new \DateTimeZone('Asia/Almaty')))->getTimestamp();
        $yesterdaystart = $todaystart - DAYSECS;
        $yesterdayend = $todaystart - 1;
        $last30start = $todayend - (30 * DAYSECS) + 1;
        $previous30start = $last30start - (30 * DAYSECS);
        $previous30end = $last30start - 1;

        $dau = $this->log_distinct_user_count($filters, $todaystart, $todayend);
        $dauyesterday = $this->log_distinct_user_count($filters, $yesterdaystart, $yesterdayend);
        $mau = $this->log_distinct_user_count($filters, $last30start, $todayend);
        $completion = $this->completion_rate_summary($filters, $last30start, $todayend);
        $completionprevious = $this->completion_rate_summary($filters, $previous30start, $previous30end);
        $avgsession = $this->average_session_minutes($filters, $last30start, $todayend);
        $avgsessionprevious = $this->average_session_minutes($filters, $previous30start, $previous30end);

        $items = [
            [
                'label' => get_string('overview:dautoday', 'block_dashboardanalytics'),
                'value' => (string)$dau,
                'percent' => 0.0,
                'status' => 'info',
                'meta' => $this->delta_meta($dau, $dauyesterday, get_string('overview:vsday', 'block_dashboardanalytics')),
                'segments' => [],
            ],
            [
                'label' => get_string('overview:mau30d', 'block_dashboardanalytics'),
                'value' => (string)$mau,
                'percent' => 0.0,
                'status' => 'ok',
                'meta' => get_string('overview:last30days', 'block_dashboardanalytics'),
                'segments' => [],
            ],
            [
                'label' => get_string('overview:completionrate', 'block_dashboardanalytics'),
                'value' => $completion['value'],
                'percent' => $completion['percent'],
                'status' => $completion['status'],
                'meta' => $this->delta_meta($completion['percent'], $completionprevious['percent'], get_string('kpi:trend:vslastmo', 'block_dashboardanalytics')),
                'segments' => [],
            ],
            [
                'label' => get_string('overview:avgsession', 'block_dashboardanalytics'),
                'value' => $avgsession['value'],
                'percent' => 0.0,
                'status' => 'warning',
                'meta' => $this->delta_meta($avgsession['minutes'], $avgsessionprevious['minutes'], get_string('kpi:trend:vslastmo', 'block_dashboardanalytics')),
                'segments' => [],
            ],
        ];

        foreach ($this->top_active_course_items($filters, 3, $last30start, $todayend, $mau) as $courseitem) {
            $items[] = $courseitem;
        }

        return $items;
    }

    public function company_health_items(array $filters): array {
        $companies = $this->company_scope_options($filters);
        $trustmap = $this->trust_score_map($filters);
        $items = [];

        foreach ($companies as $company) {
            $companyfilters = $this->company_scoped_filters($filters, $company['name'], $company['id']);
            $activeusers = (new employee_repository())->count_active_users($companyfilters);
            $compliancesummary = $this->overall_employee_compliance_summary($companyfilters);
            $turnoverpercent = $this->recent_staff_change_percent($companyfilters, 90, $activeusers);
            $completion = $this->completion_rate_summary($companyfilters, $this->current_report_date() - (30 * DAYSECS) + 1, $this->current_report_date());
            $companyname = $company['name'];
            $trustscore = $trustmap[$companyname] ?? null;
            $statuskey = $this->company_health_status_key($activeusers, (float)$compliancesummary['percent']);

            $items[] = [
                'label' => $companyname,
                'value' => $activeusers > 0 ? (string)$activeusers : '—',
                'percent' => (float)$compliancesummary['percent'],
                'status' => $statuskey,
                'meta' => $activeusers > 0 ? round($turnoverpercent, 1) . '%' : '—',
                'segments' => [
                    ['label' => get_string('label:compliancepercent', 'block_dashboardanalytics'), 'value' => $activeusers > 0 ? round((float)$compliancesummary['percent'], 1) . '%' : '—', 'percent' => (float)$compliancesummary['percent'], 'status' => $this->status_for_percent((float)$compliancesummary['percent'])],
                    ['label' => get_string('overview:trustscore', 'block_dashboardanalytics'), 'value' => $trustscore !== null ? (string)round($trustscore) : '—', 'percent' => $trustscore !== null ? (float)$trustscore : 0.0, 'status' => $trustscore !== null ? $this->status_for_percent((float)$trustscore) : 'muted'],
                    ['label' => get_string('overview:completion', 'block_dashboardanalytics'), 'value' => $completion['value'], 'percent' => $completion['percent'], 'status' => $completion['status']],
                    ['label' => get_string('label:action', 'block_dashboardanalytics'), 'value' => get_string('overview:report', 'block_dashboardanalytics'), 'percent' => 0.0, 'status' => 'info'],
                    ['label' => 'companyid', 'value' => (string)$company['id'], 'percent' => 0.0, 'status' => 'muted'],
                ],
            ];
        }

        return $items;
    }

    public function company_health_modal_data(array $filters, string $companyname, int $companyid = 0): array {
        $companyfilters = $this->company_scoped_filters($filters, $companyname, $companyid);
        $employees = new employee_repository();
        $activeusers = $employees->count_active_users($companyfilters);
        $compliancesummary = $this->overall_employee_compliance_summary($companyfilters);
        $statuscounts = $this->status_counts($companyfilters);
        $turnoverpercent = $this->recent_staff_change_percent($companyfilters, 90, $activeusers);
        $trustscore = $this->company_trust_score($companyfilters, $companyname);
        $edspending = (new eds_repository())->count_pending_manual($companyfilters);
        $statuskey = $this->company_health_status_key($activeusers, (float)$compliancesummary['percent']);
        $subtitle = $this->company_health_modal_subtitle($companyfilters);

        return [
            'title' => $companyname,
            'subtitle' => $subtitle,
            'statuskey' => $statuskey,
            'statuslabel' => $this->company_health_status_label($statuskey),
            'summarycards' => [
                [
                    'label' => get_string('label:activeusers', 'block_dashboardanalytics'),
                    'value' => (string)$activeusers,
                    'status' => 'info',
                ],
                [
                    'label' => get_string('label:compliancepercent', 'block_dashboardanalytics'),
                    'value' => round((float)$compliancesummary['percent'], 1) . '%',
                    'status' => $this->status_for_percent((float)$compliancesummary['percent']),
                ],
                [
                    'label' => get_string('kpi:expiring30long', 'block_dashboardanalytics'),
                    'value' => (string)$statuscounts['expiring'],
                    'status' => 'warning',
                ],
                [
                    'label' => get_string('kpi:expirednow', 'block_dashboardanalytics'),
                    'value' => (string)$statuscounts['expired'],
                    'status' => 'danger',
                ],
            ],
            'courseitems' => $this->company_course_compliance_modal_items($companyfilters, 5),
            'additionalcards' => [
                [
                    'label' => get_string('tab:turnover', 'block_dashboardanalytics'),
                    'value' => $activeusers > 0 ? round($turnoverpercent, 1) . '%' : '—',
                    'status' => 'ok',
                ],
                [
                    'label' => get_string('overview:trustscore', 'block_dashboardanalytics'),
                    'value' => $trustscore !== null ? round($trustscore, 1) . ' / 100' : '—',
                    'status' => $trustscore !== null ? $this->status_for_percent((float)$trustscore) : 'muted',
                ],
                [
                    'label' => get_string('panel:edsqueue:title', 'block_dashboardanalytics'),
                    'value' => (string)$edspending,
                    'status' => $edspending > 0 ? 'warning' : 'info',
                ],
            ],
            'courseheading' => get_string('modal:companycourses', 'block_dashboardanalytics'),
            'metricsheading' => get_string('modal:additionalmetrics', 'block_dashboardanalytics'),
            'closebutton' => get_string('modal:close', 'block_dashboardanalytics'),
            'exportbutton' => get_string('modal:exportpdf', 'block_dashboardanalytics'),
        ];
    }

    public function priority_action_items(array $filters): array {
        $actions = [];
        $summaries = $this->company_summaries($filters, $this->current_report_date());

        if ($summaries) {
            usort($summaries, static function(array $a, array $b): int {
                return $a['percent'] <=> $b['percent'];
            });
            $worst = $summaries[0];
            if ($worst['total'] > 0) {
                $noncompliant = max(0, $worst['total'] - $worst['compliant']);
                $actions[] = [
                    'label' => strtoupper(get_string('label:critical', 'block_dashboardanalytics')) . ' — ' . $worst['label'] . ' ' . round((float)$worst['percent'], 1) . '%',
                    'value' => get_string('overview:viewreportcta', 'block_dashboardanalytics'),
                    'percent' => 0.0,
                    'status' => 'danger',
                    'meta' => $noncompliant . ' ' . get_string('overview:employeeswithoutvaliddocs', 'block_dashboardanalytics'),
                    'segments' => [],
                ];
            }
        }

        $expiredsummary = $this->expired_documents_priority_summary($filters);
        if ($expiredsummary['count'] > 0) {
            $actions[] = [
                'label' => strtoupper(get_string('label:urgent', 'block_dashboardanalytics')) . ' — ' . $expiredsummary['count'] . ' ' . get_string('overview:documentsexpired', 'block_dashboardanalytics'),
                'value' => get_string('overview:viewexpiredcta', 'block_dashboardanalytics'),
                'percent' => 0.0,
                'status' => 'warning',
                'meta' => $expiredsummary['meta'],
                'segments' => [],
            ];
        }

        $server = new server_repository();
        $disk = $server->disk_card();
        if (preg_match('/([0-9]+(?:\.[0-9]+)?)%/', (string)$disk['value'], $matches)) {
            $actions[] = [
                'label' => strtoupper(get_string('tab:server', 'block_dashboardanalytics')) . ' — ' . get_string('kpi:serverdisk', 'block_dashboardanalytics') . ' ' . $matches[1] . '%',
                'value' => get_string('js:gotoservertab', 'block_dashboardanalytics'),
                'percent' => 0.0,
                'status' => (string)$disk['status'],
                'meta' => (string)$disk['trend'],
                'segments' => [],
            ];
        }

        if (!$actions) {
            $actions[] = [
                'label' => get_string('overview:nopriorityactions', 'block_dashboardanalytics'),
                'value' => get_string('label:ok', 'block_dashboardanalytics'),
                'percent' => 0.0,
                'status' => 'ok',
                'meta' => get_string('overview:nopriorityactionsmeta', 'block_dashboardanalytics'),
                'segments' => [],
            ];
        }

        return array_slice($actions, 0, 3);
    }

    private function company_summaries(array $filters, int $reportdate): array {
        $rows = $this->enrolment_status_rows($filters, $reportdate);
        $companies = [];
        foreach ($rows as $row) {
            $companyname = trim((string)($row['company'] ?? ''));
            if ($companyname === '') {
                $companyname = get_string('label:unassigned', 'block_dashboardanalytics');
            }

            $companyid = (int)($row['companyid'] ?? 0);
            $normalizedcompanyname = class_exists('\core_text') ? \core_text::strtolower($companyname) : strtolower($companyname);
            $companykey = $companyid > 0 ? 'id:' . $companyid : 'name:' . $normalizedcompanyname;
            if (!isset($companies[$companykey])) {
                $companies[$companykey] = [
                    'label' => $companyname,
                    'rows' => [],
                ];
            }
            $companies[$companykey]['rows'][] = $row;
        }

        $summaries = [];
        foreach ($companies as $company) {
            $summary = $this->compliance_rollup_from_rows($company['rows']);
            $summaries[] = [
                'label' => $company['label'],
                'total' => $summary['total'],
                'compliant' => $summary['compliant'],
                'percent' => $summary['percent'],
            ];
        }

        return $summaries;
    }

    private function compliance_rollup_from_rows(array $rows): array {
        $users = [];

        foreach ($rows as $row) {
            $userid = (int)$row['userid'];
            if (!isset($users[$userid])) {
                $users[$userid] = [
                    'totalcourses' => 0,
                    'validcourses' => 0,
                ];
            }

            $users[$userid]['totalcourses']++;
            if ($row['status'] === 'Active' || $row['status'] === 'Expiring') {
                $users[$userid]['validcourses']++;
            }
        }

        $total = 0;
        $compliant = 0;
        $sumpercent = 0.0;

        foreach ($users as $user) {
            if ($user['totalcourses'] <= 0) {
                continue;
            }

            $total++;
            $employeepercent = ($user['validcourses'] / $user['totalcourses']) * 100.0;
            $sumpercent += $employeepercent;

            if ($user['validcourses'] >= $user['totalcourses']) {
                $compliant++;
            }
        }

        return [
            'total' => $total,
            'compliant' => $compliant,
            'percent' => $total > 0 ? round($sumpercent / $total, 1) : 0.0,
        ];
    }

    private function enrolment_status_rows(array $filters, int $reportdate): array {
        global $DB;

        $employee = new employee_repository();
        $documents = new document_repository();
        $analytics = new course_analytics_repository();
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
        $positionfield = $this->existing_user_profile_field_shortname(array_values(array_filter([
            trim((string)get_config('block_dashboardanalytics', 'positionfield')),
            'Job_Title',
        ])));
        $positionjoin = '';
        if ($positionfield !== '') {
            $escapedpositionfield = addslashes($positionfield);
            $positionjoin = "LEFT JOIN {user_info_field} uifpos ON uifpos.shortname = '{$escapedpositionfield}'
                             LEFT JOIN {user_info_data} uidpos ON uidpos.fieldid = uifpos.id AND uidpos.userid = u.id";
            $positionselect = 'uidpos.data AS positionname';
        }

        $departmentselect = 'u.department AS departmentname';
        $departmentjoin = '';
        $departmentfield = $this->existing_user_profile_field_shortname(['Department', 'department']);
        if ($departmentfield !== '') {
            $escapeddepartmentfield = addslashes($departmentfield);
            $departmentjoin = "LEFT JOIN {user_info_field} uifdep ON uifdep.shortname = '{$escapeddepartmentfield}'
                               LEFT JOIN {user_info_data} uiddep ON uiddep.fieldid = uifdep.id AND uiddep.userid = u.id";
            $departmentselect = 'COALESCE(NULLIF(uiddep.data, \'\'), u.department) AS departmentname';
        }

        $validitysql = $this->validity_days_sql('cfd');
        $expiryselect = "CASE
                            WHEN cc.timecompleted IS NULL OR cc.timecompleted <= 0 THEN NULL
                            ELSE cc.timecompleted + ({$validitysql} * 86400)
                         END AS expirytime";
        $docjoin = $this->latest_document_join_sql($source, 'u', 'c', 'd');
        $analyticsjoin = $analytics->eligibility_join_sql('c', 'cfanalyticsoverview', 'cdanalyticsoverview');
        $basewhere = [
            $userfilter['sql'],
            'c.id <> :siteid',
            $analytics->eligibility_where_sql('c', 'cfanalyticsoverview', 'cdanalyticsoverview'),
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
                                COALESCE({$companysql['idexpr']}, 0) AS companyid,
                                {$companysql['select']},
                                d.id AS documentid,
                                {$expiryselect}
                           FROM {{$source['table']}} d
                           JOIN {user} u ON u.id = d.{$source['userid']}
                           JOIN {course} c ON c.id = d.{$source['courseid']}
                      LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id
                      LEFT JOIN {customfield_field} cff ON cff.shortname = 'validity_period'
                      LEFT JOIN {customfield_data} cfd ON cfd.fieldid = cff.id AND cfd.instanceid = c.id
                                {$analyticsjoin}
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
                             COALESCE({$companysql['idexpr']}, 0) AS companyid,
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
                             {$analyticsjoin}
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
            $expirytime = $record->expirytime !== null ? (int)$record->expirytime : null;
            $status = $this->status_for_row((int)$record->documentid, $expirytime, $reportdate);
            $rows[] = [
                'userid' => (int)$record->userid,
                'courseid' => (int)$record->courseid,
                'employee' => fullname($record),
                'companyid' => (int)$record->companyid,
                'company' => (string)$record->companyname,
                'department' => (string)$record->departmentname,
                'location' => (string)$record->city,
                'position' => (string)$record->positionname,
                'course' => format_string((string)$record->coursename),
                'documentid' => (int)$record->documentid,
                'expirytime' => $expirytime ?? 0,
                'status' => $status,
            ];

            if (count($samples) < 10) {
                $samples[] = [
                    'userid' => (int)$record->userid,
                        'courseid' => (int)$record->courseid,
                        'course' => $this->truncate_text((string)$record->coursename, 120),
                        'documentid' => (int)$record->documentid,
                        'expirytime' => $expirytime,
                        'status' => $status,
                        'companyid' => (int)$record->companyid,
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

    private function status_for_row(int $documentid, ?int $expirytime, int $reportdate): string {
        if ($documentid <= 0 || $expirytime === null || $expirytime <= 0) {
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
        $count = 12;
        if (in_array($daterange, ['6months', 'last6months'], true)) {
            $count = 6;
        } else if (in_array($daterange, ['day', 'week', 'month', 'last30days'], true)) {
            $count = 1;
        } else if ($daterange === 'last90days') {
            $count = 3;
        } else if ($daterange === 'alltime') {
            $count = 24;
        }

        if ($daterange === 'customrange' && !empty($filters['customstart']) && !empty($filters['customend'])) {
            $start = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $filters['customstart'] . ' 00:00:00', new \DateTimeZone('Asia/Almaty'));
            $end = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $filters['customend'] . ' 23:59:59', new \DateTimeZone('Asia/Almaty'));
            if ($start && $end && $start <= $end) {
                $months = [];
                $cursor = $start->modify('first day of this month 00:00:00');
                $limit = 0;
                while ($cursor <= $end && $limit < 24) {
                    $windowend = $cursor->modify('last day of this month 23:59:59');
                    $months[] = [
                        'key' => $cursor->format('Y-m'),
                        'label' => userdate($windowend->getTimestamp(), '%b %y'),
                        'end' => min($windowend->getTimestamp(), $end->getTimestamp()),
                    ];
                    $cursor = $cursor->modify('+1 month');
                    $limit++;
                }
                if ($months) {
                    return $months;
                }
            }
        }

        $months = [];
        $base = new \DateTimeImmutable('first day of this month 00:00:00', new \DateTimeZone('Asia/Almaty'));
        for ($offset = $count - 1; $offset >= 0; $offset--) {
            $start = $base->modify('-' . $offset . ' months');
            $end = $start->modify('last day of this month 23:59:59');
            $months[] = [
                'key' => $start->format('Y-m'),
                'label' => userdate($end->getTimestamp(), '%b %y'),
                'end' => $end->getTimestamp(),
            ];
        }
        return $months;
    }

    private function platform_growth_windows(array $filters): array {
        $period = $filters['platformgrowthperiod'] ?? '';
        if ($period === '') {
            $period = '1year';
        }

        $timezone = new \DateTimeZone('Asia/Almaty');
        $base = new \DateTimeImmutable('first day of this month 00:00:00', $timezone);
        $count = 12;
        if ($period === '3months') {
            $count = 3;
        } else if ($period === '2years') {
            $count = 24;
        } else if ($period === 'alltime') {
            global $DB;
            $earliest = (int)$DB->get_field_select('user', 'MIN(timecreated)', 'deleted = 0 AND confirmed = 1');
            if ($earliest > 0) {
                $start = (new \DateTimeImmutable('@' . $earliest))->setTimezone($timezone)->modify('first day of this month 00:00:00');
                $count = max(1, (($base->format('Y') - $start->format('Y')) * 12) + ((int)$base->format('n') - (int)$start->format('n')) + 1);
            } else {
                $count = 12;
            }
        }

        $format = $count > 12 ? '%b %y' : '%b';
        $months = [];
        for ($offset = $count - 1; $offset >= 0; $offset--) {
            $start = $base->modify('-' . $offset . ' months');
            $end = $start->modify('last day of this month 23:59:59');
            $months[] = [
                'key' => $start->format('Y-m'),
                'label' => userdate($end->getTimestamp(), $format),
                'start' => $start->getTimestamp(),
                'end' => $end->getTimestamp(),
            ];
        }

        return $months;
    }

    private function current_report_date(): int {
        return (new \DateTimeImmutable('today 23:59:59', new \DateTimeZone('Asia/Almaty')))->getTimestamp();
    }

    private function status_item(string $label, int $count, int $total, string $status): array {
        $percent = round(($count / max(1, $total)) * 100, 1);
        return [
            'label' => $label,
            'value' => (string)$count,
            'percent' => $percent,
            'status' => $status,
            'meta' => get_string('meta:percentofchecks', 'block_dashboardanalytics', $percent),
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

    private function top_company_labels(array $filters, int $limit): array {
        $summaries = $this->company_summaries($filters, $this->current_report_date());
        usort($summaries, static function(array $a, array $b): int {
            return $b['total'] <=> $a['total'];
        });

        $labels = array_values(array_filter(array_map(static function(array $summary): string {
            return (string)$summary['label'];
        }, array_slice($summaries, 0, $limit))));

        return $labels ?: [get_string('label:unassigned', 'block_dashboardanalytics')];
    }

    private function uptime_summary(): array {
        return (new uptime_repository())->summary();
    }

    private function average_company_compliance_summary(array $filters): array {
        $summaries = array_values(array_filter($this->company_summaries($filters, $this->current_report_date()), static function(array $summary): bool {
            return $summary['total'] > 0;
        }));

        if (!$summaries) {
            return [
                'value' => get_string('kpi:value:nostaff', 'block_dashboardanalytics'),
                'percent' => 0.0,
                'status' => 'muted',
                'meta' => get_string('overview:belowtarget', 'block_dashboardanalytics'),
            ];
        }

        $average = array_sum(array_map(static function(array $summary): float {
            return (float)$summary['percent'];
        }, $summaries)) / count($summaries);
        $average = round($average, 1);

        return [
            'value' => $average . '%',
            'percent' => $average,
            'status' => $this->status_for_percent($average),
            'meta' => $average < 80 ? get_string('overview:belowtarget', 'block_dashboardanalytics') : get_string('label:ok', 'block_dashboardanalytics'),
        ];
    }

    private function active_company_summary(array $filters): array {
        $options = $this->company_scope_options($filters);
        $active = 0;
        $onboardinglabel = '';

        foreach ($options as $company) {
            $companyfilters = $this->company_scoped_filters($filters, $company['name'], $company['id']);
            $users = (new employee_repository())->count_active_users($companyfilters);
            if ($users > 0) {
                $active++;
            } else if ($onboardinglabel === '') {
                $onboardinglabel = $company['name'];
            }
        }

        $total = count($options);
        return [
            'value' => $active . ' / ' . $total,
            'percent' => $total > 0 ? round(($active / $total) * 100, 1) : 0.0,
            'status' => $active === $total ? 'ok' : ($active > 0 ? 'warning' : 'muted'),
            'meta' => $onboardinglabel !== ''
                ? $onboardinglabel . ' ' . get_string('overview:onboarding', 'block_dashboardanalytics')
                : get_string('overview:allcompaniesactive', 'block_dashboardanalytics'),
        ];
    }

    private function company_scope_options(array $filters): array {
        $companyrepo = new company_repository();
        $options = $companyrepo->get_company_options($filters);
        if ($options) {
            return array_map(static function(array $option): array {
                return [
                    'id' => ctype_digit((string)$option['value']) ? (int)$option['value'] : 0,
                    'name' => (string)$option['label'],
                ];
            }, $options);
        }

        $summaries = $this->company_summaries($filters, $this->current_report_date());
        return array_map(static function(array $summary): array {
            return ['id' => 0, 'name' => (string)$summary['label']];
        }, $summaries);
    }

    private function company_scoped_filters(array $filters, string $companyname, int $companyid = 0): array {
        $companyrepo = new company_repository();
        if ($companyrepo->has_iomad_tables() && $companyid > 0) {
            $filters['companyids'] = [$companyid];
            unset($filters['companies']);
            return $filters;
        }

        $filters['companies'] = [$companyname];
        unset($filters['companyids']);
        return $filters;
    }

    private function trust_score_map(array $filters): array {
        $items = (new proctoring_repository())->company_average_items($filters, 200);
        $map = [];
        foreach ($items as $item) {
            $map[$item['label']] = (float)$item['percent'];
        }
        return $map;
    }

    private function recent_staff_change_percent(array $filters, int $days, int $activeusers): float {
        global $DB;

        if ($activeusers <= 0) {
            return 0.0;
        }

        $employee = new employee_repository();
        $filter = $employee->user_filter_sql($filters, 'u', 'recentstaff');
        $params = $filter['params'] + ['createdsince' => time() - ($days * DAYSECS)];
        $sql = "SELECT COUNT(1)
                  FROM {user} u
                 WHERE {$filter['sql']}
                   AND u.timecreated >= :createdsince";

        $count = (int)$DB->count_records_sql($sql, $params);
        return round(($count / $activeusers) * 100, 1);
    }

    private function has_log_table(): bool {
        return $this->table_exists('logstore_standard_log');
    }

    private function log_distinct_user_count(array $filters, int $start, int $end): int {
        global $DB;

        if (!$this->has_log_table()) {
            return 0;
        }

        $employee = new employee_repository();
        $filter = $employee->user_filter_sql($filters, 'u', 'logcount');
        $params = $filter['params'] + ['startts' => $start, 'endts' => $end];
        $sql = "SELECT COUNT(DISTINCT l.userid)
                  FROM {logstore_standard_log} l
                  JOIN {user} u ON u.id = l.userid
                 WHERE l.userid > 0
                   AND {$filter['sql']}
                   AND l.timecreated >= :startts
                   AND l.timecreated <= :endts";

        return (int)$DB->count_records_sql($sql, $params);
    }

    private function completion_rate_summary(array $filters, int $start, int $end): array {
        global $DB;

        $employee = new employee_repository();
        $analytics = new course_analytics_repository();
        $filter = $employee->user_filter_sql($filters, 'u', 'completionrate');
        $params = $filter['params'] + [
            'siteid' => SITEID,
            'startts' => $start,
            'endts' => $end,
        ];
        $analyticsjoin = $analytics->eligibility_join_sql('c', 'cfcompletionrate', 'cdcompletionrate');

        $sql = "SELECT COUNT(1) AS totalenrolments,
                       SUM(CASE WHEN cc.timecompleted IS NOT NULL
                                 AND cc.timecompleted >= :startts
                                 AND cc.timecompleted <= :endts
                                THEN 1 ELSE 0 END) AS completedrecent
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
                  JOIN {course} c ON c.id = e.courseid
                  {$analyticsjoin}
                  JOIN {user} u ON u.id = ue.userid
             LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = c.id
                 WHERE ue.status = 0
                   AND c.id <> :siteid
                   AND " . $analytics->eligibility_where_sql('c', 'cfcompletionrate', 'cdcompletionrate') . "
                   AND {$filter['sql']}";

        if (!empty($filters['courseids'])) {
            [$insql, $inparams] = $DB->get_in_or_equal($filters['courseids'], SQL_PARAMS_NAMED, 'completioncourse');
            $sql .= " AND c.id {$insql}";
            $params += $inparams;
        }

        $record = $DB->get_record_sql($sql, $params);
        $total = (int)($record->totalenrolments ?? 0);
        $completed = (int)($record->completedrecent ?? 0);
        $percent = $total > 0 ? round(($completed / $total) * 100, 1) : 0.0;

        return [
            'value' => $percent . '%',
            'percent' => $percent,
            'status' => $this->status_for_percent($percent),
            'completed' => $completed,
            'total' => $total,
        ];
    }

    private function average_session_minutes(array $filters, int $start, int $end): array {
        global $DB;

        if (!$this->has_log_table()) {
            return ['value' => 'N/A', 'minutes' => 0.0];
        }

        $employee = new employee_repository();
        $filter = $employee->user_filter_sql($filters, 'u', 'sessionavg');
        $params = $filter['params'] + ['startts' => $start, 'endts' => $end];
        $sql = "SELECT AVG(spans.spanseconds) AS avgseconds
                  FROM (
                        SELECT l.userid,
                               FLOOR(l.timecreated / 86400) AS daybucket,
                               LEAST(MAX(l.timecreated) - MIN(l.timecreated), 14400) AS spanseconds
                          FROM {logstore_standard_log} l
                          JOIN {user} u ON u.id = l.userid
                         WHERE l.userid > 0
                           AND {$filter['sql']}
                           AND l.timecreated >= :startts
                           AND l.timecreated <= :endts
                      GROUP BY l.userid, FLOOR(l.timecreated / 86400)
                       ) spans";

        $record = $DB->get_record_sql($sql, $params);
        $avgseconds = (float)($record->avgseconds ?? 0.0);
        $minutes = round($avgseconds / 60, 1);

        return [
            'value' => $minutes > 0 ? round($minutes) . 'm' : '0m',
            'minutes' => $minutes,
        ];
    }

    private function top_active_course_items(array $filters, int $limit, int $start, int $end, int $mau): array {
        global $DB;

        if (!$this->has_log_table()) {
            return [];
        }

        $employee = new employee_repository();
        $analytics = new course_analytics_repository();
        $filter = $employee->user_filter_sql($filters, 'u', 'topcourses');
        $params = $filter['params'] + [
            'siteid' => SITEID,
            'startts' => $start,
            'endts' => $end,
        ];
        $analyticsjoin = $analytics->eligibility_join_sql('c', 'cftopcourses', 'cdtopcourses');

        $sql = "SELECT c.id,
                       c.fullname,
                       COUNT(DISTINCT l.userid) AS activeusers
                  FROM {logstore_standard_log} l
                  JOIN {user} u ON u.id = l.userid
                  JOIN {course} c ON c.id = l.courseid
                  {$analyticsjoin}
                 WHERE l.userid > 0
                   AND c.id <> :siteid
                   AND " . $analytics->eligibility_where_sql('c', 'cftopcourses', 'cdtopcourses') . "
                   AND {$filter['sql']}
                   AND l.timecreated >= :startts
                   AND l.timecreated <= :endts
              GROUP BY c.id, c.fullname
              ORDER BY activeusers DESC, c.fullname ASC";

        $records = $DB->get_records_sql($sql, $params, 0, $limit);
        $items = [];
        foreach ($records as $record) {
            $percent = $mau > 0 ? round(((int)$record->activeusers / $mau) * 100, 1) : 0.0;
            $items[] = [
                'label' => format_string((string)$record->fullname),
                'value' => $percent . '%',
                'percent' => $percent,
                'status' => $percent >= 80 ? 'ok' : ($percent >= 60 ? 'warning' : 'danger'),
                'meta' => (string)(int)$record->activeusers,
                'segments' => [],
            ];
        }

        return $items;
    }

    private function delta_meta(float $current, float $previous, string $suffix): string {
        $delta = round($current - $previous, 1);
        if (abs($delta) < 0.1) {
            return get_string('kpi:trend:flat', 'block_dashboardanalytics', $suffix);
        }

        if ($delta > 0) {
            return '+' . abs($delta) . ' ' . $suffix;
        }

        return '↓ ' . abs($delta) . ' ' . $suffix;
    }

    private function company_health_status_key(int $activeusers, float $compliance): string {
        if ($activeusers <= 0) {
            return 'onboarding';
        }
        if ($compliance >= 80) {
            return 'healthy';
        }
        if ($compliance >= 70) {
            return 'atrisk';
        }
        return 'critical';
    }

    private function company_health_status_label(string $statuskey): string {
        if ($statuskey === 'healthy') {
            return get_string('js:healthylabel', 'block_dashboardanalytics');
        }
        if ($statuskey === 'atrisk') {
            return get_string('js:atrisklabel', 'block_dashboardanalytics');
        }
        if ($statuskey === 'critical') {
            return get_string('label:critical', 'block_dashboardanalytics');
        }
        return get_string('js:onboardinglabel', 'block_dashboardanalytics');
    }

    private function company_trust_score(array $filters, string $companyname): ?float {
        $items = (new proctoring_repository())->company_average_items($filters, 50);
        foreach ($items as $item) {
            if ((string)$item['label'] === $companyname) {
                return (float)$item['percent'];
            }
        }

        if (count($items) === 1) {
            return (float)$items[0]['percent'];
        }

        return null;
    }

    private function company_health_modal_subtitle(array $filters): string {
        $departments = (new employee_repository())->active_users_by_dimension_items($filters, 'department', 200);
        $locations = (new employee_repository())->active_users_by_dimension_items($filters, 'location', 200);

        return get_string('modal:companysubtitle', 'block_dashboardanalytics', (object)[
            'departments' => count($departments),
            'locations' => count($locations),
        ]);
    }

    private function company_course_compliance_modal_items(array $filters, int $limit): array {
        $rows = $this->enrolment_status_rows($filters, $this->current_report_date());
        $courses = [];

        foreach ($rows as $row) {
            $course = $row['course'] ?: get_string('label:unassigned', 'block_dashboardanalytics');
            if (!isset($courses[$course])) {
                $courses[$course] = ['total' => 0, 'bad' => 0];
            }

            $courses[$course]['total']++;
            if ($row['status'] === 'Expired' || $row['status'] === 'No document') {
                $courses[$course]['bad']++;
            }
        }

        $items = [];
        foreach ($courses as $course => $counts) {
            $compliance = $counts['total'] > 0 ? round((($counts['total'] - $counts['bad']) / $counts['total']) * 100, 1) : 0.0;
            $items[] = [
                'label' => $course,
                'value' => round($compliance, 1) . '%',
                'percent' => $compliance,
                'status' => $this->status_for_percent($compliance),
            ];
        }

        usort($items, static function(array $a, array $b): int {
            return $b['percent'] <=> $a['percent'];
        });

        return array_slice($items, 0, $limit);
    }

    private function expired_documents_priority_summary(array $filters): array {
        $rows = $this->enrolment_status_rows($filters, $this->current_report_date());
        $expired = array_values(array_filter($rows, static function(array $row): bool {
            return $row['status'] === 'Expired' && !empty($row['expirytime']);
        }));

        if (!$expired) {
            return ['count' => 0, 'meta' => ''];
        }

        usort($expired, static function(array $a, array $b): int {
            return $a['expirytime'] <=> $b['expirytime'];
        });

        $mostoverdue = $expired[0];
        $days = max(0, (int)floor(($this->current_report_date() - (int)$mostoverdue['expirytime']) / DAYSECS));

        return [
            'count' => count($expired),
            'meta' => $mostoverdue['employee'] . ' - ' . $days . ' ' . get_string('overview:daysoverdue', 'block_dashboardanalytics'),
        ];
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

    private function existing_user_profile_field_shortname(array $candidates): string {
        global $DB;

        foreach ($candidates as $candidate) {
            $candidate = trim((string)$candidate);
            if ($candidate !== '' && $DB->record_exists('user_info_field', ['shortname' => $candidate])) {
                return $candidate;
            }
        }

        return '';
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

    private function table_exists(string $tablename): bool {
        global $CFG, $DB;

        require_once($CFG->libdir . '/xmldb/xmldb_table.php');
        return $DB->get_manager()->table_exists(new \xmldb_table($tablename));
    }
}
