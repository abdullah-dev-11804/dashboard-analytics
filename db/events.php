<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\\core\\event\\user_deleted',
        'callback' => '\\block_dashboardanalytics\\observer::user_deleted',
        'priority' => 1000,
    ],
    [
        'eventname' => '\\block_iomad_company_admin\\event\\company_user_unassigned',
        'callback' => '\\block_dashboardanalytics\\observer::company_user_unassigned',
    ],
    [
        'eventname' => '\\block_iomad_company_admin\\event\\company_user_deleted',
        'callback' => '\\block_dashboardanalytics\\observer::company_user_deleted',
    ],
    [
        'eventname' => '\\block_iomad_company_admin\\event\\company_user_suspended',
        'callback' => '\\block_dashboardanalytics\\observer::company_user_suspended',
    ],
];
