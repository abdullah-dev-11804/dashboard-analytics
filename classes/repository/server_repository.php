<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\repository;

defined('MOODLE_INTERNAL') || die();

class server_repository {

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
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            if (is_array($load) && isset($load[0])) {
                return round((float)$load[0], 2) . ' load';
            }
        }

        return 'Not available';
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
}
