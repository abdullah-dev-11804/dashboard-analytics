<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

function xmldb_block_dashboardanalytics_upgrade(int $oldversion): bool {
    if ($oldversion < 2026060800) {
        $clientroles = get_config('block_dashboardanalytics', 'clientroles');
        if ($clientroles === false || trim((string)$clientroles) === '') {
            set_config('clientroles', 'trainingmanager', 'block_dashboardanalytics');
        }

        unset_config('companycoordinatorroles', 'block_dashboardanalytics');
        unset_config('clientmanagerroles', 'block_dashboardanalytics');
        unset_config('systemadministratorroles', 'block_dashboardanalytics');

        upgrade_block_savepoint(true, 2026060800, 'dashboardanalytics');
    }

    return true;
}
