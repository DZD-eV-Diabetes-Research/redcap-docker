# These tests require real REDCap community portal credentials.
# Run locally before each release. See tests/README.md.
#
# The export runs inside the web container via `redcap-export run` against its own
# Apache (http://localhost/api/). In production the same command runs from the cron
# container on a schedule; the crontab generation is covered by test_export_crontab.

import json
import shlex

import pytest

from conftest import (
    BOOT_TIMEOUT_FAST,
    REDCAP_COMMUNITY_PASSWORD,
    REDCAP_COMMUNITY_USER,
    REDCAP_TEST_VERSION,
    RedcapStack,
)

_CREDENTIALS = {
    "REDCAP_COMMUNITY_USER": REDCAP_COMMUNITY_USER,
    "REDCAP_COMMUNITY_PASSWORD": REDCAP_COMMUNITY_PASSWORD,
}
_EXPORT_ENV = {
    "ENABLE_DATA_EXPORT": "true",
    "EXPORT_API_URL": "http://localhost/api/",
}
_SUPER_TOKEN = "E" * 64
_SERVICE_USER = "redcap_docker_export"
_DATA_DICTIONARY = (
    '"Variable / Field Name","Form Name","Section Header","Field Type","Field Label",'
    '"Choices, Calculations, OR Slider Labels","Field Note","Text Validation Type OR Show Slider Number",'
    '"Text Validation Min","Text Validation Max",Identifier?,"Branching Logic (Show field only if...)",'
    '"Required Field?","Custom Alignment","Question Number (surveys only)","Matrix Group Name",'
    '"Matrix Ranking?","Field Annotation"\n'
    'record_id,demographics,,text,"Record ID",,,,,,,,,,,,,\n'
    "name,demographics,,text,Name,,,,,,y,,,,,,,\n"
    'sex,demographics,,radio,Sex,"1, Female | 2, Male",,,,,,,,,,,,\n'
    "weight,visit,,text,Weight,,,,,,,,,,,,,\n"
)
_RECORDS = [
    {"record_id": "1", "name": "Ellen Ripley", "sex": "1", "weight": "61"},
    {"record_id": "2", "name": "Dwayne Hicks", "sex": "2", "weight": "88"},
]


def _api(stack: RedcapStack, **params: str) -> str:
    """POST to the REDCap API from inside the container, return the body."""
    args = " ".join(f"--data-urlencode {shlex.quote(f'{k}={v}')}" for k, v in params.items())
    code, out = stack.exec_run(f"curl -s --fail-with-body -X POST http://localhost/api/ {args}")
    assert code == 0, f"API call {params.get('content')} failed: {out}"
    return out


def _create_project(stack: RedcapStack) -> tuple[int, int]:
    """Create a project with two instruments, two records and one report. Returns (project_id, report_id)."""
    stack.db_execute(
        "UPDATE redcap_user_information SET api_token = %s WHERE username = 'site_admin'",
        (_SUPER_TOKEN,),
    )
    token = _api(
        stack, token=_SUPER_TOKEN, content="project", format="json",
        data=json.dumps([{"project_title": "Export Test", "purpose": 0}]),
    ).strip()
    _api(stack, token=token, content="metadata", format="csv", data=_DATA_DICTIONARY)
    _api(stack, token=token, content="record", format="json", data=json.dumps(_RECORDS))
    pid = stack.db_query("SELECT project_id FROM redcap_user_rights WHERE api_token = %s", (token,))[0][0]
    stack.db_execute(
        "INSERT INTO redcap_reports (project_id, title, user_access) VALUES (%s, 'Sex report', 'ALL')", (pid,)
    )
    report_id = stack.db_query("SELECT MAX(report_id) FROM redcap_reports WHERE project_id = %s", (pid,))[0][0]
    stack.db_execute(
        "INSERT INTO redcap_reports_fields (report_id, field_name, field_order) VALUES (%s, 'record_id', 1), (%s, 'sex', 2)",
        (report_id, report_id),
    )
    return pid, report_id


def _run_export(stack: RedcapStack, jobs: list, *names: str) -> tuple[int, str]:
    return stack.exec_run(
        ["redcap-export", "run", *names],
        user="www-data",
        environment={"EXPORT_JOBS": json.dumps(jobs)},
    )


def _export_files(stack: RedcapStack, job: str) -> list[str]:
    _, out = stack.exec_run(f"ls /opt/redcap-docker/exports/{job}/ 2>/dev/null || true")
    return sorted(f for f in out.splitlines() if f.strip())


def _read_export(stack: RedcapStack, job: str, filename: str) -> str:
    _, out = stack.exec_run(["cat", f"/opt/redcap-docker/exports/{job}/{filename}"])
    return out


@pytest.mark.timeout(300)
def test_data_export(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
) -> None:
    """
    redcap-export provisions a least-privilege service user, exports records, a report
    and the project XML through the API, and applies the retention setting.
    """
    vol, dump = installed_a_snapshot
    redcap_stack.start_from_snapshot(
        vol, dump,
        {"REDCAP_VERSION": REDCAP_TEST_VERSION, **_EXPORT_ENV, **_CREDENTIALS},
    )
    redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)
    pid, report_id = _create_project(redcap_stack)

    jobs = [
        {"name": "records", "project_id": pid, "schedule": "0 2 * * *", "keep": 1},
        {"name": "labels", "project_id": pid, "schedule": "0 2 * * *", "format": "json",
         "options": {"rawOrLabel": "label", "fields": ["record_id", "sex"]}},
        {"name": "report", "project_id": pid, "schedule": "0 2 * * *", "content": "report", "report_id": report_id},
        {"name": "xml", "project_id": pid, "schedule": "0 2 * * *", "content": "project_xml"},
    ]
    code, out = _run_export(redcap_stack, jobs, "records", "labels", "report", "xml")
    assert code == 0, f"redcap-export failed:\n{out}"

    [records_file] = _export_files(redcap_stack, "records")
    records_csv = _read_export(redcap_stack, "records", records_file)
    assert "Ellen Ripley" in records_csv and "Dwayne Hicks" in records_csv, records_csv
    [labels_file] = _export_files(redcap_stack, "labels")
    assert json.loads(_read_export(redcap_stack, "labels", labels_file)) == [
        {"record_id": "1", "sex": "Female"},
        {"record_id": "2", "sex": "Male"},
    ]
    [report_file] = _export_files(redcap_stack, "report")
    assert _read_export(redcap_stack, "report", report_file).splitlines()[0] == "record_id,sex"
    [xml_file] = _export_files(redcap_stack, "xml")
    assert "<ClinicalData" in _read_export(redcap_stack, "xml", xml_file)

    # Exports hold research data: owner and group only
    _, mode = redcap_stack.exec_run(f"stat -c %a /opt/redcap-docker/exports/records/{records_file}")
    assert mode.strip() == "640"

    # The service user can not log in, is no super user and can only export
    assert redcap_stack.db_query(
        "SELECT super_user FROM redcap_user_information WHERE username = %s", (_SERVICE_USER,)
    ) == [(0,)]
    assert not redcap_stack.db_query("SELECT 1 FROM redcap_auth WHERE username = %s", (_SERVICE_USER,))
    [rights] = redcap_stack.db_query(
        "SELECT api_export, api_import, user_rights, design, record_delete, data_entry, data_export_instruments "
        "FROM redcap_user_rights WHERE project_id = %s AND username = %s",
        (pid, _SERVICE_USER),
    )
    assert rights == (1, 0, 0, 0, 0, "[demographics,128][visit,128]", "[demographics,1][visit,1]")

    # keep=1: a second run replaces the first file
    code, out = _run_export(redcap_stack, jobs, "records")
    assert code == 0, out
    files = _export_files(redcap_stack, "records")
    assert len(files) == 1 and files[0] != records_file, files


@pytest.mark.timeout(300)
def test_data_export_failures(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
) -> None:
    """Invalid jobs, missing projects and a suspended service user fail with a clear message."""
    vol, dump = installed_a_snapshot
    redcap_stack.start_from_snapshot(
        vol, dump,
        {"REDCAP_VERSION": REDCAP_TEST_VERSION, **_EXPORT_ENV, **_CREDENTIALS},
    )
    redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)
    pid, _ = _create_project(redcap_stack)

    code, out = _run_export(
        redcap_stack, [{"name": "bad", "project_id": pid, "schedule": "daily", "content": "records"}], "bad"
    )
    assert code == 1 and "'schedule' is required" in out and "'content' must be one of" in out, out

    code, out = _run_export(redcap_stack, [{"name": "gone", "project_id": 999999, "schedule": "* * * * *"}], "gone")
    assert code == 1 and "Project 999999 does not exist" in out, out

    jobs = [{"name": "ok", "project_id": pid, "schedule": "* * * * *"}]
    assert _run_export(redcap_stack, jobs, "ok")[0] == 0
    redcap_stack.db_execute(
        "UPDATE redcap_user_information SET user_suspended_time = NOW() WHERE username = %s", (_SERVICE_USER,)
    )
    code, out = _run_export(redcap_stack, jobs, "ok")
    assert code == 1 and "is suspended" in out, out

    # A failed job turns the cron mode healthcheck red
    code, out = redcap_stack.exec_run(
        "echo 0 > /tmp/cron-ok && CRON_MODE=true CRON_HEALTH_STATE_FILE=/tmp/cron-ok /opt/redcap-docker/assets/scripts/healthcheck.sh"
    )
    assert code == 1 and "Data export job" in out, out


@pytest.mark.timeout(300)
def test_export_crontab(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
) -> None:
    """Every valid job from env vars and job files gets its own crontab line, invalid ones are skipped."""
    vol, dump = installed_a_snapshot
    redcap_stack.start_from_snapshot(
        vol, dump,
        {
            "REDCAP_VERSION": REDCAP_TEST_VERSION,
            **_EXPORT_ENV,
            **_CREDENTIALS,
            "EXPORT_JOBS": json.dumps([{"name": "a", "project_id": 1, "schedule": "0 2 * * *"}]),
            "EXPORT_JOBS_1": json.dumps({"name": "b", "project_id": 1, "schedule": "*/15 * * * *", "format": "json"}),
            "EXPORT_JOBS_2": json.dumps({"name": "a", "project_id": 2, "schedule": "0 3 * * *"}),
            "EXPORT_JOBS_3": json.dumps({"name": "c", "project_id": 1, "schedule": "0 2 * * *", "format": "odm",
                                         "content": "report", "report_id": 1}),
        },
    )
    logs = redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)
    assert "already used by" in logs and "'format' for content 'report'" in logs, logs

    _, crontab = redcap_stack.exec_run("cat /var/spool/cron/crontabs/www-data")
    lines = [line for line in crontab.splitlines() if "export-job.sh" in line]
    assert lines == [
        "0 2 * * * /opt/redcap-docker/assets/scripts/export-job.sh a",
        "*/15 * * * * /opt/redcap-docker/assets/scripts/export-job.sh b",
    ], crontab
