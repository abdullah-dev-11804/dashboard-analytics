<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\repository;

defined('MOODLE_INTERNAL') || die();

class server_repository {
    /**
     * @var server_metric_repository|null
     */
    private $metrics = null;

    private function metric_repository(): server_metric_repository {
        if ($this->metrics === null) {
            $this->metrics = new server_metric_repository();
        }
        return $this->metrics;
    }

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

    public function inline_capacity_rows(): array {
        global $CFG;

        $path = !empty($CFG->dataroot) ? $CFG->dataroot : sys_get_temp_dir();
        $disktotal = @disk_total_space($path);
        $diskfree = @disk_free_space($path);
        $diskused = ($disktotal && $diskfree !== false) ? ($disktotal - $diskfree) : 0;
        $diskpercent = ($disktotal && $disktotal > 0 && $diskfree !== false) ? round(($diskused / $disktotal) * 100, 1) : null;

        $memoryused = memory_get_usage(true);
        $memorylimit = $this->memory_limit_bytes();
        $memorypercent = $memorylimit > 0 ? round(($memoryused / $memorylimit) * 100, 1) : null;

        $cpuload = $this->cpu_load_average();
        $cpupercent = $this->cpu_percent();
        $cpucores = $this->cpu_core_count();

        $dbsize = $this->database_size_bytes();
        $dbpercent = ($dbsize !== null && $disktotal && $disktotal > 0) ? round(($dbsize / $disktotal) * 100, 1) : null;

        $rows = [
            $this->inline_metric_row(
                'Disk',
                $diskpercent,
                $diskpercent !== null ? $diskpercent . '%' : 'N/A',
                $diskpercent !== null ? $this->format_bytes($diskused) . ' of ' . $this->format_bytes($disktotal) . ' · ' . $this->status_word($diskpercent) : 'Disk metrics unavailable',
                $diskpercent !== null ? $this->status_for_percent($diskpercent) : 'muted'
            ),
            $this->inline_metric_row(
                'RAM',
                $memorypercent,
                $memorypercent !== null ? $memorypercent . '%' : 'N/A',
                $memorypercent !== null ? $this->format_bytes($memoryused) . ' of ' . $this->format_bytes($memorylimit) . ' · ' . $this->status_word($memorypercent) : 'Memory limit unavailable',
                $memorypercent !== null ? $this->status_for_percent($memorypercent) : 'muted'
            ),
            $this->inline_metric_row(
                'CPU (1-min)',
                $cpupercent,
                $cpupercent !== null ? $cpupercent . '%' : 'N/A',
                'Load avg ' . ($cpuload !== null ? $cpuload : 'N/A') . ($cpucores > 0 ? ' · ' . $cpucores . ' cores' : '') . ' · ' . ($cpupercent !== null ? $this->status_word($cpupercent) : 'Unavailable'),
                $cpupercent !== null ? $this->status_for_percent($cpupercent) : 'muted'
            ),
            $this->inline_metric_row(
                'Database',
                $dbpercent,
                $dbpercent !== null ? $dbpercent . '%' : ($dbsize !== null ? $this->format_bytes($dbsize) : 'N/A'),
                $dbsize !== null
                    ? $this->format_bytes($dbsize) . ($disktotal && $disktotal > 0 ? ' of ' . $this->format_bytes($disktotal) : '') . ' · Monitor'
                    : 'Database size unavailable',
                $dbpercent !== null ? 'info' : ($dbsize !== null ? 'info' : 'muted')
            ),
        ];

        return [
            'columns' => [
                ['key' => 'metric', 'label' => 'Metric'],
                ['key' => 'value', 'label' => 'Value'],
                ['key' => 'meta', 'label' => 'Meta'],
                ['key' => 'percent', 'label' => 'Percent'],
                ['key' => 'statuskey', 'label' => 'Status key'],
            ],
            'rows' => $rows,
            'totalcount' => count($rows),
            'notice' => '',
            'description' => 'sental_server_metrics — latest collected_at per metric. Amber at 70%, Red at 90%.',
        ];
    }

    public function capacity_gauge_items(): array {
        global $CFG;

        $this->metric_repository()->capture_disk_snapshot();

        $path = !empty($CFG->dataroot) ? $CFG->dataroot : sys_get_temp_dir();
        $disktotal = @disk_total_space($path);
        $diskfree = @disk_free_space($path);
        $diskused = ($disktotal && $diskfree !== false) ? ($disktotal - $diskfree) : 0;
        $diskpercent = ($disktotal && $disktotal > 0 && $diskfree !== false) ? round(($diskused / $disktotal) * 100, 1) : null;

        $memoryused = memory_get_usage(true);
        $memorylimit = $this->memory_limit_bytes();
        $memorypercent = $memorylimit > 0 ? round(($memoryused / $memorylimit) * 100, 1) : null;

        $cpuload = $this->cpu_load_average();
        $cpupercent = $this->cpu_percent();
        $cpucores = $this->cpu_core_count();

        $dbsize = $this->database_size_bytes();
        $dbpercent = ($dbsize !== null && $disktotal && $disktotal > 0) ? round(($dbsize / $disktotal) * 100, 1) : null;

        $concurrentusers = $this->concurrent_users();
        $activeusers = max(1, $this->active_user_count());
        $concurrentpercent = round(min(100, ($concurrentusers / $activeusers) * 100), 1);

        return [
            $this->visual_item(
                get_string('metric:disk', 'block_dashboardanalytics'),
                $diskpercent !== null ? $diskpercent . '%' : 'N/A',
                $diskpercent ?? 0.0,
                $diskpercent !== null ? $this->status_for_percent($diskpercent) : 'muted',
                $diskpercent !== null
                    ? $this->format_bytes($diskused) . ' / ' . $this->format_bytes($disktotal) . ' · ' . $this->status_word($diskpercent)
                    : get_string('server:meta:unavailable', 'block_dashboardanalytics')
            ),
            $this->visual_item(
                get_string('metric:ram', 'block_dashboardanalytics'),
                $memorypercent !== null ? $memorypercent . '%' : 'N/A',
                $memorypercent ?? 0.0,
                $memorypercent !== null ? $this->status_for_percent($memorypercent) : 'muted',
                $memorypercent !== null
                    ? $this->format_bytes($memoryused) . ' / ' . $this->format_bytes($memorylimit) . ' · ' . $this->status_word($memorypercent)
                    : get_string('server:meta:unavailable', 'block_dashboardanalytics')
            ),
            $this->visual_item(
                get_string('metric:cpu1m', 'block_dashboardanalytics'),
                $cpupercent !== null ? $cpupercent . '%' : 'N/A',
                $cpupercent ?? 0.0,
                $cpupercent !== null ? $this->status_for_percent($cpupercent) : 'muted',
                'Load ' . ($cpuload !== null ? $cpuload : 'N/A') . ($cpucores > 0 ? ' · ' . $cpucores . ' cores' : '')
                    . ' · ' . ($cpupercent !== null ? $this->status_word($cpupercent) : get_string('server:label:monitor', 'block_dashboardanalytics'))
            ),
            $this->visual_item(
                get_string('metric:database', 'block_dashboardanalytics'),
                $dbpercent !== null ? $dbpercent . '%' : ($dbsize !== null ? $this->format_bytes($dbsize) : 'N/A'),
                $dbpercent ?? 0.0,
                $dbpercent !== null ? 'info' : ($dbsize !== null ? 'info' : 'muted'),
                $dbsize !== null
                    ? $this->format_bytes($dbsize) . ($disktotal && $disktotal > 0 ? ' / ' . $this->format_bytes($disktotal) : '')
                        . ' · ' . get_string('server:label:monitor', 'block_dashboardanalytics')
                    : get_string('server:meta:unavailable', 'block_dashboardanalytics')
            ),
            $this->visual_item(
                get_string('metric:concurrentusers', 'block_dashboardanalytics'),
                $concurrentpercent . '%',
                $concurrentpercent,
                $this->status_for_percent($concurrentpercent),
                $concurrentusers . ' / ' . $activeusers . ' · ' . get_string('server:meta:livesessions', 'block_dashboardanalytics')
            ),
        ];
    }

    public function disk_forecast_items(): array {
        $current = $this->metric_repository()->capture_disk_snapshot();
        $history = $this->metric_repository()->disk_history(90);
        $percent = $current['percent'] ?? null;
        $status = $percent !== null ? $this->status_for_percent($percent) : 'muted';
        $segments = [];

        if ($percent !== null && $history) {
            $anchors = [
                ['label' => '-30d', 'time' => time() - (30 * DAYSECS), 'status' => 'historical'],
                ['label' => '-20d', 'time' => time() - (20 * DAYSECS), 'status' => 'historical'],
                ['label' => '-10d', 'time' => time() - (10 * DAYSECS), 'status' => 'historical'],
                ['label' => 'Now', 'time' => time(), 'status' => 'historical'],
            ];
            foreach ($anchors as $anchor) {
                $point = $this->closest_history_point($history, $anchor['time']) ?: end($history);
                $segments[] = [
                    'label' => $anchor['label'],
                    'value' => round((float)$point['percent'], 1) . '%',
                    'percent' => (float)$point['percent'],
                    'status' => $anchor['status'],
                ];
            }

            $slope = $this->history_slope_per_day($history);
            foreach ([30, 60, 90] as $days) {
                $projected = $percent + ($slope * $days);
                $projected = max(0, min(100, round($projected, 1)));
                $segments[] = [
                    'label' => '+' . $days . 'd',
                    'value' => $projected . '%',
                    'percent' => $projected,
                    'status' => 'projected',
                ];
            }
        }

        $meta = $this->forecast_meta($percent, $history);
        $base = $percent ?? 0.0;

        return [[
            'label' => get_string('metric:disk', 'block_dashboardanalytics'),
            'value' => $percent !== null ? $percent . '%' : 'N/A',
            'percent' => $base,
            'status' => $status,
            'meta' => $meta,
            'segments' => $segments,
        ]];
    }

    private function closest_history_point(array $history, int $timestamp): ?array {
        $closest = null;
        foreach ($history as $point) {
            if ((int)$point['collectedat'] <= $timestamp) {
                $closest = $point;
            } else {
                break;
            }
        }

        return $closest ?: ($history[0] ?? null);
    }

    private function history_slope_per_day(array $history): float {
        if (count($history) < 2) {
            return 0.0;
        }

        $first = (int)$history[0]['collectedat'];
        $sumx = 0.0;
        $sumy = 0.0;
        $sumxy = 0.0;
        $sumxx = 0.0;
        $n = 0;

        foreach ($history as $point) {
            $x = ((int)$point['collectedat'] - $first) / DAYSECS;
            $y = (float)$point['percent'];
            $sumx += $x;
            $sumy += $y;
            $sumxy += ($x * $y);
            $sumxx += ($x * $x);
            $n++;
        }

        $denominator = ($n * $sumxx) - ($sumx * $sumx);
        if ($denominator == 0.0) {
            return 0.0;
        }

        return (($n * $sumxy) - ($sumx * $sumy)) / $denominator;
    }

    private function forecast_meta(?float $percent, array $history): string {
        if ($percent === null) {
            return get_string('server:forecast:unavailable', 'block_dashboardanalytics');
        }
        if ($percent >= 90) {
            return get_string('server:forecast:criticalnow', 'block_dashboardanalytics');
        }
        if (count($history) < 2) {
            return get_string('server:forecast:collecting', 'block_dashboardanalytics', userdate(time(), get_string('strftimedate', 'langconfig')));
        }

        $slope = $this->history_slope_per_day($history);
        $latest = end($history);
        $latestlabel = $latest
            ? userdate((int)$latest['collectedat'], get_string('strftimedatetime', 'langconfig'))
            : '';

        if ($slope <= 0) {
            return get_string('server:forecast:stable', 'block_dashboardanalytics', $latestlabel);
        }

        $dayscritical = (90 - $percent) / $slope;
        if ($dayscritical <= 0) {
            return get_string('server:forecast:criticalnow', 'block_dashboardanalytics');
        }

        $weekscritical = max(1, (int)round($dayscritical / 7));
        return get_string('server:forecast:projectedcritical', 'block_dashboardanalytics', (object)[
            'weeks' => $weekscritical,
            'rate' => round($slope, 2),
            'latest' => $latestlabel,
        ]);
    }

    public function error_summary_items(): array {
        global $DB, $CFG;

        $since = time() - WEEKSECS;
        $logsignals = $this->error_log_signals($since);

        $qrfailcount = 0;
        $qrlatest = 0;
        if ($this->table_exists('local_ncasign_jobs')) {
            try {
                $record = $DB->get_record_sql(
                    "SELECT COUNT(1) AS totalcount,
                            MAX(COALESCE(timemodified, timecreated)) AS latestts
                       FROM {local_ncasign_jobs}
                      WHERE status = :status
                        AND COALESCE(timemodified, timecreated) >= :since",
                    ['status' => 'finalize_failed', 'since' => $since]
                );
                $qrfailcount = (int)($record->totalcount ?? 0);
                $qrlatest = (int)($record->latestts ?? 0);
            } catch (\Throwable $e) {
                $qrfailcount = 0;
                $qrlatest = 0;
            }
        }
        $qrfailcount = max($qrfailcount, (int)($logsignals['qr']['count'] ?? 0));
        $qrlatest = max($qrlatest, (int)($logsignals['qr']['latest'] ?? 0));

        $smtpconfigured = !empty($CFG->smtphosts);
        $emailfailcount = (int)($logsignals['email']['count'] ?? 0);
        $emaillatest = (int)($logsignals['email']['latest'] ?? 0);

        $edsrejectcount = 0;
        $edsrejectlatest = 0;
        if ($this->table_exists('local_ncasign_signers')) {
            try {
                $record = $DB->get_record_sql(
                    "SELECT COUNT(1) AS totalcount,
                            MAX(timemodified) AS latestts
                       FROM {local_ncasign_signers}
                      WHERE verificationstatus IS NOT NULL
                        AND verificationstatus <> ''
                        AND verificationstatus NOT IN ('valid', 'trusted', 'good', 'signed_manual')
                        AND timemodified >= :since",
                    ['since' => $since]
                );
                $edsrejectcount = (int)($record->totalcount ?? 0);
                $edsrejectlatest = (int)($record->latestts ?? 0);
            } catch (\Throwable $e) {
                $edsrejectcount = 0;
                $edsrejectlatest = 0;
            }
        }
        $edsrejectcount = max($edsrejectcount, (int)($logsignals['edsreject']['count'] ?? 0));
        $edsrejectlatest = max($edsrejectlatest, (int)($logsignals['edsreject']['latest'] ?? 0));

        $cronoverruns = $this->overdue_task_count();
        $cronlatest = (int)($logsignals['cron']['latest'] ?? 0);
        $cronoverruns = max($cronoverruns, (int)($logsignals['cron']['count'] ?? 0));

        $quilgotimeouts = 0;
        if ($this->table_exists('quizaccess_quilgo_reports')) {
            try {
                $quilgotimeouts = (int)$DB->count_records_select('quizaccess_quilgo_reports',
                    $DB->sql_like('error_reason', ':timeout', false, false),
                    ['timeout' => '%timeout%']
                );
            } catch (\Throwable $e) {
                $quilgotimeouts = 0;
            }
        }
        $quilgotimeouts = max($quilgotimeouts, (int)($logsignals['quilgo']['count'] ?? 0));
        $quilgolatest = (int)($logsignals['quilgo']['latest'] ?? 0);

        $storageerrors = 0;
        if ($this->table_exists('local_ncasign_jobs')) {
            try {
                $likefile = $DB->sql_like('autosignnote', ':fileerror', false, false);
                $likestorage = $DB->sql_like('autosignnote', ':storageerror', false, false);
                $storageerrors = (int)$DB->count_records_sql(
                    "SELECT COUNT(1)
                       FROM {local_ncasign_jobs}
                      WHERE COALESCE(timemodified, timecreated) >= :since
                        AND ({$likefile} OR {$likestorage})",
                    [
                        'since' => $since,
                        'fileerror' => '%file%',
                        'storageerror' => '%storage%',
                    ]
                );
            } catch (\Throwable $e) {
                $storageerrors = 0;
            }
        }
        $storageerrors = max($storageerrors, (int)($logsignals['storage']['count'] ?? 0));
        $storagelatest = (int)($logsignals['storage']['latest'] ?? 0);

        return [
            $this->visual_item(
                get_string('server:error:qr', 'block_dashboardanalytics'),
                (string)$qrfailcount,
                $qrfailcount > 0 ? 100.0 : 0.0,
                $qrfailcount > 0 ? 'danger' : 'ok',
                $qrfailcount > 0 ? $this->latest_age_text($qrlatest) : get_string('server:error:clear', 'block_dashboardanalytics')
            ),
            $this->visual_item(
                get_string('server:error:email', 'block_dashboardanalytics'),
                (string)$emailfailcount,
                $emailfailcount > 0 ? 100.0 : 0.0,
                $emailfailcount > 0 ? 'danger' : ($smtpconfigured ? 'ok' : 'warning'),
                $emailfailcount > 0
                    ? $this->latest_age_text($emaillatest)
                    : ($smtpconfigured ? get_string('server:error:clear', 'block_dashboardanalytics') : get_string('server:error:smtpmissing', 'block_dashboardanalytics'))
            ),
            $this->visual_item(
                get_string('server:error:edsreject', 'block_dashboardanalytics'),
                (string)$edsrejectcount,
                $edsrejectcount > 0 ? 100.0 : 0.0,
                $edsrejectcount > 0 ? 'danger' : 'ok',
                $edsrejectcount > 0 ? $this->latest_age_text($edsrejectlatest) : get_string('server:error:clear', 'block_dashboardanalytics')
            ),
            $this->visual_item(
                get_string('server:error:cron', 'block_dashboardanalytics'),
                (string)$cronoverruns,
                min(100, $cronoverruns * 20),
                $cronoverruns > 3 ? 'danger' : ($cronoverruns > 0 ? 'warning' : 'ok'),
                $cronoverruns > 0
                    ? ($cronlatest > 0 ? $this->latest_age_text($cronlatest) : $this->cron_meta_text())
                    : get_string('server:error:clear', 'block_dashboardanalytics')
            ),
            $this->visual_item(
                get_string('server:error:quilgo', 'block_dashboardanalytics'),
                (string)$quilgotimeouts,
                $quilgotimeouts > 0 ? 100.0 : 0.0,
                $quilgotimeouts > 0 ? 'warning' : 'ok',
                $quilgotimeouts > 0
                    ? ($quilgolatest > 0 ? $this->latest_age_text($quilgolatest) : get_string('server:error:quilgotimeoutmeta', 'block_dashboardanalytics'))
                    : get_string('server:error:clear', 'block_dashboardanalytics')
            ),
            $this->visual_item(
                get_string('server:error:storage', 'block_dashboardanalytics'),
                (string)$storageerrors,
                $storageerrors > 0 ? 100.0 : 0.0,
                $storageerrors > 0 ? 'warning' : 'ok',
                $storageerrors > 0
                    ? ($storagelatest > 0 ? $this->latest_age_text($storagelatest) : get_string('server:error:storagemeta', 'block_dashboardanalytics'))
                    : get_string('server:error:clear', 'block_dashboardanalytics')
            ),
        ];
    }

    public function system_settings_items(): array {
        global $CFG, $DB;

        $release = '';
        if (!empty($CFG->release)) {
            $release = (string)$CFG->release;
        } else if ($this->table_exists('config')) {
            $release = (string)$DB->get_field('config', 'value', ['name' => 'release'], IGNORE_MISSING);
        }

        $phpversion = PHP_VERSION;
        $memorylimit = ini_get('memory_limit');
        $lastcron = $this->last_cron_run();
        $cronage = $lastcron > 0 ? time() - $lastcron : null;
        $cronstatus = $cronage === null ? 'warning' : ($cronage > 900 ? 'warning' : 'ok');
        $cronvalue = $cronage === null
            ? get_string('server:settings:cronunknown', 'block_dashboardanalytics')
            : get_string('server:settings:cronvalue', 'block_dashboardanalytics', format_time($cronage));

        $cachebackend = $this->cache_backend_label();
        $smtpvalue = !empty($CFG->smtphosts)
            ? $CFG->smtphosts . (!empty($CFG->smtpsecure) ? ' · ' . strtoupper((string)$CFG->smtpsecure) : '')
            : get_string('server:settings:notconfigured', 'block_dashboardanalytics');

        $loglifetime = (int)get_config('moodle', 'loglifetime');
        $logvalue = $loglifetime > 0
            ? get_string('server:settings:logdays', 'block_dashboardanalytics', (int)round($loglifetime / DAYSECS))
            : get_string('server:settings:logunlimited', 'block_dashboardanalytics');

        return [
            $this->visual_item(
                get_string('server:setting:moodle', 'block_dashboardanalytics'),
                $release !== '' ? $release : get_string('server:settings:unknown', 'block_dashboardanalytics'),
                0.0,
                'ok',
                get_string('server:settings:latestrecommended', 'block_dashboardanalytics')
            ),
            $this->visual_item(
                get_string('server:setting:phpversion', 'block_dashboardanalytics'),
                $phpversion,
                0.0,
                version_compare($phpversion, '8.1.0', '>=') ? 'ok' : 'warning',
                version_compare($phpversion, '8.1.0', '>=') ? get_string('label:ok', 'block_dashboardanalytics') : get_string('label:warning', 'block_dashboardanalytics')
            ),
            $this->visual_item(
                get_string('server:setting:phpmemory', 'block_dashboardanalytics'),
                (string)$memorylimit,
                0.0,
                $this->memory_limit_bytes() >= (256 * 1024 * 1024) ? 'ok' : 'warning',
                $this->memory_limit_bytes() >= (256 * 1024 * 1024)
                    ? get_string('server:settings:memoryok', 'block_dashboardanalytics')
                    : get_string('server:settings:memorylow', 'block_dashboardanalytics')
            ),
            $this->visual_item(
                get_string('server:setting:cron', 'block_dashboardanalytics'),
                $cronvalue,
                0.0,
                $cronstatus,
                $cronstatus === 'ok' ? get_string('label:ok', 'block_dashboardanalytics') : get_string('label:urgent', 'block_dashboardanalytics')
            ),
            $this->visual_item(
                get_string('server:setting:cache', 'block_dashboardanalytics'),
                $cachebackend,
                0.0,
                $cachebackend === get_string('server:settings:cachedefault', 'block_dashboardanalytics') ? 'info' : 'ok',
                $cachebackend === get_string('server:settings:cachedefault', 'block_dashboardanalytics')
                    ? get_string('server:label:monitor', 'block_dashboardanalytics')
                    : get_string('label:ok', 'block_dashboardanalytics')
            ),
            $this->visual_item(
                get_string('server:setting:smtp', 'block_dashboardanalytics'),
                $smtpvalue,
                0.0,
                !empty($CFG->smtphosts) ? 'ok' : 'warning',
                !empty($CFG->smtphosts) ? get_string('label:ok', 'block_dashboardanalytics') : get_string('label:warning', 'block_dashboardanalytics')
            ),
            $this->visual_item(
                get_string('server:setting:logretention', 'block_dashboardanalytics'),
                $logvalue,
                0.0,
                $loglifetime > 0 ? 'ok' : 'info',
                $loglifetime > 0 ? get_string('label:ok', 'block_dashboardanalytics') : get_string('server:label:setpolicy', 'block_dashboardanalytics')
            ),
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

    private function visual_item(string $label, string $value, float $percent, string $status, string $meta): array {
        return [
            'label' => $label,
            'value' => $value,
            'percent' => $percent,
            'status' => $status,
            'meta' => $meta,
            'segments' => [],
        ];
    }

    private function inline_metric_row(string $metric, ?float $percent, string $value, string $meta, string $status): array {
        return [
            'cells' => [
                ['key' => 'metric', 'value' => $metric],
                ['key' => 'value', 'value' => $value],
                ['key' => 'meta', 'value' => $meta],
                ['key' => 'percent', 'value' => $percent !== null ? (string)$percent : '0'],
                ['key' => 'statuskey', 'value' => $status],
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

    private function active_user_count(): int {
        try {
            return (new employee_repository())->count_active_users([]);
        } catch (\Throwable $e) {
            return 0;
        }
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

        $cores = $this->cpu_core_count();

        return round(min(100, (((float)$load) / $cores) * 100), 1);
    }

    private function cpu_core_count(): int {
        $cores = 0;
        if (function_exists('shell_exec')) {
            $cores = (int)trim((string)@\shell_exec('nproc 2>/dev/null'));
        }
        return $cores > 0 ? $cores : 1;
    }

    private function status_word(float $percent): string {
        if ($percent >= 90) {
            return 'Critical';
        }
        if ($percent >= 70) {
            return 'Warning';
        }
        return 'OK';
    }

    private function latest_age_text(int $timestamp): string {
        if ($timestamp <= 0) {
            return get_string('server:error:unknownlatest', 'block_dashboardanalytics');
        }

        return get_string('server:error:latestage', 'block_dashboardanalytics', format_time(time() - $timestamp));
    }

    private function cron_meta_text(): string {
        $lastcron = $this->last_cron_run();
        if ($lastcron <= 0) {
            return get_string('server:settings:cronunknown', 'block_dashboardanalytics');
        }

        return get_string('server:settings:cronvalue', 'block_dashboardanalytics', format_time(time() - $lastcron));
    }

    private function cache_backend_label(): string {
        global $DB;

        if (!$this->table_exists('config_plugins')) {
            return get_string('server:settings:cachedefault', 'block_dashboardanalytics');
        }

        try {
            if ($DB->record_exists('config_plugins', ['plugin' => 'cachestore_redis'])) {
                return 'Redis';
            }
        } catch (\Throwable $e) {
            return get_string('server:settings:cachedefault', 'block_dashboardanalytics');
        }

        return get_string('server:settings:cachedefault', 'block_dashboardanalytics');
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

    private function error_log_signals(int $since): array {
        $signals = [
            'qr' => ['count' => 0, 'latest' => 0],
            'email' => ['count' => 0, 'latest' => 0],
            'edsreject' => ['count' => 0, 'latest' => 0],
            'cron' => ['count' => 0, 'latest' => 0],
            'quilgo' => ['count' => 0, 'latest' => 0],
            'storage' => ['count' => 0, 'latest' => 0],
        ];

        $path = $this->resolve_error_log_path();
        if ($path === '' || !is_readable($path)) {
            return $signals;
        }

        $handle = @fopen($path, 'rb');
        $filesize = @filesize($path);
        if (!$handle || $filesize === false || $filesize <= 0) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            return $signals;
        }

        $chunksize = 1024 * 1024;
        $maxbytes = 64 * 1024 * 1024;
        $position = (int)$filesize;
        $buffer = '';
        $processed = 0;

        while ($position > 0 && $processed < $maxbytes) {
            $read = min($chunksize, $position);
            $position -= $read;
            if (@fseek($handle, $position) !== 0) {
                break;
            }

            $chunk = (string)@fread($handle, $read);
            if ($chunk === '') {
                break;
            }

            $processed += strlen($chunk);
            $buffer = $chunk . $buffer;
        }

        fclose($handle);

        $lines = preg_split("/\r\n|\n|\r/", $buffer) ?: [];
        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '') {
                continue;
            }

            $timestamp = $this->log_line_timestamp($line);
            if ($timestamp !== null && $timestamp < $since) {
                continue;
            }

            $category = $this->match_error_log_category($line);
            if ($category === '') {
                continue;
            }

            $signals[$category]['count']++;
            if ($timestamp !== null) {
                $signals[$category]['latest'] = max($signals[$category]['latest'], $timestamp);
            }
        }

        return $signals;
    }

    private function resolve_error_log_path(): string {
        global $CFG;

        $host = (string)(parse_url((string)($CFG->wwwroot ?? ''), PHP_URL_HOST) ?: 'sental.kz');
        $candidates = [
            '/www/wwwlogs/' . $host . '-error_log',
            'www/wwwlogs/' . $host . '-error_log',
            '/www/wwwlogs/sental.kz-error_log',
            'www/wwwlogs/sental.kz-error_log',
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && @is_readable($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    private function log_line_timestamp(string $line): ?int {
        if (preg_match('/\[(\d{2}-[A-Za-z]{3}-\d{4}\s+\d{2}:\d{2}:\d{2})(?:\s+[^\]]+)?\]/', $line, $matches)) {
            $timestamp = strtotime($matches[1]);
            return $timestamp !== false ? (int)$timestamp : null;
        }

        if (preg_match('/\[(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})(?:\s+[^\]]+)?\]/', $line, $matches)) {
            $timestamp = strtotime($matches[1]);
            return $timestamp !== false ? (int)$timestamp : null;
        }

        return null;
    }

    private function match_error_log_category(string $line): string {
        if (preg_match('/\b(?:qr|qrcode)\b.*\b(?:fail|failed|failure|error|exception)\b/i', $line)) {
            return 'qr';
        }

        if (preg_match('/\b(?:email|smtp|mail)\b.*\b(?:fail|failed|failure|error|exception|timeout|timed out|rejected|could not|invalid)\b/i', $line)
            || preg_match('/\b(?:Could not instantiate mail function|Failed to send email|error sending email)\b/i', $line)) {
            return 'email';
        }

        if (preg_match('/\b(?:eds|signature|ncasign)\b.*\b(?:reject|rejected|declin|invalid|fail|failed|error)\b/i', $line)) {
            return 'edsreject';
        }

        if (preg_match('/\bcron\b.*?\b(\d+(?:\.\d+)?)\s*(?:s|sec|secs|second|seconds)\b/i', $line, $matches)) {
            return ((float)$matches[1] > 120.0) ? 'cron' : '';
        }

        if (preg_match('/\bquilgo\b.*\b(?:timeout|timed out|error|exception|cURL error 28|gateway timeout)\b/i', $line)) {
            return 'quilgo';
        }

        if (preg_match('/\b(?:storage|stored_file|pluginfile|file system|file storage|disk full|failed to open stream|cannot create file)\b.*\b(?:error|exception|fail|failed|missing)\b/i', $line)) {
            return 'storage';
        }

        return '';
    }
}
