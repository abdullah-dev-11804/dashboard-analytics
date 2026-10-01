<?php
// This file is part of Moodle - http://moodle.org/

namespace block_dashboardanalytics\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;

class provider implements \core_privacy\local\metadata\provider {
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('block_da_expcompany', [
            'recipientids' => 'privacy:metadata:recipientids',
            'modifiedby' => 'privacy:metadata:actorid',
        ], 'privacy:metadata:expirycompany');
        $collection->add_database_table('block_da_expcase', [
            'userid' => 'privacy:metadata:userid',
            'actionby' => 'privacy:metadata:actorid',
        ], 'privacy:metadata:expirycase');
        $collection->add_database_table('block_da_expaudit', [
            'actorid' => 'privacy:metadata:actorid',
            'detail' => 'privacy:metadata:detail',
        ], 'privacy:metadata:expiryaudit');
        $collection->add_database_table('block_da_reptemplate', [
            'userid' => 'privacy:metadata:userid',
            'name' => 'privacy:metadata:templatename',
            'columnsjson' => 'privacy:metadata:templatecolumns',
            'filtersjson' => 'privacy:metadata:templatefilters',
        ], 'privacy:metadata:reporttemplate');
        $collection->add_database_table('block_da_empstatus', [
            'userid' => 'privacy:metadata:userid',
            'deactivatedby' => 'privacy:metadata:actorid',
            'modifiedby' => 'privacy:metadata:actorid',
        ], 'privacy:metadata:employeestatus');
        $collection->add_database_table('block_da_empaudit', [
            'userid' => 'privacy:metadata:userid',
            'actorid' => 'privacy:metadata:actorid',
            'detail' => 'privacy:metadata:detail',
        ], 'privacy:metadata:employeeaudit');

        return $collection;
    }
}
