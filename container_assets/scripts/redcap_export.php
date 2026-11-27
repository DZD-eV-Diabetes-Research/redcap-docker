<?php
# CLI for the periodic data export. Invoked via the `redcap-export` command (redcap_export.sh).
#   redcap-export list           show all valid export jobs
#   redcap-export run <name>...  run the given jobs now
#   redcap-export run-all        run all jobs now
#   redcap-export crontab        print the crontab lines for all jobs (used by 50_define-cronjob.sh)

require_once __DIR__ . '/startup-scripts/php_helpers/data_export.php';

$usage = "Usage: redcap-export list | run <job name> [<job name> ...] | run-all | crontab\n";
$args = array_slice($argv, 1);
if ($args && $args[0] == "--") {
    array_shift($args);
}
$command = $args[0] ?? "";
if (!in_array($command, ["list", "run", "run-all", "crontab"])) {
    fwrite(STDERR, $usage);
    exit(2);
}
if (!filter_var(getenv("ENABLE_DATA_EXPORT"), FILTER_VALIDATE_BOOLEAN)) {
    if ($command == "crontab") {
        exit(0);
    }
    fwrite(STDERR, EXPORT_LOG_PREFIX . "[ERROR]: Data export is disabled. Set ENABLE_DATA_EXPORT=true to use it.\n");
    exit(1);
}

$jobs = collect_export_jobs();

switch ($command) {
    case "list":
        foreach ($jobs as $job) {
            printf("%-24s project %-6d %-12s %-5s keep %-4s schedule '%s'  (%s)\n",
                $job->name, $job->project_id, $job->content . ($job->report_id ? "#$job->report_id" : ""),
                $job->format, $job->keep ?: "all", $job->schedule, $job->source);
        }
        if (!$jobs) {
            print("No valid export jobs configured.\n");
        }
        exit(0);
    case "crontab":
        # export_log() writes problems to stderr, so stdout is only the crontab
        foreach ($jobs as $job) {
            printf("%s /opt/redcap-docker/assets/scripts/export-job.sh %s\n", $job->schedule, $job->name);
        }
        exit(0);
    case "run":
    case "run-all":
        $names = $command == "run-all" ? array_keys($jobs) : array_slice($args, 1);
        if (!$names) {
            fwrite(STDERR, $usage);
            exit(2);
        }
        $failed = 0;
        foreach ($names as $name) {
            if (!isset($jobs[$name])) {
                export_log("ERROR", "No valid export job named '$name'. Run 'redcap-export list' to see the configured jobs.");
                $failed++;
                continue;
            }
            $failed += run_export_job_guarded($jobs[$name]) ? 0 : 1;
        }
        exit($failed ? 1 : 0);
}
