<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading(
        'block_dashboardanalytics/rolesheading',
        get_string('settings:rolesheading', 'block_dashboardanalytics'),
        get_string('settings:rolesheading_desc', 'block_dashboardanalytics')
    ));

    $settings->add(new admin_setting_configtext(
        'block_dashboardanalytics/companyownerroles',
        get_string('settings:companyownerroles', 'block_dashboardanalytics'),
        get_string('settings:rolescsv_desc', 'block_dashboardanalytics'),
        'companyowner',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configtext(
        'block_dashboardanalytics/companycoordinatorroles',
        get_string('settings:companycoordinatorroles', 'block_dashboardanalytics'),
        get_string('settings:rolescsv_desc', 'block_dashboardanalytics'),
        'companycoordinator,trainingmanager',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configtext(
        'block_dashboardanalytics/clientmanagerroles',
        get_string('settings:clientmanagerroles', 'block_dashboardanalytics'),
        get_string('settings:rolescsv_desc', 'block_dashboardanalytics'),
        'clientadministrator,manager',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configtext(
        'block_dashboardanalytics/systemadministratorroles',
        get_string('settings:systemadministratorroles', 'block_dashboardanalytics'),
        get_string('settings:rolescsv_desc', 'block_dashboardanalytics'),
        'systemadministrator',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_heading(
        'block_dashboardanalytics/dimensionsheading',
        get_string('settings:dimensionsheading', 'block_dashboardanalytics'),
        get_string('settings:dimensionsheading_desc', 'block_dashboardanalytics')
    ));

    $settings->add(new admin_setting_configtext(
        'block_dashboardanalytics/positionfield',
        get_string('settings:positionfield', 'block_dashboardanalytics'),
        get_string('settings:positionfield_desc', 'block_dashboardanalytics'),
        '',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_heading(
        'block_dashboardanalytics/documentheading',
        get_string('settings:documentheading', 'block_dashboardanalytics'),
        get_string('settings:documentheading_desc', 'block_dashboardanalytics')
    ));

    $settings->add(new admin_setting_configtext(
        'block_dashboardanalytics/documenttable',
        get_string('settings:documenttable', 'block_dashboardanalytics'),
        get_string('settings:documenttable_desc', 'block_dashboardanalytics'),
        'local_ncasign_jobs',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'block_dashboardanalytics/documentuseridcolumn',
        get_string('settings:documentuseridcolumn', 'block_dashboardanalytics'),
        get_string('settings:documentuseridcolumn_desc', 'block_dashboardanalytics'),
        'userid',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'block_dashboardanalytics/documentcourseidcolumn',
        get_string('settings:documentcourseidcolumn', 'block_dashboardanalytics'),
        get_string('settings:documentcourseidcolumn_desc', 'block_dashboardanalytics'),
        'courseid',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'block_dashboardanalytics/documentexpirycolumn',
        get_string('settings:documentexpirycolumn', 'block_dashboardanalytics'),
        get_string('settings:documentexpirycolumn_desc', 'block_dashboardanalytics'),
        'expirydate',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'block_dashboardanalytics/forecastthreshold',
        get_string('settings:forecastthreshold', 'block_dashboardanalytics'),
        get_string('settings:forecastthreshold_desc', 'block_dashboardanalytics'),
        '10',
        PARAM_INT
    ));
}
