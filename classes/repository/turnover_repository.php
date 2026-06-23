<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\repository;

defined('MOODLE_INTERNAL') || die();

class turnover_repository {

    public function staff_dynamics_items(array $filters, int $months = 12): array {
        $windows = $this->rolling_month_windows($months);
        $records = $this->scoped_user_lifecycle_records($filters, $windows[0]['start'], $windows[count($windows) - 1]['end'], 'turnoverdynamics');
        $monthly = [];

        foreach ($windows as $window) {
            $monthly[$window['key']] = ['new' => 0, 'deactivated' => 0];
        }

        foreach ($records as $record) {
            $createdkey = $this->month_key((int)$record->timecreated);
            if (isset($monthly[$createdkey])) {
                $monthly[$createdkey]['new']++;
            }

            if ($this->is_deactivated_record($record)) {
                $modifiedkey = $this->month_key((int)$record->timemodified);
                if (isset($monthly[$modifiedkey])) {
                    $monthly[$modifiedkey]['deactivated']++;
                }
            }
        }

        $max = 1;
        foreach ($monthly as $counts) {
            $net = $counts['new'] - $counts['deactivated'];
            $max = max($max, $counts['new'], $counts['deactivated'], abs($net));
        }

        $items = [];
        foreach ($windows as $window) {
            $counts = $monthly[$window['key']];
            $net = $counts['new'] - $counts['deactivated'];

            $items[] = [
                'label' => $window['label'],
                'value' => (string)$net,
                'percent' => 0.0,
                'status' => $net >= 0 ? 'ok' : 'danger',
                'meta' => get_string('turnover:monthsummary', 'block_dashboardanalytics', (object)[
                    'new' => $counts['new'],
                    'deactivated' => $counts['deactivated'],
                    'net' => $net,
                ]),
                'segments' => [
                    [
                        'label' => get_string('turnover:newemployees', 'block_dashboardanalytics'),
                        'value' => (string)$counts['new'],
                        'percent' => round(($counts['new'] / $max) * 100, 1),
                        'status' => 'info',
                    ],
                    [
                        'label' => get_string('turnover:deactivatedemployees', 'block_dashboardanalytics'),
                        'value' => (string)$counts['deactivated'],
                        'percent' => round(($counts['deactivated'] / $max) * 100, 1),
                        'status' => 'danger',
                    ],
                    [
                        'label' => get_string('turnover:nettrend', 'block_dashboardanalytics'),
                        'value' => (string)$net,
                        'percent' => round((abs($net) / $max) * 100, 1),
                        'status' => $net > 0 ? 'ok' : ($net < 0 ? 'danger' : 'warning'),
                    ],
                ],
            ];
        }

        return $items;
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
                       u.deleted
                  FROM {user} u
                 WHERE " . implode(' AND ', $where);

        return $DB->get_records_sql($sql, $params);
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
}
