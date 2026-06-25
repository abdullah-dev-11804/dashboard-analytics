<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\service;

use block_dashboardanalytics\repository\course_rating_repository;
use block_dashboardanalytics\repository\quiz_repository;

defined('MOODLE_INTERNAL') || die();

class training_quality_service {

    public function pass_rate_threshold(): float {
        $configured = (float)get_config('block_dashboardanalytics', 'qualitypassratethreshold');
        if ($configured <= 0 || $configured > 100) {
            return 60.0;
        }

        return $configured;
    }

    public function rating_threshold(): float {
        return 3.0;
    }

    public function first_attempt_pass_rate_items(array $filters): array {
        $quiz = new quiz_repository();
        return $quiz->first_attempt_pass_rate_by_course_items($filters, $this->pass_rate_threshold());
    }

    public function engagement_ratio_items(array $filters): array {
        // Moodle core event logs can show activity events, but do not provide a reliable per-course
        // total session duration that includes idle/browser-open time. Keep this chart empty until
        // a session-tracking source table is introduced.
        return [];
    }

    public function course_rating_items(array $filters): array {
        $ratings = new course_rating_repository();
        return $ratings->average_rating_by_course_items($filters, $this->rating_threshold());
    }

    public function course_feedback_items(array $filters): array {
        $ratings = new course_rating_repository();
        return $ratings->feedback_table_items($filters, $this->rating_threshold());
    }

    public function course_feedback_alert(array $items): string {
        $ratings = new course_rating_repository();
        return $ratings->action_required_message($items, $this->rating_threshold());
    }
}
