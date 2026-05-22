<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\repository;

defined('MOODLE_INTERNAL') || die();

class proctoring_repository {

    public function trust_distribution_items(array $filters): array {
        $scores = $this->trust_score_records($filters);
        $bands = [
            'trusted' => ['label' => 'Trusted', 'count' => 0, 'status' => 'ok', 'meta' => '90-100'],
            'review' => ['label' => 'Review', 'count' => 0, 'status' => 'warning', 'meta' => '70-89'],
            'suspicious' => ['label' => 'Suspicious', 'count' => 0, 'status' => 'warning', 'meta' => '50-69'],
            'flagged' => ['label' => 'Flagged', 'count' => 0, 'status' => 'danger', 'meta' => '0-49'],
        ];

        foreach ($scores as $record) {
            $score = (float)$record['trustscore'];
            if ($score >= 90) {
                $bands['trusted']['count']++;
            } else if ($score >= 70) {
                $bands['review']['count']++;
            } else if ($score >= 50) {
                $bands['suspicious']['count']++;
            } else {
                $bands['flagged']['count']++;
            }
        }

        $total = max(1, count($scores));
        $items = [];
        foreach ($bands as $band) {
            $items[] = [
                'label' => $band['label'],
                'value' => (string)$band['count'],
                'percent' => round(($band['count'] / $total) * 100, 1),
                'status' => $band['status'],
                'meta' => $band['meta'],
            ];
        }

        return $items;
    }

    public function company_average_items(array $filters, int $limit = 12): array {
        $scores = $this->trust_score_records($filters);
        $companies = [];

        foreach ($scores as $record) {
            $company = trim((string)$record['company']);
            if ($company === '') {
                $company = 'Unassigned';
            }
            if (!isset($companies[$company])) {
                $companies[$company] = ['sum' => 0.0, 'count' => 0, 'flagged' => 0];
            }
            $companies[$company]['sum'] += (float)$record['trustscore'];
            $companies[$company]['count']++;
            if ((float)$record['trustscore'] < 50) {
                $companies[$company]['flagged']++;
            }
        }

        $items = [];
        foreach ($companies as $company => $values) {
            $average = $values['count'] > 0 ? round($values['sum'] / $values['count'], 1) : 0.0;
            $items[] = [
                'label' => $company,
                'value' => (string)$average,
                'percent' => $average,
                'status' => $average >= 85 ? 'ok' : ($average >= 75 ? 'warning' : 'danger'),
                'meta' => $values['count'] . ' attempts, ' . $values['flagged'] . ' flagged',
            ];
        }

        usort($items, static function(array $a, array $b): int {
            return $a['percent'] <=> $b['percent'];
        });

        return array_slice($items, 0, $limit);
    }

    public function has_data(array $filters): bool {
        return count($this->trust_score_records($filters, 1)) > 0;
    }

    public function has_reports(array $filters): bool {
        return $this->report_count($filters) > 0;
    }

    public function coverage_items(array $filters): array {
        $counts = $this->report_counts($filters);
        $total = max(1, (int)$counts->total);

        return [
            [
                'label' => 'Proctoring enabled',
                'value' => (string)(int)$counts->proctoringenabled,
                'percent' => round(((int)$counts->proctoringenabled / $total) * 100, 1),
                'status' => 'ok',
                'meta' => round(((int)$counts->proctoringenabled / $total) * 100, 1) . '% of reports',
            ],
            [
                'label' => 'Not enabled',
                'value' => (string)max(0, (int)$counts->total - (int)$counts->proctoringenabled),
                'percent' => round((max(0, (int)$counts->total - (int)$counts->proctoringenabled) / $total) * 100, 1),
                'status' => 'muted',
                'meta' => 'reports without proctoring',
            ],
        ];
    }

    public function feature_items(array $filters): array {
        $counts = $this->report_counts($filters);
        $total = max(1, (int)$counts->total);

        return [
            $this->feature_item('Camera enabled', (int)$counts->cameraenabled, $total, 'ok'),
            $this->feature_item('Screen enabled', (int)$counts->screenenabled, $total, 'ok'),
            $this->feature_item('Force enabled', (int)$counts->forceenabled, $total, 'warning'),
            $this->feature_item('Errors captured', (int)$counts->errors, $total, (int)$counts->errors > 0 ? 'danger' : 'ok'),
        ];
    }

    private function trust_score_records(array $filters, int $limit = 500): array {
        global $DB;

        if (!$this->has_tables()) {
            return [];
        }

        $employee = new employee_repository();
        $userfilter = $employee->user_filter_sql($filters, 'u', 'quilgo');
        $company = new company_repository();
        $companysql = $company->company_name_sql('u', 'quilgo');
        $where = [$userfilter['sql'], 'qr.stat IS NOT NULL'];
        $params = $userfilter['params'];

        if (!empty($filters['courseids'])) {
            [$insql, $inparams] = $DB->get_in_or_equal($filters['courseids'], SQL_PARAMS_NAMED, 'quilgocourse');
            $where[] = "q.course {$insql}";
            $params += $inparams;
        }

        $sql = "SELECT qr.id,
                       qr.stat,
                       qa.userid,
                       qa.timestart,
                       q.course AS courseid,
                       {$companysql['select']}
                  FROM {quizaccess_quilgo_reports} qr
                  JOIN {quiz_attempts} qa ON qa.id = qr.attemptid
                  JOIN {quiz} q ON q.id = qa.quiz
                  JOIN {user} u ON u.id = qa.userid
                       {$companysql['join']}
                 WHERE " . implode(' AND ', $where) . "
              ORDER BY qa.timestart DESC";

        $records = $DB->get_records_sql($sql, $params, 0, $limit);
        $items = [];
        foreach ($records as $record) {
            $trustscore = $this->extract_trust_score((string)$record->stat);
            if ($trustscore === null) {
                continue;
            }
            $items[] = [
                'trustscore' => $trustscore,
                'company' => (string)$record->companyname,
                'courseid' => (int)$record->courseid,
                'userid' => (int)$record->userid,
            ];
        }

        return $items;
    }

    private function report_count(array $filters): int {
        $counts = $this->report_counts($filters);
        return (int)$counts->total;
    }

    private function report_counts(array $filters): \stdClass {
        global $DB;

        $empty = (object)[
            'total' => 0,
            'proctoringenabled' => 0,
            'cameraenabled' => 0,
            'screenenabled' => 0,
            'forceenabled' => 0,
            'errors' => 0,
        ];

        if (!$this->has_tables()) {
            return $empty;
        }

        $employee = new employee_repository();
        $userfilter = $employee->user_filter_sql($filters, 'u', 'quilgocount');
        $where = [$userfilter['sql']];
        $params = $userfilter['params'];

        if (!empty($filters['courseids'])) {
            [$insql, $inparams] = $DB->get_in_or_equal($filters['courseids'], SQL_PARAMS_NAMED, 'quilgocountcourse');
            $where[] = "q.course {$insql}";
            $params += $inparams;
        }

        $sql = "SELECT COUNT(1) AS total,
                       SUM(CASE WHEN qr.proctoring_enabled = 1 THEN 1 ELSE 0 END) AS proctoringenabled,
                       SUM(CASE WHEN qr.camera_enabled = 1 THEN 1 ELSE 0 END) AS cameraenabled,
                       SUM(CASE WHEN qr.screen_enabled = 1 THEN 1 ELSE 0 END) AS screenenabled,
                       SUM(CASE WHEN qr.force_enabled = 1 THEN 1 ELSE 0 END) AS forceenabled,
                       SUM(CASE WHEN qr.error_reason IS NOT NULL AND qr.error_reason <> '' THEN 1 ELSE 0 END) AS errors
                  FROM {quizaccess_quilgo_reports} qr
                  JOIN {quiz_attempts} qa ON qa.id = qr.attemptid
                  JOIN {quiz} q ON q.id = qa.quiz
                  JOIN {user} u ON u.id = qa.userid
                 WHERE " . implode(' AND ', $where);

        $record = $DB->get_record_sql($sql, $params);
        return $record ?: $empty;
    }

    private function feature_item(string $label, int $count, int $total, string $status): array {
        return [
            'label' => $label,
            'value' => (string)$count,
            'percent' => round(($count / max(1, $total)) * 100, 1),
            'status' => $status,
            'meta' => round(($count / max(1, $total)) * 100, 1) . '% of reports',
        ];
    }

    private function extract_trust_score(string $stat): ?float {
        $decoded = json_decode($stat, true);
        if (is_array($decoded)) {
            return $this->find_trust_score($decoded);
        }

        if (preg_match('/trust[^0-9]{0,30}([0-9]{1,3}(?:\.[0-9]+)?)/i', $stat, $matches)) {
            return $this->normalize_score((float)$matches[1]);
        }

        return null;
    }

    private function find_trust_score(array $value): ?float {
        foreach ($value as $key => $item) {
            $key = strtolower((string)$key);
            if (is_numeric($item) && strpos($key, 'trust') !== false) {
                return $this->normalize_score((float)$item);
            }
            if (is_array($item)) {
                $score = $this->find_trust_score($item);
                if ($score !== null) {
                    return $score;
                }
            }
        }

        return null;
    }

    private function normalize_score(float $score): float {
        if ($score <= 1) {
            $score *= 100;
        }

        return max(0.0, min(100.0, round($score, 1)));
    }

    private function has_tables(): bool {
        return $this->table_exists('quizaccess_quilgo_reports')
            && $this->table_exists('quiz_attempts')
            && $this->table_exists('quiz');
    }

    private function table_exists(string $tablename): bool {
        global $CFG, $DB;

        require_once($CFG->libdir . '/xmldb/xmldb_table.php');
        return $DB->get_manager()->table_exists(new \xmldb_table($tablename));
    }
}
