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

    return true;
}
