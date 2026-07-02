<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\repository;

defined('MOODLE_INTERNAL') || die();

class server_metric_repository {
    /** @var string */
    public const TABLE = 'block_da_srvmetric';
    /** @var string */
    public const METRIC_DISK = 'disk';
    /** @var int */
    private const SNAPSHOT_COOLDOWN = 900;

    public function capture_disk_snapshot(): ?array {
        global $CFG, $DB;

        if (!$this->table_exists()) {
            return null;
        }

        $path = !empty($CFG->dataroot) ? $CFG->dataroot : sys_get_temp_dir();
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);
        if (!$total || $total <= 0 || $free === false) {
            return null;
        }

        $used = $total - $free;
        $percent = round(($used / $total) * 100, 2);
        $now = time();

        $latest = $DB->get_record_sql(
            "SELECT *
               FROM {" . self::TABLE . "}
              WHERE metricname = :metricname
           ORDER BY collectedat DESC, id DESC",
            ['metricname' => self::METRIC_DISK],
            IGNORE_MULTIPLE
        );

        if ($latest && ((int)$latest->collectedat + self::SNAPSHOT_COOLDOWN) > $now) {
            return [
                'percent' => (float)$latest->percentvalue,
                'usedbytes' => (int)$latest->usedbytes,
                'totalbytes' => (int)$latest->totalbytes,
                'collectedat' => (int)$latest->collectedat,
            ];
        }

        $record = (object)[
            'metricname' => self::METRIC_DISK,
            'percentvalue' => $percent,
            'usedbytes' => $used,
            'totalbytes' => $total,
            'collectedat' => $now,
        ];
        $DB->insert_record(self::TABLE, $record);

        return [
            'percent' => $percent,
            'usedbytes' => $used,
            'totalbytes' => $total,
            'collectedat' => $now,
        ];
    }

    public function disk_history(int $days = 90): array {
        global $DB;

        if (!$this->table_exists()) {
            return [];
        }

        $since = time() - max(1, $days) * DAYSECS;
        $records = $DB->get_records_sql(
            "SELECT id, percentvalue, usedbytes, totalbytes, collectedat
               FROM {" . self::TABLE . "}
              WHERE metricname = :metricname
                AND collectedat >= :since
           ORDER BY collectedat ASC, id ASC",
            [
                'metricname' => self::METRIC_DISK,
                'since' => $since,
            ]
        );

        return array_map(static function($record): array {
            return [
                'percent' => (float)$record->percentvalue,
                'usedbytes' => (int)$record->usedbytes,
                'totalbytes' => (int)$record->totalbytes,
                'collectedat' => (int)$record->collectedat,
            ];
        }, array_values($records));
    }

    private function table_exists(): bool {
        global $DB;
        return $DB->get_manager()->table_exists(new \xmldb_table(self::TABLE));
    }
}
