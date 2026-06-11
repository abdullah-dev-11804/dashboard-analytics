<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\repository;

defined('MOODLE_INTERNAL') || die();

class server_repository {

    public function admin_kpi_cards(): array {
        return [
            $this->users_online_card(),
            $this->disk_kpi_card(),
            $this->ram_card(),
            $this->cpu_card(),
            $this->db_card(),
            $this->cron_card(),
        ];
    }

    public function disk_card(): array {
        global $CFG;

        $path = !empty($CFG->dataroot) ? $CFG->dataroot : sys_get_temp_dir();
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);

        if (!$total || $total <= 0 || $free === false) {
            return [
                'value' => 'N/A',
                'status' => 'muted',
                'trend' => '',
            ];
        }

        $used = $total - $free;
        $percent = round(($used / $total) * 100);

        return [
            'value' => $percent . '%',
            'status' => $percent >= 90 ? 'danger' : ($percent >= 70 ? 'warning' : 'ok'),
            'trend' => $this->format_bytes($used) . ' / ' . $this->format_bytes($total),
        ];
    }

    public function disk_rows(): array {
        global $CFG, $DB;

        $path = !empty($CFG->dataroot) ? $CFG->dataroot : sys_get_temp_dir();
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);
        $rows = [];

        if ($total && $free !== false) {
            $used = $total - $free;
            $percent = round(($used / $total) * 100);
            $rows[] = $this->metric_row('Disk', $percent . '%', $this->format_bytes($used), $this->format_bytes($total));
        }

        $memoryused = memory_get_usage(true);
        $memorylimit = $this->memory_limit_bytes();
        $memorypercent = $memorylimit > 0 ? round(($memoryused / $memorylimit) * 100) . '%' : '-';
        $rows[] = $this->metric_row('RAM', $memorypercent, $this->format_bytes($memoryused), $memorylimit > 0 ? $this->format_bytes($memorylimit) : 'No PHP limit');

        $rows[] = $this->metric_row('CPU', $this->cpu_load(), '-', '-');

        $dbsize = $this->database_size_bytes();
        if ($dbsize !== null) {
            $rows[] = $this->metric_row('DB', '-', $this->format_bytes($dbsize), '-');
        }

        $rows[] = $this->metric_row('Concurrent users', (string)$this->concurrent_users(), '-', 'Last 5 minutes');
        $rows[] = $this->metric_row('Warning threshold', '70%', '-', '-');
        $rows[] = $this->metric_row('Critical threshold', '90%', '-', '-');

        return [
            'columns' => [
                ['key' => 'metric', 'label' => 'Metric'],
                ['key' => 'current', 'label' => 'Current value'],
                ['key' => 'used', 'label' => 'Used'],
                ['key' => 'total', 'label' => 'Total'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            'rows' => $rows,
            'totalcount' => count($rows),
            'notice' => '',
            'description' => 'Server capacity view from the System/Server slide: Disk, RAM, CPU, DB and concurrent users with 70% warning and 90% critical thresholds. Historical 7-day sparklines and 13-week disk regression require the sental_server_metrics collection table in the next phase.',
        ];
    }

    private function metric_row(string $metric, string $current, string $used, string $total): array {
        $status = 'OK';
        if (substr($current, -1) === '%') {
            $value = (int)$current;
            $status = $value >= 90 ? 'Critical' : ($value >= 70 ? 'Warning' : 'OK');
        }

        return [
            'cells' => [
                ['key' => 'metric', 'value' => $metric],
                ['key' => 'current', 'value' => $current],
                ['key' => 'used', 'value' => $used],
                ['key' => 'total', 'value' => $total],
                ['key' => 'status', 'value' => $status],
            ],
        ];
    }

    private function users_online_card(): array {
        $usersonline = $this->concurrent_users();

        return [
            'label' => 'Users Online',
            'value' => (string)$usersonline,
            'percent' => 0.0,
            'status' => 'info',
            'meta' => 'Live sessions · Updated ' . userdate(time(), '%H:%M'),
        ];
    }

    private function disk_kpi_card(): array {
        $disk = $this->disk_card();

        return [
            'label' => 'Disk',
            'value' => (string)$disk['value'],
            'percent' => $this->numeric_percent((string)$disk['value']),
            'status' => (string)$disk['status'],
            'meta' => 'Storage used · ' . ((string)$disk['trend'] !== '' ? (string)$disk['trend'] . ' · ' : '') . 'Updated ' . userdate(time(), '%H:%M'),
        ];
    }

    private function ram_card(): array {
        $memoryused = memory_get_usage(true);
        $memorylimit = $this->memory_limit_bytes();
        $percent = $memorylimit > 0 ? round(($memoryused / $memorylimit) * 100, 1) : 0.0;
        $status = $memorylimit > 0 ? $this->status_for_percent($percent) : 'muted';

        return [
            'label' => 'RAM',
            'value' => $memorylimit > 0 ? $percent . '%' : 'N/A',
            'percent' => $memorylimit > 0 ? $percent : 0.0,
            'status' => $status,
            'meta' => 'Memory used · ' . $this->format_bytes($memoryused) . ($memorylimit > 0 ? ' / ' . $this->format_bytes($memorylimit) : '') . ' · Updated ' . userdate(time(), '%H:%M'),
        ];
    }

    private function cpu_card(): array {
        $load = $this->cpu_load_average();
        $percent = $this->cpu_percent();
        $status = $percent !== null ? $this->status_for_percent($percent) : 'muted';

        return [
            'label' => 'CPU',
            'value' => $percent !== null ? $percent . '%' : $load,
            'percent' => $percent ?? 0.0,
            'status' => $status,
            'meta' => 'Current load · ' . $load . ' · Updated ' . userdate(time(), '%H:%M'),
        ];
    }

    private function db_card(): array {
        $dbsize = $this->database_size_bytes();

        return [
            'label' => 'DB',
            'value' => $dbsize !== null ? $this->format_bytes($dbsize) : 'N/A',
            'percent' => 0.0,
            'status' => $dbsize !== null ? 'info' : 'muted',
            'meta' => 'Database size · Updated ' . userdate(time(), '%H:%M'),
        ];
    }

    private function cron_card(): array {
        $lastcron = $this->last_cron_run();
        $overdue = $this->overdue_task_count();
        $age = $lastcron > 0 ? time() - $lastcron : null;
        $status = 'muted';
        $value = 'Unknown';

        if ($age !== null) {
            if ($age > 900 || $overdue > 0) {
                $status = $age > 900 || $overdue > 3 ? 'danger' : 'warning';
            } else {
                $status = 'ok';
            }

            if ($overdue > 0) {
                $value = $overdue . ' overdue';
            } else {
                $value = 'Healthy';
            }
        }

        return [
            'label' => 'Cron',
            'value' => $value,
            'percent' => 0.0,
            'status' => $status,
            'meta' => $lastcron > 0 ? 'Last run ' . format_time(time() - $lastcron) . ' ago' : 'Cron timing unavailable',
        ];
    }

    private function database_size_bytes(): ?int {
        global $CFG, $DB;

        if ($DB->get_dbfamily() !== 'mysql') {
            return null;
        }

        $dbname = !empty($CFG->dbname) ? (string)$CFG->dbname : '';
        if ($dbname === '') {
            return null;
        }

        $sql = "SELECT SUM(data_length + index_length)
                  FROM information_schema.tables
                 WHERE table_schema = :dbname";

        try {
            $value = $DB->get_field_sql($sql, ['dbname' => $dbname]);
        } catch (\Throwable $e) {
            return null;
        }

        return $value === false || $value === null ? null : (int)$value;
    }

    private function concurrent_users(): int {
        global $DB;

        if (!$this->table_exists('sessions')) {
            return 0;
        }

        return (int)$DB->count_records_select('sessions', 'timemodified >= :since', ['since' => time() - 300]);
    }

    private function cpu_load(): string {
        $load = $this->cpu_load_average();
        return $load !== null ? $load . ' load' : 'Not available';
    }

    private function cpu_load_average(): ?string {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            if (is_array($load) && isset($load[0])) {
                return (string)round((float)$load[0], 2);
            }
        }

        return null;
    }

    private function memory_limit_bytes(): int {
        $limit = ini_get('memory_limit');
        if ($limit === false || $limit === '' || $limit === '-1') {
            return 0;
        }

        $unit = strtolower(substr($limit, -1));
        $value = (float)$limit;
        if ($unit === 'g') {
            $value *= 1024 * 1024 * 1024;
        } else if ($unit === 'm') {
            $value *= 1024 * 1024;
        } else if ($unit === 'k') {
            $value *= 1024;
        }

        return (int)$value;
    }

    private function table_exists(string $tablename): bool {
        global $CFG, $DB;

        require_once($CFG->libdir . '/xmldb/xmldb_table.php');
        return $DB->get_manager()->table_exists(new \xmldb_table($tablename));
    }

    private function format_bytes(float $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return round($bytes, $index === 0 ? 0 : 1) . ' ' . $units[$index];
    }

    private function status_for_percent(float $percent): string {
        if ($percent >= 90) {
            return 'danger';
        }
        if ($percent >= 70) {
            return 'warning';
        }
        return 'ok';
    }

    private function numeric_percent(string $value): float {
        return preg_match('/^\s*([0-9]+(?:\.[0-9]+)?)%$/', $value, $matches) ? (float)$matches[1] : 0.0;
    }

    private function cpu_percent(): ?float {
        $load = $this->cpu_load_average();
        if ($load === null) {
            return null;
        }

        $cores = 0;
        if (function_exists('shell_exec')) {
            $cores = (int)trim((string)@\shell_exec('nproc 2>/dev/null'));
        }
        if ($cores <= 0) {
            $cores = 1;
        }

        return round(min(100, (((float)$load) / $cores) * 100), 1);
    }

    private function last_cron_run(): int {
        global $DB;

        if (!$this->table_exists('config')) {
            return 0;
        }

        $value = $DB->get_field('config', 'value', ['name' => 'lastcron'], IGNORE_MISSING);
        return $value === false || $value === null ? 0 : (int)$value;
    }

    private function overdue_task_count(): int {
        global $DB;

        if (!$this->table_exists('task_scheduled')) {
            return 0;
        }

        try {
            return (int)$DB->count_records_select('task_scheduled', 'disabled = 0 AND nextruntime > 0 AND nextruntime < :now', ['now' => time()]);
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
