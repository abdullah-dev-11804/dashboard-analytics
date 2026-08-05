<?php
// This file is part of Moodle - http://moodle.org/
//
// CLI backfill for expiry workflow cases.

define('CLI_SCRIPT', true);

require_once(dirname(__DIR__, 3) . '/config.php');
require_once($CFG->libdir . '/clilib.php');

use block_dashboardanalytics\repository\expiry_workflow_repository;
use block_dashboardanalytics\repository\overview_repository;

$help = <<<EOF
Sync expiring user-course rows into mdl_block_da_expcase.

Options:
--all                Sync all companies and unassigned cases (default behavior).
--companyid=ID       Sync only one company.
--verbose            Print extra detail.
--diagnose           Print upstream snapshot counts before syncing.
--help               Show this help.

Examples:
php blocks/dashboardanalytics/cli/sync_expiry_cases.php --all
php blocks/dashboardanalytics/cli/sync_expiry_cases.php --companyid=3 --verbose
php blocks/dashboardanalytics/cli/sync_expiry_cases.php --companyid=3 --diagnose
EOF;

[$options, $unrecognized] = cli_get_params([
    'all' => false,
    'companyid' => 0,
    'verbose' => false,
    'diagnose' => false,
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
$diagnose = !empty($options['diagnose']);

$repository = new expiry_workflow_repository();

if ($diagnose) {
    $filters = ['statusmode' => 'course'];
    if (!$syncall && $companyid > 0) {
        $filters['companyids'] = [$companyid];
    }

    $overview = new overview_repository();
    $rows = $overview->enrolment_status_snapshot_rows($filters);
    $statuscounts = [
        'Active' => 0,
        'Expiring' => 0,
        'Expired' => 0,
        'No document' => 0,
    ];
    $sourcecounts = [];
    $expiringsamples = [];
    $thresholddays = $repository->threshold_days();
    $now = time();
    $thresholdend = $now + ($thresholddays * DAYSECS);
    $eligiblecounts = [
        'status_not_expiring' => 0,
        'company_missing' => 0,
        'expiry_missing_or_past' => 0,
        'expiry_beyond_threshold' => 0,
        'eligible' => 0,
    ];
    $eligiblesamples = [];

    foreach ($rows as $row) {
        $status = (string)($row['status'] ?? 'No document');
        $sourcekind = (string)($row['sourcekind'] ?? '');
        $companyrowid = (int)($row['companyid'] ?? 0);
        $expirytime = (int)($row['expirytime'] ?? 0);

        if (!isset($statuscounts[$status])) {
            $statuscounts[$status] = 0;
        }
        $statuscounts[$status]++;

        if (!isset($sourcecounts[$sourcekind])) {
            $sourcecounts[$sourcekind] = 0;
        }
        $sourcecounts[$sourcekind]++;

        if ($status === 'Expiring' && count($expiringsamples) < 10) {
            $expiringsamples[] = [
                'userid' => (int)($row['userid'] ?? 0),
                'courseid' => (int)($row['courseid'] ?? 0),
                'companyid' => $companyrowid,
                'sourcekind' => $sourcekind,
                'documentid' => (int)($row['documentid'] ?? 0),
                'expiry' => $expirytime > 0 ? userdate($expirytime, '%Y-%m-%d') : '-',
                'status' => $status,
                'course' => (string)($row['course'] ?? ''),
                'employee' => (string)($row['employee'] ?? ''),
            ];
        }

        if ($status !== 'Expiring') {
            $eligiblecounts['status_not_expiring']++;
            continue;
        }

        if ($companyrowid <= 0) {
            $eligiblecounts['company_missing']++;
            continue;
        }

        if ($expirytime <= $now) {
            $eligiblecounts['expiry_missing_or_past']++;
            continue;
        }

        if ($expirytime > $thresholdend) {
            $eligiblecounts['expiry_beyond_threshold']++;
            continue;
        }

        $eligiblecounts['eligible']++;
        if (count($eligiblesamples) < 10) {
            $eligiblesamples[] = [
                'userid' => (int)($row['userid'] ?? 0),
                'courseid' => (int)($row['courseid'] ?? 0),
                'companyid' => $companyrowid,
                'sourcekind' => $sourcekind,
                'documentid' => (int)($row['documentid'] ?? 0),
                'expiry' => $expirytime > 0 ? userdate($expirytime, '%Y-%m-%d %H:%M:%S') : '-',
                'daysleft' => $expirytime > 0 ? (string)floor(($expirytime - $now) / DAYSECS) : '-',
                'employee' => (string)($row['employee'] ?? ''),
                'course' => (string)($row['course'] ?? ''),
            ];
        }
    }

    cli_writeln('Upstream snapshot diagnostics:');
    cli_writeln('Now: ' . userdate($now, '%Y-%m-%d %H:%M:%S'));
    cli_writeln('Threshold days: ' . $thresholddays);
    cli_writeln('Threshold end: ' . userdate($thresholdend, '%Y-%m-%d %H:%M:%S'));
    cli_writeln('Rows: ' . count($rows));
    foreach ($statuscounts as $status => $count) {
        cli_writeln('  Status ' . $status . ': ' . (int)$count);
    }
    foreach ($sourcecounts as $sourcekind => $count) {
        cli_writeln('  Source ' . ($sourcekind !== '' ? $sourcekind : '[empty]') . ': ' . (int)$count);
    }
    cli_writeln('Eligibility counts inside sync_cases filter:');
    foreach ($eligiblecounts as $key => $count) {
        cli_writeln('  ' . $key . ': ' . (int)$count);
    }
    if ($expiringsamples) {
        cli_writeln('Sample Expiring rows:');
        foreach ($expiringsamples as $sample) {
            cli_writeln('  user=' . $sample['userid']
                . ' course=' . $sample['courseid']
                . ' company=' . $sample['companyid']
                . ' source=' . $sample['sourcekind']
                . ' expiry=' . $sample['expiry']
                . ' doc=' . $sample['documentid']
                . ' employee=' . $sample['employee']
                . ' course_name=' . $sample['course']);
        }
    }
    if ($eligiblesamples) {
        cli_writeln('Sample rows that should become workflow candidates:');
        foreach ($eligiblesamples as $sample) {
            cli_writeln('  user=' . $sample['userid']
                . ' course=' . $sample['courseid']
                . ' company=' . $sample['companyid']
                . ' source=' . $sample['sourcekind']
                . ' expiry=' . $sample['expiry']
                . ' daysleft=' . $sample['daysleft']
                . ' doc=' . $sample['documentid']
                . ' employee=' . $sample['employee']
                . ' course_name=' . $sample['course']);
        }
    }
    cli_writeln('');
}

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
