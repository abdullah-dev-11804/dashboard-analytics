<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\repository;

defined('MOODLE_INTERNAL') || die();

class course_rating_repository {

    public function feedback_table_items(array $filters, float $threshold = 3.0, int $limit = 12): array {
        global $DB;

        if (!$this->table_exists('tool_courserating_rating')) {
            return [];
        }

        $employee = new employee_repository();
        $userfilter = $employee->user_filter_sql($filters, 'u', 'courserating');
        $where = [
            $userfilter['sql'],
            'r.rating >= 1',
            'r.rating <= 10',
        ];
        $params = $userfilter['params'];

        if (!empty($filters['courseids'])) {
            [$insql, $inparams] = $DB->get_in_or_equal($filters['courseids'], SQL_PARAMS_NAMED, 'courseratingcourse');
            $where[] = "c.id {$insql}";
            $params += $inparams;
        }

        $this->append_date_filter($where, $params, 'r.timemodified', $filters, 'courseratingdate');

        $ratingcase = [];
        for ($rating = 1; $rating <= 10; $rating++) {
            $ratingcase[] = "SUM(CASE WHEN r.rating = {$rating} THEN 1 ELSE 0 END) AS cnt{$rating}";
        }

        $sql = "SELECT c.id AS courseid,
                       c.fullname AS coursename,
                       AVG(r.rating) AS avgrating,
                       COUNT(r.id) AS ratingcount,
                       MAX(r.rating) AS maxrating,
                       " . implode(",\n                       ", $ratingcase) . "
                  FROM {tool_courserating_rating} r
                  JOIN {course} c ON c.id = r.courseid
                  JOIN {user} u ON u.id = r.userid
                 WHERE " . implode(' AND ', $where) . "
              GROUP BY c.id, c.fullname
                HAVING COUNT(r.id) > 0
              ORDER BY AVG(r.rating) DESC,
                       COUNT(r.id) DESC,
                       c.fullname ASC";

        $records = $DB->get_records_sql($sql, $params, 0, $limit);
        if (!$records) {
            return [];
        }

        $latestreviews = $this->latest_reviews_for_courses(array_keys($records), $filters);
        $items = [];
        foreach ($records as $record) {
            $average = round((float)$record->avgrating, 2);
            $count = (int)$record->ratingcount;
            $maxrating = (int)$record->maxrating;
            $scale = $maxrating > 5 ? 10 : 5;
            $rating5 = $scale === 10 ? round(($average / 10.0) * 5.0, 2) : $average;
            $nps = $this->calculate_nps($record, $scale, $count);
            $relevance = $this->calculate_relevance($rating5, $nps);
            $status = $this->rating_status($rating5, $threshold);
            $relevancestatus = $this->relevance_status($relevance);
            $review = $latestreviews[(int)$record->courseid] ?? '';

            $items[] = [
                'label' => format_string((string)$record->coursename),
                'url' => (new \moodle_url('/course/view.php', ['id' => (int)$record->courseid]))->out(false),
                'value' => $this->format_rating($rating5),
                'percent' => $relevance,
                'status' => $status,
                'meta' => $status === 'danger'
                    ? get_string('quality:rating:revisionmeta', 'block_dashboardanalytics', (object)[
                        'count' => $count,
                        'threshold' => $this->format_rating($threshold),
                    ])
                    : get_string('quality:rating:meta', 'block_dashboardanalytics', (object)[
                        'count' => $count,
                        'threshold' => $this->format_rating($threshold),
                    ]),
                'rating' => $rating5,
                'ratinglabel' => $this->format_rating($rating5),
                'reviews' => $count,
                'nps' => $nps,
                'npslabel' => $this->format_signed_integer($nps),
                'relevance' => $relevance,
                'relevancelabel' => $this->format_percent($relevance),
                'relevancestatus' => $relevancestatus,
                'latestfeedback' => $review,
            ];
        }

        return $items;
    }

    public function average_rating_by_course_items(array $filters, float $threshold = 3.0, int $limit = 12): array {
        $items = [];
        foreach ($this->feedback_table_items($filters, $threshold, $limit) as $item) {
            $items[] = [
                'label' => $item['label'],
                'value' => $item['ratinglabel'] . '/5',
                'percent' => round(min(5.0, max(0.0, (float)$item['rating'])) / 5.0 * 100.0, 1),
                'status' => $item['status'],
                'meta' => $item['meta'],
            ];
        }
        return $items;
    }

    public function action_required_message(array $items, float $threshold = 3.0): string {
        $flagged = array_values(array_filter($items, function(array $item) use ($threshold): bool {
            return isset($item['rating']) && (float)$item['rating'] < $threshold;
        }));

        if (!$flagged) {
            return '';
        }

        usort($flagged, function(array $left, array $right): int {
            $rating = ((float)$left['rating']) <=> ((float)$right['rating']);
            if ($rating !== 0) {
                return $rating;
            }
            return ((float)$left['relevance']) <=> ((float)$right['relevance']);
        });

        $item = $flagged[0];
        return get_string('quality:rating:actionrequired', 'block_dashboardanalytics', (object)[
            'course' => $item['label'],
            'rating' => $item['ratinglabel'],
            'nps' => $item['npslabel'],
            'relevance' => $item['relevancelabel'],
        ]);
    }

    private function latest_reviews_for_courses(array $courseids, array $filters): array {
        global $DB;

        if (!$courseids) {
            return [];
        }

        $employee = new employee_repository();
        $userfilter = $employee->user_filter_sql($filters, 'u', 'courseratingreview');
        [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'courseratingreviewcourse');
        $where = [
            $userfilter['sql'],
            "r.courseid {$insql}",
            'r.hasreview = 1',
            $DB->sql_compare_text('r.review', 1333) . " <> ''",
        ];
        $params = $userfilter['params'] + $inparams;
        $this->append_date_filter($where, $params, 'r.timemodified', $filters, 'courseratingreviewdate');

        $sql = "SELECT r.id,
                       r.courseid,
                       r.review,
                       r.timemodified,
                       u.firstname,
                       u.lastname,
                       u.firstnamephonetic,
                       u.lastnamephonetic,
                       u.middlename,
                       u.alternatename
                  FROM {tool_courserating_rating} r
                  JOIN {user} u ON u.id = r.userid
                 WHERE " . implode(' AND ', $where) . "
              ORDER BY r.courseid ASC, r.timemodified DESC, r.id DESC";

        $records = $DB->get_records_sql($sql, $params);
        $reviews = [];
        foreach ($records as $record) {
            $courseid = (int)$record->courseid;
            if (isset($reviews[$courseid])) {
                continue;
            }

            $review = $this->normalise_review((string)$record->review);
            if ($review === '') {
                continue;
            }

            $author = trim(fullname($record));
            if ($author !== '') {
                $review .= ' - ' . $author;
            }
            $reviews[$courseid] = $this->shorten_text($review, 170);
        }

        return $reviews;
    }

    private function calculate_nps(object $record, int $scale, int $total): int {
        if ($total <= 0) {
            return 0;
        }

        $promoters = 0;
        $detractors = 0;
        for ($rating = 1; $rating <= 10; $rating++) {
            $count = (int)($record->{'cnt' . $rating} ?? 0);
            if ($scale === 10) {
                if ($rating >= 9) {
                    $promoters += $count;
                } else if ($rating <= 6) {
                    $detractors += $count;
                }
            } else {
                if ($rating >= 4 && $rating <= 5) {
                    $promoters += $count;
                } else if ($rating <= 2) {
                    $detractors += $count;
                }
            }
        }

        return (int)round((($promoters - $detractors) / $total) * 100);
    }

    private function calculate_relevance(float $rating, int $nps): int {
        $ratingpercent = min(100.0, max(0.0, ($rating / 5.0) * 100.0));
        $npspercent = min(100.0, max(0.0, ($nps + 100.0) / 2.0));
        return (int)round(($ratingpercent * 0.75) + ($npspercent * 0.25));
    }

    private function rating_status(float $average, float $threshold): string {
        if ($average < $threshold) {
            return 'danger';
        }

        if ($average < 4.0) {
            return 'warning';
        }

        return 'ok';
    }

    private function relevance_status(int $relevance): string {
        if ($relevance < 60) {
            return 'danger';
        }
        if ($relevance < 80) {
            return 'warning';
        }
        return 'ok';
    }

    private function table_exists(string $table): bool {
        global $CFG, $DB;

        require_once($CFG->libdir . '/ddllib.php');
        return $DB->get_manager()->table_exists(new \xmldb_table($table));
    }

    private function append_date_filter(array &$where, array &$params, string $field, array $filters, string $prefix): void {
        $range = (string)($filters['daterange'] ?? 'last12months');
        $now = time();
        $start = null;
        $end = null;

        if ($range === 'customrange') {
            if (!empty($filters['customstart'])) {
                $starttime = strtotime((string)$filters['customstart'] . ' 00:00:00');
                if ($starttime !== false) {
                    $start = $starttime;
                }
            }
            if (!empty($filters['customend'])) {
                $endtime = strtotime((string)$filters['customend'] . ' 23:59:59');
                if ($endtime !== false) {
                    $end = $endtime;
                }
            }
        } else if ($range !== 'alltime') {
            $days = [
                'day' => 1,
                'week' => 7,
                'month' => 30,
                'last30days' => 30,
                'last90days' => 90,
                '6months' => 183,
                'last6months' => 183,
                'year' => 365,
                'last12months' => 365,
            ][$range] ?? 365;
            $start = $now - ($days * DAYSECS);
        }

        if ($start !== null) {
            $where[] = "{$field} >= :{$prefix}start";
            $params[$prefix . 'start'] = $start;
        }
        if ($end !== null) {
            $where[] = "{$field} <= :{$prefix}end";
            $params[$prefix . 'end'] = $end;
        }
    }

    private function normalise_review(string $review): string {
        $review = html_to_text($review, 0, false);
        $review = preg_replace('/\s+/', ' ', $review);
        return trim((string)$review);
    }

    private function shorten_text(string $text, int $limit): string {
        if (\core_text::strlen($text) <= $limit) {
            return $text;
        }
        return rtrim(\core_text::substr($text, 0, max(0, $limit - 3))) . '...';
    }

    private function format_rating(float $value): string {
        return format_float(round($value, 1), 1);
    }

    private function format_signed_integer(int $value): string {
        return $value > 0 ? '+' . $value : (string)$value;
    }

    private function format_percent(int $value): string {
        return $value . '%';
    }
}
