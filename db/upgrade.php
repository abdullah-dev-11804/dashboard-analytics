<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

function xmldb_block_dashboardanalytics_upgrade(int $oldversion): bool {
    $dbman = $GLOBALS['DB']->get_manager();

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

    if ($oldversion < 2026070300) {
        $table = new xmldb_table('block_da_srvmetric');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('metricname', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);
        $table->add_field('percentvalue', XMLDB_TYPE_NUMBER, '10,2', null, null, null, null);
        $table->add_field('usedbytes', XMLDB_TYPE_INTEGER, '19', null, null, null, null);
        $table->add_field('totalbytes', XMLDB_TYPE_INTEGER, '19', null, null, null, null);
        $table->add_field('collectedat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('metric_collected_ix', XMLDB_INDEX_NOTUNIQUE, ['metricname', 'collectedat']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        if (get_config('block_dashboardanalytics', 'uptimeendpoint') === false) {
            set_config(
                'uptimeendpoint',
                'https://uptime.thefonerep.com/v1/site?token=a66ce24005d6024cd3c44a3da0eccae4e1032c1e39f01463984033a8485a32e4&id=1783023558638',
                'block_dashboardanalytics'
            );
        }

        upgrade_block_savepoint(true, 2026070300, 'dashboardanalytics');
    }

    if ($oldversion < 2026080400) {
        $table = new xmldb_table('block_da_expcompany');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('companyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('recipientids', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('modifiedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('companyid_uix', XMLDB_INDEX_UNIQUE, ['companyid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('block_da_expcourse');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('modifiedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('courseid_uix', XMLDB_INDEX_UNIQUE, ['courseid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('block_da_expcase');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('cyclekey', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('companyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sourcekind', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, '');
        $table->add_field('sourceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('issuedate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('expirydate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('activewindow', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('workflowstatus', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'awaiting');
        $table->add_field('cadencemode', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'weekly');
        $table->add_field('nextnotifyat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lastnotifiedat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('reassignedat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('dismissedat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('actionby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('cyclekey_uix', XMLDB_INDEX_UNIQUE, ['cyclekey']);
        $table->add_index('company_window_status_ix', XMLDB_INDEX_NOTUNIQUE, ['companyid', 'activewindow', 'workflowstatus']);
        $table->add_index('notify_ix', XMLDB_INDEX_NOTUNIQUE, ['activewindow', 'workflowstatus', 'nextnotifyat']);
        $table->add_index('usercourse_ix', XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('block_da_expaudit');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('caseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('action', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, '');
        $table->add_field('detail', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('actorid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('case_time_ix', XMLDB_INDEX_NOTUNIQUE, ['caseid', 'timecreated']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        if (get_config('block_dashboardanalytics', 'expiryworkflowenabled') === false) {
            set_config('expiryworkflowenabled', 0, 'block_dashboardanalytics');
        }
        if (get_config('block_dashboardanalytics', 'expiryworkflowthresholddays') === false) {
            set_config('expiryworkflowthresholddays', 30, 'block_dashboardanalytics');
        }
        if (get_config('block_dashboardanalytics', 'expiryworkflowdefaultrecipient') === false) {
            set_config('expiryworkflowdefaultrecipient', 'trainingmng1@sental.kz', 'block_dashboardanalytics');
        }

        upgrade_block_savepoint(true, 2026080400, 'dashboardanalytics');
    }

    if ($oldversion < 2026082700) {
        $table = new xmldb_table('block_da_reptemplate');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
        $table->add_field('columnsjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('filtersjson', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('userid_name_ix', XMLDB_INDEX_NOTUNIQUE, ['userid', 'name']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_block_savepoint(true, 2026082700, 'dashboardanalytics');
    }

    return true;
}
