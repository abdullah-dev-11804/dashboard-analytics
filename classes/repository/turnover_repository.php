<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\repository;

use block_dashboardanalytics\name_formatter;

defined('MOODLE_INTERNAL') || die();

class turnover_repository {

    public function staff_dynamics_items(array $filters): array {
        $periods = $this->turnover_period_options();
        $tabs = $this->turnover_company_tabs($filters);
        $items = [];
        $selectedperiod = $this->normalise_turnover_period((string)($filters['turnoverperiod_staffdynamics'] ?? '12months'));
        $selectedtabkey = $this->selected_turnover_tab_key($tabs, (string)($filters['paneltab_staffdynamics'] ?? ''));

        foreach ($tabs as $tab) {
            if ($tab['key'] !== $selectedtabkey) {
                continue;
            }
            $windows = $this->turnover_windows($selectedperiod, $filters);
            $periodstart = $windows ? (int)$windows[0]['start'] : 0;
            $periodend = $windows ? (int)$windows[count($windows) - 1]['end'] : time();
            $tabfilters = $tab['key'] === 'all'
                ? $filters
                : $this->company_scoped_filters($filters, $tab['label'], (int)$tab['companyid']);
            $records = $this->scoped_user_lifecycle_records($tabfilters, 0, $periodend, 'turnoverdynamics' . preg_replace('/[^a-z0-9]/i', '', $tab['key']), $periodstart);

            foreach ($periods as $period) {
                if ($period['key'] !== $selectedperiod) {
                    continue;
                }
                $perioditems = $this->build_staff_dynamics_period_items(
                    $records,
                    $windows,
                    $period['key'],
                    $tab['key'],
                    !empty($filters['showemployeeidentity'])
                );
                $items = array_merge($items, $perioditems);
            }
        }

        return [
            'tabs' => array_map(static function(array $tab): array {
                return [
                    'key' => $tab['key'],
                    'label' => $tab['label'],
                    'active' => $tab['key'] === $selectedtabkey,
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
            $records = $this->scoped_user_lifecycle_records($companyfilters, 0, $periodend, 'turnovercompany' . $company['id'], $periodstart);
            $deactivated = 0;

            foreach ($records as $record) {
                $exitdate = $this->record_exit_timestamp($record);
                if ($exitdate >= $periodstart && $exitdate <= $periodend) {
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

    private function scoped_user_lifecycle_records(array $filters, int $start, int $end, string $prefix, int $exitlogstart = 0): array {
        global $DB;

        $employee = new employee_repository();
        $filter = $employee->scoped_user_filter_sql($filters, 'u', $prefix, [
            'requireactive' => false,
            'requireconfirmed' => true,
            'includesuspended' => true,
            'includedeleted' => true,
        ]);
        $params = $filter['params'];
        $companyrepo = new company_repository();
        $companynamesql = $companyrepo->has_iomad_tables()
            ? "(SELECT GROUP_CONCAT(DISTINCT coturnover.name ORDER BY coturnover.name SEPARATOR ', ')
                  FROM {company_users} cuturnover
                  JOIN {company} coturnover ON coturnover.id = cuturnover.companyid
                 WHERE cuturnover.userid = u.id)"
            : "''";

        $params[$prefix . 'hirefield'] = 'Date';
        $params[$prefix . 'sitefield'] = 'Site';
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
                       u.firstname,
                       u.lastname,
                       u.email,
                       hiredata.data AS hiredateprofile,
                       COALESCE(NULLIF(sitedata.data, ''), '') AS site,
                       COALESCE(NULLIF({$companynamesql}, ''), '') AS companyname,
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
                       END AS exittimestamp,
                       CASE
                           WHEN u.deleted = 1 THEN 'deleted'
                           WHEN u.suspended = 1 THEN 'deactivated'
                           ELSE ''
                       END AS exitsource
                  FROM {user} u
             LEFT JOIN {user_info_field} hirefield
                    ON hirefield.shortname = :{$prefix}hirefield
             LEFT JOIN {user_info_data} hiredata
                    ON hiredata.fieldid = hirefield.id
                   AND hiredata.userid = u.id
             LEFT JOIN {user_info_field} sitefield
                    ON sitefield.shortname = :{$prefix}sitefield
             LEFT JOIN {user_info_data} sitedata
                    ON sitedata.fieldid = sitefield.id
                   AND sitedata.userid = u.id
                 WHERE " . implode(' AND ', $where);

        $records = $DB->get_records_sql($sql, $params);
        $this->append_company_change_exit_records($records, $filters, $exitlogstart, $end, $prefix);
        return $records;
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
            ['key' => 'customrange', 'label' => get_string('forecast:period:customrange', 'block_dashboardanalytics')],
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

    private function selected_turnover_tab_key(array $tabs, string $requested): string {
        foreach ($tabs as $tab) {
            if ($requested !== '' && $tab['key'] === $requested) {
                return $requested;
            }
        }

        foreach ($tabs as $tab) {
            if (!empty($tab['active'])) {
                return (string)$tab['key'];
            }
        }

        return (string)(($tabs[0] ?? [])['key'] ?? 'all');
    }

    private function normalise_turnover_period(string $period): string {
        $period = strtolower(trim($period));
        $map = [
            '3' => '90days',
            '3m' => '90days',
            '6' => '6months',
            '6m' => '6months',
            '12' => '12months',
            '12m' => '12months',
            'last30days' => '30days',
            'last60days' => '60days',
            'last90days' => '90days',
            'last6months' => '6months',
            'last12months' => '12months',
            'custom' => 'customrange',
        ];
        if (isset($map[$period])) {
            return $map[$period];
        }

        $allowed = ['30days', '60days', '90days', '6months', '12months', '3years', 'customrange'];
        return in_array($period, $allowed, true) ? $period : '12months';
    }

    private function turnover_windows(string $periodkey, array $filters = []): array {
        $timezone = new \DateTimeZone('Asia/Almaty');
        $today = new \DateTimeImmutable('today 23:59:59', $timezone);

        if ($periodkey === 'customrange') {
            return $this->custom_turnover_windows($filters, $timezone, $today);
        }

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

    private function custom_turnover_windows(
        array $filters,
        \DateTimeZone $timezone,
        \DateTimeImmutable $today
    ): array {
        $start = $this->custom_turnover_date((string)($filters['turnovercustomstart'] ?? ''), $timezone, true);
        $end = $this->custom_turnover_date((string)($filters['turnovercustomend'] ?? ''), $timezone, false);

        if (!$start && !$end) {
            $end = $today;
            $start = $today->modify('-29 days')->setTime(0, 0, 0);
        } else if (!$start) {
            $start = $end->modify('-29 days')->setTime(0, 0, 0);
        } else if (!$end) {
            $end = $start->modify('+29 days')->setTime(23, 59, 59);
        }

        if ($start > $end) {
            [$start, $end] = [
                $end->setTime(0, 0, 0),
                $start->setTime(23, 59, 59),
            ];
        }

        $days = max(1, (int)ceil(($end->getTimestamp() - $start->getTimestamp() + 1) / DAYSECS));
        if ($days <= 90) {
            return $this->chunk_windows($start, $end, 7);
        }

        if ($days <= 730) {
            $windows = [];
            $cursor = $start->modify('first day of this month')->setTime(0, 0, 0);
            $limit = 0;
            while ($cursor <= $end && $limit < 36) {
                $windowstart = $cursor < $start ? $start : $cursor;
                $windowend = $cursor->modify('last day of this month')->setTime(23, 59, 59);
                if ($windowend > $end) {
                    $windowend = $end;
                }
                $windows[] = [
                    'key' => $windowstart->format('Y-m-d'),
                    'label' => $this->turnover_interval_label($windowstart, $windowend, count($windows) < 2 || $cursor->format('n') === '1'),
                    'start' => $windowstart->getTimestamp(),
                    'end' => $windowend->getTimestamp(),
                ];
                $cursor = $cursor->modify('+1 month');
                $limit++;
            }
            return $windows;
        }

        $windows = [];
        $cursor = $start->setDate((int)$start->format('Y'), 1, 1)->setTime(0, 0, 0);
        $limit = 0;
        while ($cursor <= $end && $limit < 10) {
            $windowstart = $cursor < $start ? $start : $cursor;
            $windowend = $cursor->modify('last day of December this year')->setTime(23, 59, 59);
            if ($windowend > $end) {
                $windowend = $end;
            }
            $windows[] = [
                'key' => $windowstart->format('Y'),
                'label' => $this->turnover_interval_label($windowstart, $windowend, true),
                'start' => $windowstart->getTimestamp(),
                'end' => $windowend->getTimestamp(),
            ];
            $cursor = $cursor->modify('+1 year');
            $limit++;
        }

        return $windows;
    }

    private function custom_turnover_date(string $value, \DateTimeZone $timezone, bool $startofday): ?\DateTimeImmutable {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $time = $startofday ? '00:00:00' : '23:59:59';
        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value . ' ' . $time, $timezone);
        return $date ?: null;
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

    private function build_staff_dynamics_period_items(
        array $records,
        array $windows,
        string $periodkey,
        string $groupkey,
        bool $showidentity
    ): array {
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
                'movementrows' => $this->staff_movement_rows_for_window($records, $window, $showidentity),
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

    private function staff_movement_rows_for_window(array $records, array $window, bool $showidentity): array {
        $rows = [];
        foreach ($records as $record) {
            $hiredate = $this->record_hire_timestamp($record);
            $exitdate = $this->record_exit_timestamp($record);

            if ($hiredate >= $window['start'] && $hiredate <= $window['end']) {
                $rows[] = $this->staff_movement_row($record, 'joined', $hiredate, $hiredate, $showidentity);
            }

            if ($exitdate > 0 && $exitdate >= $window['start'] && $exitdate <= $window['end']) {
                $rows[] = $this->staff_movement_row($record, 'left', $exitdate, $hiredate, $showidentity);
            }
        }

        usort($rows, static function(array $a, array $b): int {
            $datecomparison = ((int)$a['_sortdate']) <=> ((int)$b['_sortdate']);
            if ($datecomparison !== 0) {
                return $datecomparison;
            }
            return strnatcasecmp((string)$a['employee'], (string)$b['employee']);
        });

        foreach ($rows as $index => $row) {
            unset($rows[$index]['_sortdate']);
        }

        return $rows;
    }

    private function staff_movement_row(\stdClass $record, string $eventkey, int $eventdate, int $hiredate, bool $showidentity): array {
        $tenureanchor = $eventkey === 'left' ? $eventdate : time();
        $tenure = $hiredate > 0 && $tenureanchor >= $hiredate ? (int)floor(($tenureanchor - $hiredate) / DAYSECS) : 0;
        $eventdetail = '';
        if ($eventkey === 'left') {
            $source = (string)($record->exitsource ?? '');
            if ($source === 'companychange') {
                $eventdetail = get_string('turnover:eventcompanychange', 'block_dashboardanalytics');
            } else if ($source === 'deleted') {
                $eventdetail = get_string('turnover:eventdeleted', 'block_dashboardanalytics');
            } else {
                $eventdetail = get_string('turnover:eventdeactivated', 'block_dashboardanalytics');
            }
        }

        $showrecordidentity = $showidentity && empty($record->deleted);
        $row = [
            '_sortdate' => $eventdate,
            'employee' => $showrecordidentity ? name_formatter::last_first($record) : get_string('hiddenuser'),
            'site' => trim((string)($record->site ?? '')) !== '' ? format_string((string)$record->site) : get_string('label:unassigned', 'block_dashboardanalytics'),
            'company' => trim((string)($record->companyname ?? '')) !== '' ? format_string((string)$record->companyname) : get_string('label:unassigned', 'block_dashboardanalytics'),
            'event' => $eventkey === 'joined'
                ? get_string('turnover:joined', 'block_dashboardanalytics')
                : get_string('turnover:left', 'block_dashboardanalytics'),
            'eventkey' => $eventkey,
            'eventdetail' => $eventdetail,
            'date' => userdate($eventdate, get_string('strftimedate', 'langconfig')),
            'tenure' => $tenure,
        ];
        if ($showrecordidentity) {
            $row['profileurl'] = (new \moodle_url('/user/profile.php', ['id' => (int)$record->id]))->out(false);
        }

        return $row;
    }

    private function record_hire_timestamp(\stdClass $record): int {
        if (isset($record->hiretimestamp) && (int)$record->hiretimestamp > 0) {
            return $this->normalise_profile_timestamp((int)$record->hiretimestamp);
        }

        $profilevalue = trim((string)($record->hiredateprofile ?? ''));
        if ($profilevalue !== '') {
            if (ctype_digit($profilevalue)) {
                $timestamp = $this->normalise_profile_timestamp((int)$profilevalue);
                if ($timestamp > 0) {
                    return $timestamp;
                }
            }
            $profiledate = $this->parse_profile_date($profilevalue);
            if ($profiledate > 0) {
                return $profiledate;
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

    private function normalise_profile_timestamp(int $timestamp): int {
        if ($timestamp > 9999999999) {
            $timestamp = (int)floor($timestamp / 1000);
        }

        return $timestamp;
    }

    private function append_company_change_exit_records(array &$records, array $filters, int $start, int $end, string $prefix): void {
        global $DB;

        $companyids = array_values(array_filter(array_map('intval', $filters['companyids'] ?? [])));
        if (count($companyids) !== 1 || !$this->table_exists('logstore_standard_log')) {
            return;
        }

        $companyid = reset($companyids);
        if ($companyid <= 0) {
            return;
        }

        $employee = new employee_repository();
        $scopefilters = $filters;
        unset($scopefilters['companyids'], $scopefilters['companies']);
        $filter = $employee->scoped_user_filter_sql($scopefilters, 'u', $prefix . 'companyexit', [
            'requireactive' => false,
            'requireconfirmed' => true,
            'includesuspended' => true,
            'includedeleted' => true,
        ]);

        $params = $filter['params'];
        $params[$prefix . 'companyexitfield'] = 'Date';
        $params[$prefix . 'companyexitsitefield'] = 'Site';
        $companyname = $this->company_name_for_id($companyid);
        if ($start > 0) {
            $params[$prefix . 'companyexitstart'] = $start;
        }
        if ($end > 0) {
            $params[$prefix . 'companyexitend'] = $end;
        }

        $likes = $this->company_exit_payload_likes($params, $prefix, $companyid);
        $eventlikes = [
            $DB->sql_like('l.eventname', ':' . $prefix . 'companyexiteventcompany', false, false),
            $DB->sql_like('l.eventname', ':' . $prefix . 'companyexiteventassign', false, false),
            $DB->sql_like('l.eventname', ':' . $prefix . 'companyexiteventremove', false, false),
            $DB->sql_like('l.eventname', ':' . $prefix . 'companyexiteventupdate', false, false),
        ];
        $params[$prefix . 'companyexiteventcompany'] = '%company%';
        $params[$prefix . 'companyexiteventassign'] = '%assign%';
        $params[$prefix . 'companyexiteventremove'] = '%remove%';
        $params[$prefix . 'companyexiteventupdate'] = '%update%';

        $where = [
            $filter['sql'],
            'l.timecreated > 0',
            '(' . implode(' OR ', $likes) . ')',
            '(' . implode(' OR ', $eventlikes) . ")",
        ];
        if ($start > 0) {
            $where[] = "l.timecreated >= :{$prefix}companyexitstart";
        }
        if ($end > 0) {
            $where[] = "l.timecreated <= :{$prefix}companyexitend";
        }

        $sql = "SELECT l.id AS logid,
                       l.timecreated AS companyexittimestamp,
                       l.eventname,
                       l.action,
                       l.target,
                       l.other,
                       u.id,
                       u.timecreated,
                       u.timemodified,
                       u.suspended,
                       u.deleted,
                       u.firstname,
                       u.lastname,
                       u.email,
                       hiredata.data AS hiredateprofile,
                       COALESCE(NULLIF(sitedata.data, ''), '') AS site,
                       CASE
                           WHEN hiredata.data REGEXP '^[0-9]+$' AND CAST(hiredata.data AS UNSIGNED) > 0
                               THEN CAST(hiredata.data AS UNSIGNED)
                           WHEN hiredata.data IS NOT NULL AND hiredata.data <> '' AND hiredata.data <> '0'
                               THEN UNIX_TIMESTAMP(hiredata.data)
                           ELSE u.timecreated
                       END AS hiretimestamp,
                       CASE
                           WHEN u.deleted = 1 THEN 'deleted'
                           WHEN u.suspended = 1 THEN 'deactivated'
                           ELSE ''
                       END AS exitsource
                  FROM {logstore_standard_log} l
                  JOIN {user} u
                    ON u.id = CASE
                                WHEN l.relateduserid > 0 THEN l.relateduserid
                                WHEN l.objectid > 0 THEN l.objectid
                                ELSE l.userid
                              END
             LEFT JOIN {user_info_field} hirefield
                    ON hirefield.shortname = :{$prefix}companyexitfield
             LEFT JOIN {user_info_data} hiredata
                    ON hiredata.fieldid = hirefield.id
                   AND hiredata.userid = u.id
             LEFT JOIN {user_info_field} sitefield
                    ON sitefield.shortname = :{$prefix}companyexitsitefield
             LEFT JOIN {user_info_data} sitedata
                    ON sitedata.fieldid = sitefield.id
                   AND sitedata.userid = u.id
                 WHERE " . implode(' AND ', $where) . "
              ORDER BY l.timecreated ASC";

        foreach ($DB->get_records_sql($sql, $params) as $record) {
            if (!$this->log_entry_indicates_company_exit($record, $companyid)) {
                continue;
            }

            $userid = (int)$record->id;
            $exittimestamp = (int)$record->companyexittimestamp;
            if ($userid <= 0 || $exittimestamp <= 0) {
                continue;
            }

            if (isset($records[$userid])) {
                $currentexit = (int)($records[$userid]->exittimestamp ?? 0);
                if ($currentexit <= 0 || $exittimestamp < $currentexit) {
                    $records[$userid]->exittimestamp = $exittimestamp;
                    $records[$userid]->exitsource = 'companychange';
                    if ($companyname !== '') {
                        $records[$userid]->companyname = $companyname;
                    }
                }
                continue;
            }

            $record->exittimestamp = $exittimestamp;
            $record->exitsource = 'companychange';
            $record->companyname = $companyname;
            $records[$userid] = $record;
        }
    }

    private function company_name_for_id(int $companyid): string {
        global $DB;

        if ($companyid <= 0 || !$this->table_exists('company')) {
            return '';
        }

        return (string)$DB->get_field('company', 'name', ['id' => $companyid], IGNORE_MISSING);
    }

    private function log_entry_indicates_company_exit(\stdClass $record, int $companyid): bool {
        $event = strtolower((string)($record->eventname ?? ''));
        $action = strtolower((string)($record->action ?? ''));
        $target = strtolower((string)($record->target ?? ''));
        $payload = $this->decode_log_payload((string)($record->other ?? ''));

        $oldcompany = $this->company_id_from_payload($payload, ['oldcompanyid', 'previouscompanyid', 'fromcompanyid', 'sourcecompanyid']);
        $newcompany = $this->company_id_from_payload($payload, ['newcompanyid', 'tocompanyid', 'destinationcompanyid']);
        if ($oldcompany === $companyid && $newcompany !== $companyid) {
            return true;
        }

        $eventtext = $event . ' ' . $action . ' ' . $target;
        $isremoval = preg_match('/unassign|remove|delete|left|leave/', $eventtext) === 1
            || ($newcompany > 0 && preg_match('/move|transfer|change|update/', $eventtext) === 1);
        $genericcompany = $this->company_id_from_payload($payload, ['companyid', 'company']);

        return $genericcompany === $companyid && $isremoval && $newcompany !== $companyid;
    }

    private function company_id_from_payload(array $payload, array $keys): int {
        foreach ($keys as $key) {
            if (isset($payload[$key]) && is_numeric($payload[$key])) {
                return (int)$payload[$key];
            }
        }
        return 0;
    }

    private function company_exit_payload_likes(array &$params, string $prefix, int $companyid): array {
        global $DB;

        $keys = ['companyid', 'company', 'oldcompanyid', 'previouscompanyid', 'fromcompanyid', 'sourcecompanyid'];
        $likes = [];
        foreach ($keys as $index => $key) {
            $base = $prefix . 'companyexitpayload' . $index;
            $value = (string)$companyid;
            $params[$base . 'jsonnum'] = '%"' . $DB->sql_like_escape($key) . '":' . $value . '%';
            $params[$base . 'jsonstr'] = '%"' . $DB->sql_like_escape($key) . '":"' . $DB->sql_like_escape($value) . '"%';
            $params[$base . 'sernum'] = '%s:' . strlen($key) . ':"' . $DB->sql_like_escape($key) . '";i:' . $value . ';%';
            $params[$base . 'serstr'] = '%s:' . strlen($key) . ':"' . $DB->sql_like_escape($key) . '";s:' . strlen($value) . ':"' . $DB->sql_like_escape($value) . '";%';
            $likes[] = $DB->sql_like('l.other', ':' . $base . 'jsonnum', false, false);
            $likes[] = $DB->sql_like('l.other', ':' . $base . 'jsonstr', false, false);
            $likes[] = $DB->sql_like('l.other', ':' . $base . 'sernum', false, false);
            $likes[] = $DB->sql_like('l.other', ':' . $base . 'serstr', false, false);
        }
        return $likes;
    }

    private function decode_log_payload(string $payload): array {
        $payload = trim($payload);
        if ($payload === '') {
            return [];
        }

        $decoded = json_decode($payload, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $decoded = @unserialize($payload, ['allowed_classes' => false]);
        return is_array($decoded) ? $decoded : [];
    }

    private function parse_profile_date(string $value): int {
        $timezone = new \DateTimeZone('Asia/Almaty');
        $formats = ['!d.m.Y', '!d/m/Y', '!Y-m-d', '!m/d/Y'];
        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value, $timezone);
            if ($date instanceof \DateTimeImmutable) {
                $errors = \DateTimeImmutable::getLastErrors();
                if ($errors !== false && ((int)$errors['warning_count'] > 0 || (int)$errors['error_count'] > 0)) {
                    continue;
                }
                return $date->setTime(0, 0, 0)->getTimestamp();
            }
        }

        return 0;
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
                $created = $this->record_hire_timestamp($record);
                $exitdate = $this->record_exit_timestamp($record);

                if ($created <= 0 || $created > $window['end']) {
                    continue;
                }

                if ($exitdate > 0 && $exitdate <= $window['end']) {
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

    private function table_exists(string $tablename): bool {
        global $DB;

        return $DB->get_manager()->table_exists(new \xmldb_table($tablename));
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
