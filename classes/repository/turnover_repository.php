<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\repository;

defined('MOODLE_INTERNAL') || die();

class turnover_repository {

    public function staff_dynamics_items(array $filters): array {
        $periods = $this->turnover_period_options();
        $tabs = $this->turnover_company_tabs($filters);
        $items = [];

        foreach ($tabs as $tab) {
            $tabfilters = $tab['key'] === 'all'
                ? $filters
                : $this->company_scoped_filters($filters, $tab['label'], (int)$tab['companyid']);
            $records = $this->scoped_user_lifecycle_records($tabfilters, 0, time(), 'turnoverdynamics' . preg_replace('/[^a-z0-9]/i', '', $tab['key']));

            foreach ($periods as $period) {
                $windows = $this->turnover_windows($period['key']);
                $perioditems = $this->build_staff_dynamics_period_items($records, $windows, $period['key'], $tab['key']);
                $items = array_merge($items, $perioditems);
            }
        }

        return [
            'tabs' => array_map(static function(array $tab): array {
                return [
                    'key' => $tab['key'],
                    'label' => $tab['label'],
                    'active' => !empty($tab['active']),
                ];
            }, $tabs),
            'items' => $items,
        ];
    }

    public function turnover_rate_by_company_items(array $filters, int $months = 12, int $limit = 8): array {
        $companies = $this->company_scope_options($filters);
        $windows = $this->rolling_month_windows($months);
        $periodstart = $windows[0]['start'];
        $periodend = $windows[count($windows) - 1]['end'];
        $items = [];

        foreach ($companies as $company) {
            $companyfilters = $this->company_scoped_filters($filters, $company['name'], $company['id']);
            $records = $this->scoped_user_lifecycle_records($companyfilters, 0, $periodend, 'turnovercompany' . $company['id']);
            $deactivated = 0;

            foreach ($records as $record) {
                if ($this->is_deactivated_record($record)
                    && (int)$record->timemodified >= $periodstart
                    && (int)$record->timemodified <= $periodend) {
                    $deactivated++;
                }
            }

            $avgactive = $this->rolling_average_active_users($records, $windows);
            $turnover = $avgactive > 0 ? round(($deactivated / $avgactive) * 100, 1) : 0.0;

            $items[] = [
                'label' => $company['name'],
                'value' => $turnover > 0 ? round($turnover, 1) . '%' : '0%',
                'percent' => $turnover,
                'status' => $this->turnover_status($turnover),
                'meta' => get_string('turnover:companysummary', 'block_dashboardanalytics', (object)[
                    'deactivated' => $deactivated,
                    'average' => round($avgactive, 1),
                ]),
                'segments' => [],
            ];
        }

        $items = array_values(array_filter($items, static function(array $item): bool {
            return $item['label'] !== '';
        }));

        usort($items, static function(array $a, array $b): int {
            return $b['percent'] <=> $a['percent'];
        });

        $items = array_slice($items, 0, $limit);
        $max = 1.0;
        foreach ($items as $item) {
            $max = max($max, (float)$item['percent']);
        }

        foreach ($items as $index => $item) {
            $items[$index]['percent'] = round((((float)$item['percent']) / $max) * 100, 1);
        }

        return $items;
    }

    public function new_hires_without_documents_items(array $filters, int $months = 12, int $limit = 8): array {
        $companies = $this->company_scope_options($filters);
        $windows = $this->rolling_month_windows($months);
        $periodstart = $windows[0]['start'];
        $periodend = $windows[count($windows) - 1]['end'];
        $maturedbefore = time() - (30 * DAYSECS);
        $items = [];

        foreach ($companies as $company) {
            $companyfilters = $this->company_scoped_filters($filters, $company['name'], $company['id']);
            $summary = $this->new_hires_without_documents_summary($companyfilters, $periodstart, $periodend, $maturedbefore, 'newhirerisk' . $company['id']);

            $items[] = [
                'label' => $company['name'],
                'value' => $summary['totalnew'] > 0 ? round($summary['riskpercent'], 1) . '%' : '0%',
                'percent' => $summary['riskpercent'],
                'status' => $summary['totalnew'] > 0 ? $this->new_hire_risk_status($summary['riskpercent']) : 'muted',
                'meta' => $summary['totalnew'] > 0
                    ? get_string('turnover:newhiresriskmeta', 'block_dashboardanalytics', (object)[
                        'risk' => $summary['riskcount'],
                        'total' => $summary['totalnew'],
                    ])
                    : get_string('turnover:nonewhires', 'block_dashboardanalytics'),
                'segments' => [
                    [
                        'label' => get_string('turnover:atrisk', 'block_dashboardanalytics'),
                        'value' => (string)$summary['riskcount'],
                        'percent' => $summary['riskpercent'],
                        'status' => $summary['totalnew'] > 0 ? $this->new_hire_risk_status($summary['riskpercent']) : 'muted',
                    ],
                    [
                        'label' => get_string('turnover:totalnewhires', 'block_dashboardanalytics'),
                        'value' => (string)$summary['totalnew'],
                        'percent' => 100.0,
                        'status' => 'info',
                    ],
                ],
            ];
        }

        $items = array_values(array_filter($items, static function(array $item): bool {
            return $item['label'] !== '';
        }));

        usort($items, static function(array $a, array $b): int {
            return $b['percent'] <=> $a['percent'];
        });

        $items = array_slice($items, 0, $limit);
        foreach ($items as $index => $item) {
            $items[$index]['percent'] = $item['status'] === 'muted'
                ? 0.0
                : round((float)$item['percent'], 1);
        }

        return $items;
    }

    private function scoped_user_lifecycle_records(array $filters, int $start, int $end, string $prefix): array {
        global $DB;

        $employee = new employee_repository();
        $filter = $employee->scoped_user_filter_sql($filters, 'u', $prefix, [
            'requireactive' => false,
            'requireconfirmed' => true,
            'includesuspended' => true,
            'includedeleted' => true,
        ]);
        $params = $filter['params'];
        $params[$prefix . 'hirefield'] = 'Date';
        $where = [$filter['sql']];

        if ($start > 0) {
            $params[$prefix . 'createdstart'] = $start;
            $params[$prefix . 'modifiedstart'] = $start;
            $where[] = "(u.timecreated >= :{$prefix}createdstart OR u.timemodified >= :{$prefix}modifiedstart)";
        }

        if ($end > 0) {
            $params[$prefix . 'createdend'] = $end;
            $params[$prefix . 'modifiedend'] = $end;
            $where[] = "(u.timecreated <= :{$prefix}createdend OR u.timemodified <= :{$prefix}modifiedend)";
        }

        $sql = "SELECT u.id,
                       u.timecreated,
                       u.timemodified,
                       u.suspended,
                       u.deleted,
                       hiredata.data AS hiredateprofile,
                       CASE
                           WHEN hiredata.data REGEXP '^[0-9]+$' AND CAST(hiredata.data AS UNSIGNED) > 0
                               THEN CAST(hiredata.data AS UNSIGNED)
                           WHEN hiredata.data IS NOT NULL AND hiredata.data <> '' AND hiredata.data <> '0'
                               THEN UNIX_TIMESTAMP(hiredata.data)
                           ELSE u.timecreated
                       END AS hiretimestamp,
                       CASE
                           WHEN u.suspended = 1 OR u.deleted = 1 THEN u.timemodified
                           ELSE 0
                       END AS exittimestamp
                  FROM {user} u
             LEFT JOIN {user_info_field} hirefield
                    ON hirefield.shortname = :{$prefix}hirefield
             LEFT JOIN {user_info_data} hiredata
                    ON hiredata.fieldid = hirefield.id
                   AND hiredata.userid = u.id
                 WHERE " . implode(' AND ', $where);

        return $DB->get_records_sql($sql, $params);
    }

    private function new_hires_without_documents_summary(
        array $filters,
        int $periodstart,
        int $periodend,
        int $maturedbefore,
        string $prefix
    ): array {
        global $DB;

        $employee = new employee_repository();
        $documents = new document_repository();
        $source = $documents->source();
        $filter = $employee->user_filter_sql($filters, 'u', $prefix);

        $totalparams = $filter['params'] + [
            $prefix . 'totalstart' => $periodstart,
            $prefix . 'totalend' => $periodend,
        ];
        $totalwhere = [
            $filter['sql'],
            "u.timecreated >= :{$prefix}totalstart",
            "u.timecreated <= :{$prefix}totalend",
        ];

        $totalsql = "SELECT COUNT(1)
                       FROM {user} u
                      WHERE " . implode(' AND ', $totalwhere);
        $totalnew = (int)$DB->count_records_sql($totalsql, $totalparams);

        if ($totalnew <= 0) {
            return [
                'totalnew' => 0,
                'riskcount' => 0,
                'riskpercent' => 0.0,
            ];
        }

        $riskparams = $filter['params'] + [
            $prefix . 'riskstart' => $periodstart,
            $prefix . 'riskend' => $periodend,
            $prefix . 'maturedbefore' => $maturedbefore,
        ];
        $riskwhere = [
            $filter['sql'],
            "u.timecreated >= :{$prefix}riskstart",
            "u.timecreated <= :{$prefix}riskend",
            "u.timecreated <= :{$prefix}maturedbefore",
        ];

        if ($source !== null) {
            $riskwhere[] = 'NOT EXISTS (' . $this->document_exists_subquery_sql($filters, $source, $riskparams, $prefix . 'doc') . ')';
        }

        $risksql = "SELECT COUNT(1)
                      FROM {user} u
                     WHERE " . implode(' AND ', $riskwhere);
        $riskcount = $source !== null ? (int)$DB->count_records_sql($risksql, $riskparams) : $totalnew;

        return [
            'totalnew' => $totalnew,
            'riskcount' => $riskcount,
            'riskpercent' => round(($riskcount / $totalnew) * 100, 1),
        ];
    }

    private function document_exists_subquery_sql(array $filters, array $source, array &$params, string $prefix): string {
        global $DB;

        $where = ["d.{$source['userid']} = u.id"];

        if (!empty($source['origin'])) {
            $where[] = "(d.{$source['origin']} <> :{$prefix}demo OR d.{$source['origin']} IS NULL)";
            $params[$prefix . 'demo'] = 'demo_job';
        }

        if (!empty($source['status'])) {
            $where[] = "d.{$source['status']} IN (:{$prefix}statusmanual, :{$prefix}statusauto)";
            $params[$prefix . 'statusmanual'] = 'completed_manual';
            $params[$prefix . 'statusauto'] = 'completed_auto';
        }

        if (!empty($filters['courseids']) && !empty($source['courseid'])) {
            [$insql, $inparams] = $DB->get_in_or_equal($filters['courseids'], SQL_PARAMS_NAMED, $prefix . 'course');
            $where[] = "d.{$source['courseid']} {$insql}";
            $params += $inparams;
        }

        return "SELECT 1
                  FROM {{$source['table']}} d
                 WHERE " . implode(' AND ', $where);
    }

    private function turnover_period_options(): array {
        return [
            ['key' => '30days', 'label' => get_string('forecast:period:30days', 'block_dashboardanalytics')],
            ['key' => '60days', 'label' => get_string('forecast:period:60days', 'block_dashboardanalytics')],
            ['key' => '90days', 'label' => get_string('forecast:period:90days', 'block_dashboardanalytics')],
            ['key' => '6months', 'label' => get_string('forecast:period:6months', 'block_dashboardanalytics')],
            ['key' => '12months', 'label' => get_string('forecast:period:12months', 'block_dashboardanalytics')],
            ['key' => '3years', 'label' => get_string('forecast:period:3years', 'block_dashboardanalytics')],
        ];
    }

    private function turnover_company_tabs(array $filters): array {
        $companies = $this->company_scope_options($filters);
        $tabs = [];

        if (count($companies) > 1) {
            $tabs[] = [
                'key' => 'all',
                'label' => get_string('filter:allcompanieslabel', 'block_dashboardanalytics'),
                'companyid' => 0,
                'active' => true,
            ];
        }

        foreach ($companies as $index => $company) {
            $tabs[] = [
                'key' => 'company_' . (int)$company['id'],
                'label' => $company['name'],
                'companyid' => (int)$company['id'],
                'active' => empty($tabs) && $index === 0,
            ];
        }

        if (!$tabs) {
            $tabs[] = [
                'key' => 'all',
                'label' => get_string('filter:allcompanieslabel', 'block_dashboardanalytics'),
                'companyid' => 0,
                'active' => true,
            ];
        }

        return $tabs;
    }

    private function turnover_windows(string $periodkey): array {
        $timezone = new \DateTimeZone('Asia/Almaty');
        $today = new \DateTimeImmutable('today 23:59:59', $timezone);

        if (in_array($periodkey, ['30days', '60days', '90days'], true)) {
            $days = $periodkey === '30days' ? 30 : ($periodkey === '60days' ? 60 : 90);
            $start = $today->modify('-' . ($days - 1) . ' days')->setTime(0, 0, 0);
            return $this->chunk_windows($start, $today, 7);
        }

        if (in_array($periodkey, ['6months', '12months'], true)) {
            $months = $periodkey === '6months' ? 6 : 12;
            $base = new \DateTimeImmutable('first day of this month 00:00:00', $timezone);
            $windows = [];
            for ($offset = $months - 1; $offset >= 0; $offset--) {
                $start = $base->modify('-' . $offset . ' months');
                $end = $start->modify('last day of this month 23:59:59');
                $windows[] = [
                    'key' => $start->format('Y-m'),
                    'label' => $this->turnover_interval_label($start, $end, $months <= 6 || $start->format('n') === '1' || $offset === $months - 1),
                    'start' => $start->getTimestamp(),
                    'end' => $end->getTimestamp(),
                ];
            }
            return $windows;
        }

        $base = new \DateTimeImmutable('first day of January this year 00:00:00', $timezone);
        $windows = [];
        for ($offset = 2; $offset >= 0; $offset--) {
            $start = $base->modify('-' . $offset . ' years');
            $end = $start->modify('last day of December 23:59:59');
            $windows[] = [
                'key' => $start->format('Y'),
                'label' => $start->format('Y'),
                'start' => $start->getTimestamp(),
                'end' => $end->getTimestamp(),
            ];
        }
        return $windows;
    }

    private function chunk_windows(\DateTimeImmutable $start, \DateTimeImmutable $end, int $days): array {
        $windows = [];
        $cursor = $start;
        $index = 0;
        while ($cursor->getTimestamp() <= $end->getTimestamp()) {
            $windowend = $cursor->modify('+' . ($days - 1) . ' days')->setTime(23, 59, 59);
            if ($windowend->getTimestamp() > $end->getTimestamp()) {
                $windowend = $end;
            }
            $windows[] = [
                'key' => $cursor->format('Y-m-d'),
                'label' => $this->turnover_interval_label($cursor, $windowend, count($windows) < 2 || $cursor->format('n') === '1'),
                'start' => $cursor->getTimestamp(),
                'end' => $windowend->getTimestamp(),
            ];
            $cursor = $windowend->modify('+1 second');
            $index++;
            if ($index > 60) {
                break;
            }
        }
        return $windows;
    }

    private function turnover_interval_label(\DateTimeImmutable $start, \DateTimeImmutable $end, bool $showyear): string {
        if ($start->format('Y-m') === $end->format('Y-m') && $start->format('j') === '1' && (int)$end->format('j') >= 28) {
            return userdate($start->getTimestamp(), $showyear ? '%b %Y' : '%b');
        }

        $startformat = $showyear ? '%e %b %Y' : '%e %b';
        $endformat = $showyear || $start->format('Y') !== $end->format('Y') ? '%e %b %Y' : '%e %b';
        return trim(userdate($start->getTimestamp(), $startformat)) . ' - ' . trim(userdate($end->getTimestamp(), $endformat));
    }

    private function build_staff_dynamics_period_items(array $records, array $windows, string $periodkey, string $groupkey): array {
        $counts = [];
        $maxmovement = 1;
        $maxrate = 1.0;
        $totaljoined = 0;
        $totalleft = 0;
        $headcounttotal = 0;

        foreach ($windows as $window) {
            $joined = 0;
            $left = 0;
            $headcount = 0;

            foreach ($records as $record) {
                $hiredate = $this->record_hire_timestamp($record);
                $exitdate = $this->record_exit_timestamp($record);

                if ($hiredate >= $window['start'] && $hiredate <= $window['end']) {
                    $joined++;
                }

                if ($exitdate > 0 && $exitdate >= $window['start'] && $exitdate <= $window['end']) {
                    $left++;
                }

                if ($hiredate > 0 && $hiredate <= $window['end'] && ($exitdate <= 0 || $exitdate > $window['end'])) {
                    $headcount++;
                }
            }

            $net = $joined - $left;
            $turnover = $headcount > 0 ? round(($left / $headcount) * 100, 1) : 0.0;
            $counts[$window['key']] = [
                'joined' => $joined,
                'left' => $left,
                'net' => $net,
                'turnover' => $turnover,
                'headcount' => $headcount,
            ];
            $maxmovement = max($maxmovement, $joined, $left, abs($net));
            $maxrate = max($maxrate, $turnover);
            $totaljoined += $joined;
            $totalleft += $left;
            $headcounttotal += $headcount;
        }

        $averageheadcount = count($windows) > 0 ? $headcounttotal / count($windows) : 0;
        $periodturnover = $averageheadcount > 0 ? round(($totalleft / $averageheadcount) * 100, 1) : 0.0;
        $latestheadcount = $windows ? $counts[$windows[count($windows) - 1]['key']]['headcount'] : 0;
        $periodkpis = [
            ['key' => 'joined', 'label' => get_string('turnover:joined', 'block_dashboardanalytics'), 'value' => (string)$totaljoined, 'status' => 'info'],
            ['key' => 'left', 'label' => get_string('turnover:left', 'block_dashboardanalytics'), 'value' => (string)$totalleft, 'status' => 'danger'],
            ['key' => 'net', 'label' => get_string('turnover:netchange', 'block_dashboardanalytics'), 'value' => ($totaljoined - $totalleft >= 0 ? '+' : '') . ($totaljoined - $totalleft), 'status' => $totaljoined >= $totalleft ? 'ok' : 'danger'],
            ['key' => 'turnover', 'label' => get_string('turnover:turnoverrate', 'block_dashboardanalytics'), 'value' => round($periodturnover, 1) . '%', 'status' => $this->turnover_status($periodturnover)],
            ['key' => 'headcount', 'label' => get_string('turnover:headcount', 'block_dashboardanalytics'), 'value' => (string)$latestheadcount, 'status' => 'neutral'],
        ];

        $items = [];
        foreach ($windows as $window) {
            $count = $counts[$window['key']];
            $intervalkpis = [
                ['key' => 'joined', 'label' => get_string('turnover:joined', 'block_dashboardanalytics'), 'value' => (string)$count['joined'], 'status' => 'info'],
                ['key' => 'left', 'label' => get_string('turnover:left', 'block_dashboardanalytics'), 'value' => (string)$count['left'], 'status' => 'danger'],
                ['key' => 'net', 'label' => get_string('turnover:netchange', 'block_dashboardanalytics'), 'value' => ($count['net'] >= 0 ? '+' : '') . $count['net'], 'status' => $count['net'] >= 0 ? 'ok' : 'danger'],
                ['key' => 'turnover', 'label' => get_string('turnover:turnoverrate', 'block_dashboardanalytics'), 'value' => round($count['turnover'], 1) . '%', 'status' => $this->turnover_status($count['turnover'])],
                ['key' => 'headcount', 'label' => get_string('turnover:headcount', 'block_dashboardanalytics'), 'value' => (string)$count['headcount'], 'status' => 'neutral'],
            ];
            $items[] = [
                'key' => $window['key'],
                'label' => $window['label'],
                'value' => (string)$count['net'],
                'percent' => 0.0,
                'status' => $count['net'] >= 0 ? 'ok' : 'danger',
                'meta' => get_string('turnover:monthsummary', 'block_dashboardanalytics', (object)[
                    'new' => $count['joined'],
                    'deactivated' => $count['left'],
                    'net' => $count['net'],
                ]),
                'periodkey' => $periodkey,
                'groupkey' => $groupkey,
                'start' => $window['start'],
                'end' => $window['end'],
                'joined' => $count['joined'],
                'left' => $count['left'],
                'net' => $count['net'],
                'turnover' => $count['turnover'],
                'headcount' => $count['headcount'],
                'maxmovement' => $maxmovement,
                'maxrate' => $maxrate,
                'kpis' => $periodkpis,
                'intervalkpis' => $intervalkpis,
                'segments' => [
                    ['label' => get_string('turnover:joined', 'block_dashboardanalytics'), 'value' => (string)$count['joined'], 'percent' => round(($count['joined'] / $maxmovement) * 100, 1), 'status' => 'info'],
                    ['label' => get_string('turnover:left', 'block_dashboardanalytics'), 'value' => (string)$count['left'], 'percent' => round(($count['left'] / $maxmovement) * 100, 1), 'status' => 'danger'],
                    ['label' => get_string('turnover:netchange', 'block_dashboardanalytics'), 'value' => (string)$count['net'], 'percent' => round((abs($count['net']) / $maxmovement) * 100, 1), 'status' => 'ok'],
                    ['label' => get_string('turnover:turnoverrate', 'block_dashboardanalytics'), 'value' => round($count['turnover'], 1) . '%', 'percent' => round(($count['turnover'] / $maxrate) * 100, 1), 'status' => 'purple'],
                ],
            ];
        }

        return $items;
    }

    private function record_hire_timestamp(\stdClass $record): int {
        if (isset($record->hiretimestamp) && (int)$record->hiretimestamp > 0) {
            return (int)$record->hiretimestamp;
        }

        $profilevalue = trim((string)($record->hiredateprofile ?? ''));
        if ($profilevalue !== '') {
            if (ctype_digit($profilevalue)) {
                $timestamp = (int)$profilevalue;
                if ($timestamp > 0) {
                    return $timestamp;
                }
            }
            $parsed = strtotime($profilevalue);
            if ($parsed !== false) {
                $timestamp = (int)$parsed;
                if ($timestamp > 0) {
                    return $timestamp;
                }
            }
        }

        return (int)$record->timecreated;
    }

    private function record_exit_timestamp(\stdClass $record): int {
        if (isset($record->exittimestamp)) {
            return (int)$record->exittimestamp;
        }

        if ($this->is_deactivated_record($record)) {
            return (int)$record->timemodified;
        }
        return 0;
    }

    private function rolling_month_windows(int $months): array {
        $timezone = new \DateTimeZone('Asia/Almaty');
        $base = new \DateTimeImmutable('first day of this month 00:00:00', $timezone);
        $windows = [];

        for ($offset = $months - 1; $offset >= 0; $offset--) {
            $start = $base->modify('-' . $offset . ' months');
            $end = $start->modify('last day of this month 23:59:59');
            $windows[] = [
                'key' => $start->format('Y-m'),
                'label' => userdate($end->getTimestamp(), '%b'),
                'start' => $start->getTimestamp(),
                'end' => $end->getTimestamp(),
            ];
        }

        return $windows;
    }

    private function month_key(int $timestamp): string {
        return (new \DateTimeImmutable('@' . $timestamp))
            ->setTimezone(new \DateTimeZone('Asia/Almaty'))
            ->format('Y-m');
    }

    private function rolling_average_active_users(array $records, array $windows): float {
        if (!$windows) {
            return 0.0;
        }

        $total = 0;
        foreach ($windows as $window) {
            $active = 0;
            foreach ($records as $record) {
                $created = (int)$record->timecreated;
                $modified = (int)$record->timemodified;
                $deactivated = $this->is_deactivated_record($record);

                if ($created <= 0 || $created > $window['end']) {
                    continue;
                }

                if ($deactivated && $modified > 0 && $modified <= $window['end']) {
                    continue;
                }

                $active++;
            }
            $total += $active;
        }

        return round($total / count($windows), 2);
    }

    private function company_scope_options(array $filters): array {
        $companyrepo = new company_repository();
        $options = $companyrepo->get_company_options($filters);

        return array_map(static function(array $option): array {
            return [
                'id' => ctype_digit((string)$option['value']) ? (int)$option['value'] : 0,
                'name' => (string)$option['label'],
            ];
        }, $options);
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

    private function is_deactivated_record(\stdClass $record): bool {
        return !empty($record->suspended) || !empty($record->deleted);
    }

    private function turnover_status(float $percent): string {
        if ($percent > 10) {
            return 'danger';
        }
        if ($percent >= 5) {
            return 'warning';
        }
        return 'ok';
    }

    private function new_hire_risk_status(float $percent): string {
        if ($percent > 20) {
            return 'danger';
        }
        if ($percent >= 10) {
            return 'warning';
        }
        return 'ok';
    }
}
