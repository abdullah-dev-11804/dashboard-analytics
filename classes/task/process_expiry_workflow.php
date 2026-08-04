<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\task;

use block_dashboardanalytics\repository\expiry_workflow_repository;

defined('MOODLE_INTERNAL') || die();

class process_expiry_workflow extends \core\task\scheduled_task {
    public function get_name(): string {
        return get_string('task:processexpiryworkflow', 'block_dashboardanalytics');
    }

    public function execute(): void {
        $repository = new expiry_workflow_repository();
        $repository->sync_cases();
        $repository->send_due_digests();
    }
}
