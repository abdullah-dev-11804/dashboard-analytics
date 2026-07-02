<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\task;

defined('MOODLE_INTERNAL') || die();

class collect_server_metrics extends \core\task\scheduled_task {
    public function get_name(): string {
        return get_string('task:collectservermetrics', 'block_dashboardanalytics');
    }

    public function execute() {
        $repository = new \block_dashboardanalytics\repository\server_metric_repository();
        $repository->capture_disk_snapshot();
    }
}
