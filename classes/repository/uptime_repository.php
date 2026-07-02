<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\repository;

defined('MOODLE_INTERNAL') || die();

class uptime_repository {
    /** @var string */
    private const CACHE_KEY = 'site_uptime_summary';

    public function summary(): array {
        $cached = \cache::make('block_dashboardanalytics', 'uptime_api')->get(self::CACHE_KEY);
        if (is_array($cached) && !empty($cached['value'])) {
            return $cached;
        }

        $summary = $this->fetch_summary();
        \cache::make('block_dashboardanalytics', 'uptime_api')->set(self::CACHE_KEY, $summary);

        return $summary;
    }

    private function fetch_summary(): array {
        global $CFG;

        $url = trim((string)get_config('block_dashboardanalytics', 'uptimeendpoint'));
        if ($url === '') {
            return $this->fallback_summary();
        }

        require_once($CFG->libdir . '/filelib.php');

        try {
            $curl = new \curl();
            $response = $curl->get($url, [], [
                'CURLOPT_TIMEOUT' => 10,
                'CURLOPT_CONNECTTIMEOUT' => 5,
                'CURLOPT_RETURNTRANSFER' => true,
                'CURLOPT_HTTPHEADER' => ['Accept: application/json'],
            ]);
            $decoded = json_decode((string)$response, true);

            if (!is_array($decoded) || empty($decoded['status'])) {
                return $this->fallback_summary();
            }

            $now = time();
            $windowstart = $now - (30 * DAYSECS);
            $downtime = 0;
            $incidents = is_array($decoded['incidents'] ?? null) ? $decoded['incidents'] : [];

            foreach ($incidents as $incident) {
                if (!empty($incident['falseAlarm'])) {
                    continue;
                }

                $start = !empty($incident['start']) ? strtotime((string)$incident['start']) : false;
                $end = !empty($incident['end']) ? strtotime((string)$incident['end']) : false;
                if (!$start) {
                    continue;
                }
                if (!$end) {
                    $end = $now;
                }

                $overlapstart = max($windowstart, (int)$start);
                $overlapend = min($now, (int)$end);
                if ($overlapend > $overlapstart) {
                    $downtime += ($overlapend - $overlapstart);
                }
            }

            $uptimepercent = max(0, min(100, round(((30 * DAYSECS - $downtime) / (30 * DAYSECS)) * 100, 2)));
            $status = strtolower((string)$decoded['status']) === 'up'
                ? ($uptimepercent >= 99.5 ? 'ok' : ($uptimepercent >= 97 ? 'warning' : 'danger'))
                : 'danger';

            $meta = [];
            if (!empty($decoded['lastChecked'])) {
                $checked = strtotime((string)$decoded['lastChecked']);
                if ($checked) {
                    $meta[] = get_string('uptime:lastchecked', 'block_dashboardanalytics', userdate($checked, get_string('strftimedatetime', 'langconfig')));
                }
            }
            if (isset($decoded['latency']) && $decoded['latency'] !== '') {
                $meta[] = get_string('uptime:latency', 'block_dashboardanalytics', (int)$decoded['latency']);
            }
            if (!empty($decoded['uptimeSince'])) {
                $upsince = strtotime((string)$decoded['uptimeSince']);
                if ($upsince) {
                    $meta[] = get_string('uptime:since', 'block_dashboardanalytics', userdate($upsince, get_string('strftimedatetime', 'langconfig')));
                }
            }

            return [
                'value' => $uptimepercent . '%',
                'percent' => $uptimepercent,
                'status' => $status,
                'meta' => implode(' · ', $meta) ?: get_string('label:ok', 'block_dashboardanalytics'),
            ];
        } catch (\Throwable $e) {
            return $this->fallback_summary();
        }
    }

    private function fallback_summary(): array {
        return [
            'value' => 'N/A',
            'percent' => 0.0,
            'status' => 'muted',
            'meta' => get_string('overview:uptimepending', 'block_dashboardanalytics'),
        ];
    }
}
