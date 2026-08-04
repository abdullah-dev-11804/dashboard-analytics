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
        get_string('settings:companyroles', 'block_dashboardanalytics'),
        get_string('settings:rolescsv_desc', 'block_dashboardanalytics'),
        'companyowner,companymanager',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configtext(
        'block_dashboardanalytics/clientroles',
        get_string('settings:clientroles', 'block_dashboardanalytics'),
        get_string('settings:rolescsv_desc', 'block_dashboardanalytics'),
        'trainingmanager,hrcoordinator',
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
        'block_dashboardanalytics/forecastthreshold',
        get_string('settings:forecastthreshold', 'block_dashboardanalytics'),
        get_string('settings:forecastthreshold_desc', 'block_dashboardanalytics'),
        '10',
        PARAM_INT
    ));

    $settings->add(new admin_setting_heading(
        'block_dashboardanalytics/monitoringheading',
        get_string('settings:monitoringheading', 'block_dashboardanalytics'),
        get_string('settings:monitoringheading_desc', 'block_dashboardanalytics')
    ));

    $settings->add(new admin_setting_configtext(
        'block_dashboardanalytics/uptimeendpoint',
        get_string('settings:uptimeendpoint', 'block_dashboardanalytics'),
        get_string('settings:uptimeendpoint_desc', 'block_dashboardanalytics'),
        'https://uptime.thefonerep.com/v1/site?token=a66ce24005d6024cd3c44a3da0eccae4e1032c1e39f01463984033a8485a32e4&id=1783023558638',
        PARAM_URL
    ));

    $settings->add(new admin_setting_heading(
        'block_dashboardanalytics/qualityheading',
        get_string('settings:qualityheading', 'block_dashboardanalytics'),
        get_string('settings:qualityheading_desc', 'block_dashboardanalytics')
    ));

    $settings->add(new admin_setting_configtext(
        'block_dashboardanalytics/qualitypassratethreshold',
        get_string('settings:qualitypassratethreshold', 'block_dashboardanalytics'),
        get_string('settings:qualitypassratethreshold_desc', 'block_dashboardanalytics'),
        '60',
        PARAM_FLOAT
    ));

    $settings->add(new admin_setting_heading(
        'block_dashboardanalytics/expiryworkflowheading',
        get_string('settings:expiryworkflowheading', 'block_dashboardanalytics'),
        get_string('settings:expiryworkflowheading_desc', 'block_dashboardanalytics')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_dashboardanalytics/expiryworkflowenabled',
        get_string('settings:expiryworkflowenabled', 'block_dashboardanalytics'),
        get_string('settings:expiryworkflowenabled_desc', 'block_dashboardanalytics'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'block_dashboardanalytics/expiryworkflowthresholddays',
        get_string('settings:expiryworkflowthresholddays', 'block_dashboardanalytics'),
        get_string('settings:expiryworkflowthresholddays_desc', 'block_dashboardanalytics'),
        '30',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'block_dashboardanalytics/expiryworkflowdefaultrecipient',
        get_string('settings:expiryworkflowdefaultrecipient', 'block_dashboardanalytics'),
        get_string('settings:expiryworkflowdefaultrecipient_desc', 'block_dashboardanalytics'),
        'trainingmng1@sental.kz',
        PARAM_EMAIL
    ));
}
