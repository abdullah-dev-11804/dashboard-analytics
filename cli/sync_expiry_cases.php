<?php
// This file is part of Moodle - http://moodle.org/
//
// CLI backfill for expiry workflow cases.

define('CLI_SCRIPT', true);

require_once(dirname(__DIR__, 3) . '/config.php');
require_once($CFG->libdir . '/clilib.php');

use block_dashboardanalytics\repository\expiry_workflow_repository;

$help = <<<EOF
Sync expiring user-course rows into mdl_block_da_expcase.

Options:
--all                Sync all companies and unassigned cases (default behavior).
--companyid=ID       Sync only one company.
--verbose            Print extra detail.
--help               Show this help.

Examples:
php blocks/dashboardanalytics/cli/sync_expiry_cases.php --all
php blocks/dashboardanalytics/cli/sync_expiry_cases.php --companyid=3 --verbose
EOF;

[$options, $unrecognized] = cli_get_params([
    'all' => false,
    'companyid' => 0,
    'verbose' => false,
    'help' => false,
], [
    'h' => 'help',
]);

if ($unrecognized) {
    cli_error('Unknown option(s): ' . implode(', ', $unrecognized) . PHP_EOL . $help);
}

if (!empty($options['help'])) {
    echo $help . PHP_EOL;
    exit(0);
}

$companyid = max(0, (int)$options['companyid']);
$syncall = !empty($options['all']) || $companyid <= 0;
$verbose = !empty($options['verbose']);

$repository = new expiry_workflow_repository();

cli_writeln('Starting expiry workflow sync...');
cli_writeln($syncall ? 'Scope: all companies' : ('Scope: companyid=' . $companyid));

if ($syncall) {
    $result = $repository->sync_cases(0);
    cli_writeln('Done.');
    cli_writeln('Candidates: ' . (int)$result['candidatecount']);
    cli_writeln('Created: ' . (int)$result['created']);
    cli_writeln('Updated: ' . (int)$result['updated']);
    cli_writeln('Deactivated: ' . (int)$result['deactivated']);
    exit(0);
}

$result = $repository->sync_cases($companyid);
cli_writeln('Done.');
cli_writeln('Candidates: ' . (int)$result['candidatecount']);
cli_writeln('Created: ' . (int)$result['created']);
cli_writeln('Updated: ' . (int)$result['updated']);
cli_writeln('Deactivated: ' . (int)$result['deactivated']);

if ($verbose) {
    global $DB;
    $activeawaiting = $DB->count_records('block_da_expcase', [
        'companyid' => $companyid,
        'activewindow' => 1,
        'workflowstatus' => expiry_workflow_repository::STATUS_AWAITING,
    ]);
    $activeall = $DB->count_records('block_da_expcase', [
        'companyid' => $companyid,
        'activewindow' => 1,
    ]);
    cli_writeln('Active awaiting rows now: ' . (int)$activeawaiting);
    cli_writeln('Active rows now: ' . (int)$activeall);
}
