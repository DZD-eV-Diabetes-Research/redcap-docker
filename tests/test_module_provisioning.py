# These tests require real REDCap community portal credentials.
# Run locally before each release. See tests/README.md.
#
# They restore from the session-scoped snapshot and exercise the external
# module provisioning feature (140_module_provisioning.php) end to end.
#
# To stay hermetic (no GitHub / community-portal calls), the tests use the
# "local" source: a minimal but *valid* external module is written into a Docker
# volume and mounted into REDCap's /modules directory. Provisioning then has to
# detect it and — depending on the policy — actually enable it via REDCap's own
# External Module framework, which is what these tests assert against the DB.

import base64
import json
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

_CREDENTIALS = {
    "REDCAP_COMMUNITY_USER": REDCAP_COMMUNITY_USER,
    "REDCAP_COMMUNITY_PASSWORD": REDCAP_COMMUNITY_PASSWORD,
}

# Identity of the throwaway test module.
# REDCap requires the directory suffix to carry a literal "v" ("<prefix>_v<ver>")
# and uses that "v<ver>" token as the module's version everywhere (DB included).
MODULE_PREFIX = "rcdockertest"
MODULE_VERSION = "1.0.0"
MODULE_VERSION_TOKEN = f"v{MODULE_VERSION}"
MODULE_DIRNAME = f"{MODULE_PREFIX}_{MODULE_VERSION_TOKEN}"
MODULE_MOUNT = f"/var/www/html/modules/{MODULE_DIRNAME}"

# A minimal, valid external module. The namespace's final segment ("RcDockerTest")
# is also the main class name and its file name, per the framework's convention.
# No "framework-version" is declared, so it defaults to framework v1, which every
# REDCap version supports — keeping the test version-agnostic.
MODULE_NAMESPACE = "RcDockerTest"
MODULE_CONFIG = {
    "name": "RC Docker Test Module",
    "namespace": MODULE_NAMESPACE,
    "description": "Minimal module used by redcap-docker integration tests.",
    "authors": [
        {"name": "redcap-docker tests", "email": "test@example.local", "institution": "DZD"}
    ],
    "system-settings": [
        {"key": "test_setting", "name": "Test setting", "type": "text"}
    ],
}
MODULE_CLASS_PHP = (
    "<?php\n"
    f"namespace {MODULE_NAMESPACE};\n"
    f"class {MODULE_NAMESPACE} extends \\ExternalModules\\AbstractExternalModule {{}}\n"
)


@pytest.fixture
def module_volume(
    docker_client: docker.DockerClient,
) -> Generator[str, None, None]:
    """
    Create a Docker volume populated with the minimal test module's files
    (config.json + main class file at the volume root) and yield its name.

    Mount it read-only at MODULE_MOUNT so it appears as
    /var/www/html/modules/<prefix>_<version>/ inside the REDCap container.
    """
    vol = docker_client.volumes.create(f"rctest_mod_{uuid.uuid4().hex[:8]}")

    config_b64 = base64.b64encode(json.dumps(MODULE_CONFIG).encode()).decode()
    class_b64 = base64.b64encode(MODULE_CLASS_PHP.encode()).decode()

    # Populate via a throwaway Alpine container (busybox base64 -d).
    docker_client.containers.run(
        "alpine",
        command=[
            "sh",
            "-c",
            'echo "$CONFIG_B64" | base64 -d > /m/config.json && '
            f'echo "$CLASS_B64" | base64 -d > /m/{MODULE_NAMESPACE}.php',
        ],
        environment={"CONFIG_B64": config_b64, "CLASS_B64": class_b64},
        volumes={vol.name: {"bind": "/m", "mode": "rw"}},
        remove=True,
    )

    yield vol.name

    try:
        vol.remove(force=True)
    except Exception:
        pass


@pytest.mark.timeout(300)
def test_module_provisioning_local_enable(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
    module_volume: str,
) -> None:
    """
    A 'local' module marked enabled:true must be system-enabled by REDCap's
    framework, and its declared system setting must be applied.
    """
    spec = {
        "source": "local",
        "prefix": MODULE_PREFIX,
        "version": MODULE_VERSION,
        "enabled": True,
        "settings": {"test_setting": "hello-from-test"},
    }

    vol, dump = installed_a_snapshot
    redcap_stack.start_from_snapshot(
        vol, dump,
        {
            "REDCAP_VERSION": REDCAP_TEST_VERSION,
            "ENABLE_MODULE_PROV": "true",
            "MODULE_PROV": json.dumps([spec]),
            **_CREDENTIALS,
        },
        extra_volumes={module_volume: MODULE_MOUNT},
    )

    logs = redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)
    assert "[MODULE PROVISIONING]" in logs, f"Module provisioning did not run:\n{logs}"

    enabled_version = redcap_stack.module_enabled_version(MODULE_PREFIX)
    assert enabled_version == MODULE_VERSION_TOKEN, (
        f"Expected module '{MODULE_PREFIX}' system-enabled at {MODULE_VERSION_TOKEN}, "
        f"got {enabled_version!r}.\n\nLogs:\n{logs}"
    )

    setting = redcap_stack.module_system_setting(MODULE_PREFIX, "test_setting")
    assert setting == "hello-from-test", (
        f"Expected system setting test_setting='hello-from-test', got {setting!r}"
    )


@pytest.mark.timeout(300)
def test_module_provisioning_install_only(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
    module_volume: str,
) -> None:
    """
    With the default install-only policy (MODULE_PROV_DEFAULT_ENABLE=false and no
    per-entry 'enabled'), a 'local' module's files must be present but the module
    must NOT be system-enabled.
    """
    spec = {
        "source": "local",
        "prefix": MODULE_PREFIX,
        "version": MODULE_VERSION,
    }

    vol, dump = installed_a_snapshot
    redcap_stack.start_from_snapshot(
        vol, dump,
        {
            "REDCAP_VERSION": REDCAP_TEST_VERSION,
            "ENABLE_MODULE_PROV": "true",
            # MODULE_PROV_DEFAULT_ENABLE intentionally left at its default (false).
            "MODULE_PROV": json.dumps([spec]),
            **_CREDENTIALS,
        },
        extra_volumes={module_volume: MODULE_MOUNT},
    )

    logs = redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)
    assert "[MODULE PROVISIONING]" in logs, f"Module provisioning did not run:\n{logs}"

    # Files must be available for an admin to enable later...
    exit_code, _ = redcap_stack.exec_run(f"test -f {MODULE_MOUNT}/config.json")
    assert exit_code == 0, f"Expected module files present at {MODULE_MOUNT}"

    # ...but the module must not have been system-enabled.
    assert redcap_stack.module_enabled_version(MODULE_PREFIX) is None, (
        f"Module '{MODULE_PREFIX}' should NOT be enabled under install-only policy.\n\n"
        f"Logs:\n{logs}"
    )


@pytest.mark.timeout(360)
def test_module_provisioning_version_update(
    redcap_stack: RedcapStack,
    installed_a_snapshot: tuple[str, bytes],
    module_volume: str,
) -> None:
    """
    A module already system-enabled at one version must be switched to a newer
    version on the next boot when the pinned version changes. This exercises the
    disable->enable update path (REDCap refuses to enable a second version of the
    same namespace, so the old enabled version must be cleared first).

    The module's version is determined by its directory name (<prefix>_v<ver>),
    so we mount the same volume at a different "_v" path to simulate a new
    release without needing a second set of files.
    """
    v_old, v_new = "1.0.0", "1.0.1"
    mount_old = f"/var/www/html/modules/{MODULE_PREFIX}_v{v_old}"
    mount_new = f"/var/www/html/modules/{MODULE_PREFIX}_v{v_new}"

    vol, dump = installed_a_snapshot

    # Boot 1: enable the old version.
    redcap_stack.start_from_snapshot(
        vol, dump,
        {
            "REDCAP_VERSION": REDCAP_TEST_VERSION,
            "ENABLE_MODULE_PROV": "true",
            "MODULE_PROV": json.dumps([
                {"source": "local", "prefix": MODULE_PREFIX, "version": v_old, "enabled": True}
            ]),
            **_CREDENTIALS,
        },
        extra_volumes={module_volume: mount_old},
    )
    redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)
    assert redcap_stack.module_enabled_version(MODULE_PREFIX) == f"v{v_old}", (
        "Pre-condition failed: module was not enabled at the old version."
    )

    # Boot 2: same DB, bump the pinned version (mount the files at the new path).
    redcap_stack.restart_redcap(
        {
            "REDCAP_VERSION": REDCAP_TEST_VERSION,
            "ENABLE_MODULE_PROV": "true",
            "MODULE_PROV": json.dumps([
                {"source": "local", "prefix": MODULE_PREFIX, "version": v_new, "enabled": True}
            ]),
            **_CREDENTIALS,
        },
        extra_volumes={module_volume: mount_new},
    )
    logs = redcap_stack.assert_booted(timeout=BOOT_TIMEOUT_FAST)

    assert redcap_stack.module_enabled_version(MODULE_PREFIX) == f"v{v_new}", (
        f"Module was not updated to v{v_new}.\n\nLogs:\n{logs}"
    )
