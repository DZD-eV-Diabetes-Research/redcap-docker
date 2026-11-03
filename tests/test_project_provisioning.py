# These tests require real REDCap community portal credentials.
# Run locally before each release. See tests/README.md.
#
# They restore from the session-scoped snapshot and exercise the project
# provisioning feature (150_project_provisioning.php) end to end: a project is
# created and exported as project XML through the API, then the container is
# restarted with project specs that point to that XML.

import hashlib
import io
import json
import tarfile
import uuid
from typing import Generator

import docker
import pytest

from conftest import (
    BOOT_TIMEOUT_FAST,
    REDCAP_COMMUNITY_PASSWORD,
    REDCAP_COMMUNITY_USER,
    REDCAP_TEST_VERSION,
    RedcapStack,
)
from test_data_export import _api, _create_project
from test_security import _DOC_CAP_ADD, _DOC_CAP_DROP

_BASE_ENV = {
    "REDCAP_VERSION": REDCAP_TEST_VERSION,
    "REDCAP_COMMUNITY_USER": REDCAP_COMMUNITY_USER,
    "REDCAP_COMMUNITY_PASSWORD": REDCAP_COMMUNITY_PASSWORD,
}
_PROJECTS_DIR = "/opt/redcap-docker/projects"
_OWNER = "p.owner"


def _volume_with_files(client: docker.DockerClient, files: dict[str, bytes]) -> str:
    """Create a Docker volume containing the given files at its root, return its name."""
    vol = client.volumes.create(f"rctest_proj_{uuid.uuid4().hex[:8]}")
    buf = io.BytesIO()
    with tarfile.open(fileobj=buf, mode="w") as tar:
        for name, content in files.items():
            info = tarfile.TarInfo(name)
            info.size = len(content)
            info.mode = 0o644
            tar.addfile(info, io.BytesIO(content))
    helper = client.containers.create("alpine", "true", volumes={vol.name: {"bind": "/p", "mode": "rw"}})
    try:
        helper.put_archive("/p", buf.getvalue())
    finally:
        helper.remove(force=True)
    return vol.name


def _record_count(stack: RedcapStack, pid: int) -> int:
    [(data_table,)] = stack.db_query("SELECT data_table FROM redcap_projects WHERE project_id = %s", (pid,))
    return stack.db_query(f"SELECT COUNT(DISTINCT record) FROM {data_table} WHERE project_id = %s", (pid,))[0][0]


def _project_count(stack: RedcapStack) -> int:
    return stack.db_query("SELECT COUNT(*) FROM redcap_projects")[0][0]


@pytest.fixture
def source_xml(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
) -> bytes:
    """Boot REDCap, create a project with records and a report, return its project XML (metadata and data)."""
    vol, dump = installed_a_snapshot
    redcap_stack.start_from_snapshot(vol, dump, _BASE_ENV)
    redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)
    pid, _ = _create_project(redcap_stack)
    [(token,)] = redcap_stack.db_query(
        "SELECT api_token FROM redcap_user_rights WHERE project_id = %s AND username = 'site_admin'", (pid,)
    )
    xml = _api(redcap_stack, token=token, content="project_xml", returnMetadataOnly="false", returnFormat="json")
    assert "<ClinicalData" in xml and "Ellen Ripley" in xml, xml[:2000]
    return xml.encode()


@pytest.fixture
def projects_volume(
    docker_client: docker.DockerClient, redcap_stack: RedcapStack, source_xml: bytes
) -> Generator[str, None, None]:
    # Cut off inside the records: passes REDCap's first check, fails in the import after the project row exists
    broken = source_xml[: source_xml.index(b"<ClinicalData") + 200]
    name = _volume_with_files(docker_client, {"source.xml": source_xml, "broken.xml": broken})
    yield name
    # This teardown runs before the stack's, the REDCap container still mounts the volume
    redcap_stack.stop()
    try:
        docker_client.volumes.get(name).remove(force=True)
    except Exception:
        pass


@pytest.mark.timeout(900)
def test_project_provisioning(
    redcap_stack: RedcapStack,
    source_xml: bytes,
    projects_volume: str,
) -> None:
    """
    Projects are created once from local XML files, with or without records and owner. Invalid specs, checksum
    mismatches, missing owners and broken XML create nothing. Deleted projects are not created again.
    Runs with the minimal capability set from SECURITY.md, the import switches to www-data under it.
    """
    projects_before = _project_count(redcap_stack)
    specs = [
        {"key": "full", "xml": "source.xml", "owner": _OWNER, "sha256": hashlib.sha256(source_xml).hexdigest()},
        {"key": "template", "xml": f"{_PROJECTS_DIR}/source.xml", "title": "Template", "metadata_only": True},
        {"key": "broken", "xml": "broken.xml"},
        {"key": "wrong-sum", "xml": "source.xml", "sha256": "0" * 64},
        {"key": "ghost-owner", "xml": "source.xml", "owner": "ghost"},
        {"key": "missing", "xml": "missing.xml"},
        {"key": "insecure", "url": "http://example.org/project.xml"},
    ]
    env = {
        **_BASE_ENV,
        "ENABLE_PROJECT_PROV": "true",
        "PROJECT_PROV": json.dumps(specs),
        "USER_PROV_1": json.dumps({
            "username": _OWNER, "user_firstname": "Project", "user_lastname": "Owner", "user_email": "owner@example.org",
        }),
    }
    redcap_stack.restart_redcap(
        env, extra_volumes={projects_volume: _PROJECTS_DIR}, cap_drop=_DOC_CAP_DROP, cap_add=_DOC_CAP_ADD
    )
    logs = redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)

    assert "'url' must start with https://" in logs, logs
    assert "Checksum mismatch" in logs, logs
    assert "owner 'ghost' does not exist" in logs, logs
    assert "missing.xml' does not exist" in logs, logs
    assert "Removing the incomplete project" in logs, logs
    assert _project_count(redcap_stack) == projects_before + 2, logs

    pid_full = int(redcap_stack.get_config("redcap_docker_project_prov:full"))
    pid_template = int(redcap_stack.get_config("redcap_docker_project_prov:template"))
    for key in ("broken", "wrong-sum", "ghost-owner", "missing", "insecure"):
        assert redcap_stack.get_config(f"redcap_docker_project_prov:{key}") is None, key

    # Full copy: title from the XML, records, report, the owner with UI creator rights and no API token
    [(title, created_by)] = redcap_stack.db_query(
        "SELECT app_title, created_by FROM redcap_projects WHERE project_id = %s", (pid_full,)
    )
    assert title == "Export Test"
    assert _record_count(redcap_stack, pid_full) == 2
    assert redcap_stack.db_query("SELECT title FROM redcap_reports WHERE project_id = %s", (pid_full,)) == [("Sex report",)]
    assert redcap_stack.db_query(
        "SELECT username, design, api_token, api_export, api_import FROM redcap_user_rights WHERE project_id = %s",
        (pid_full,),
    ) == [(_OWNER, 1, None, 0, 0)]
    assert created_by == redcap_stack.db_query(
        "SELECT ui_id FROM redcap_user_information WHERE username = %s", (_OWNER,)
    )[0][0]
    [(log_table,)] = redcap_stack.db_query("SELECT log_event_table FROM redcap_projects WHERE project_id = %s", (pid_full,))
    assert redcap_stack.db_query(
        f"SELECT COUNT(*) FROM {log_table} WHERE project_id = %s AND description LIKE %s",
        (pid_full, "%redcap-docker project provisioning, key 'full'%"),
    ) == [(1,)]

    # Template: same instruments, no records, no users
    [(title, created_by)] = redcap_stack.db_query(
        "SELECT app_title, created_by FROM redcap_projects WHERE project_id = %s", (pid_template,)
    )
    assert (title, created_by) == ("Template", None)
    assert _record_count(redcap_stack, pid_template) == 0
    assert not redcap_stack.db_query("SELECT 1 FROM redcap_user_rights WHERE project_id = %s", (pid_template,))
    forms = "SELECT DISTINCT form_name FROM redcap_metadata WHERE project_id = %s ORDER BY form_name"
    assert redcap_stack.db_query(forms, (pid_template,)) == redcap_stack.db_query(forms, (pid_full,)) == [
        ("demographics",), ("visit",),
    ]

    # Next start: nothing is created twice, a deleted project stays deleted
    redcap_stack.db_execute("UPDATE redcap_projects SET date_deleted = NOW() WHERE project_id = %s", (pid_template,))
    redcap_stack.restart_redcap(
        env, extra_volumes={projects_volume: _PROJECTS_DIR}, cap_drop=_DOC_CAP_DROP, cap_add=_DOC_CAP_ADD
    )
    logs = redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)
    assert f"already provisioned as project {pid_full}" in logs, logs
    assert f"was provisioned as project {pid_template}, which has been deleted since" in logs, logs
    assert _project_count(redcap_stack) == projects_before + 2, logs
