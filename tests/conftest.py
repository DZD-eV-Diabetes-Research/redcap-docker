"""
Shared fixtures and helpers for redcap-docker integration tests.

These tests require real REDCap community portal credentials.
Run locally before each release. See tests/README.md.
"""

from __future__ import annotations

import os
import socket as _socket
import subprocess
import time
import uuid
from pathlib import Path
from typing import Generator

import docker
import docker.errors
import docker.models.containers
import docker.models.networks
import docker.models.volumes
import pymysql
import pytest
from dotenv import load_dotenv

load_dotenv(Path(__file__).parent / ".env")

# ── Configuration ─────────────────────────────────────────────────────────────

REDCAP_COMMUNITY_USER = os.environ.get("REDCAP_COMMUNITY_USER", "")
REDCAP_COMMUNITY_PASSWORD = os.environ.get("REDCAP_COMMUNITY_PASSWORD", "")
REDCAP_TEST_VERSION = os.environ.get("REDCAP_TEST_VERSION", "")
REDCAP_TEST_UPGRADE_VERSION = os.environ.get("REDCAP_TEST_UPGRADE_VERSION", "")
# Empty string (e.g. from .env.example with blank value) falls back to the default.
REDCAP_DOCKER_IMAGE = os.environ.get("REDCAP_DOCKER_IMAGE") or "dzdde/redcap-docker"

_REQUIRED_VARS = [
    "REDCAP_COMMUNITY_USER",
    "REDCAP_COMMUNITY_PASSWORD",
    "REDCAP_TEST_VERSION",
    "REDCAP_TEST_UPGRADE_VERSION",
]

DB_NAME = "redcap"
DB_USER = "redcap"
DB_PASSWORD = "redcap123"
DB_ROOT_PASSWORD = "redcaproot123"
MYSQL_IMAGE = "mysql:lts"

# Generous timeout for download + install.
BOOT_TIMEOUT = 900
# Short timeout when no download is needed (snapshot restore boots in ~30s).
BOOT_TIMEOUT_FAST = 120

# Mount path for the pre-downloaded version-B zip volume inside test containers.
B_ZIP_MOUNT = "/b_zip"


# ── Credential guard ──────────────────────────────────────────────────────────

@pytest.fixture(autouse=True, scope="session")
def require_credentials() -> None:
    missing = [n for n in _REQUIRED_VARS if not os.environ.get(n)]
    if missing:
        pytest.skip(
            f"Missing required env vars: {', '.join(missing)}. "
            "Copy tests/.env.example to tests/.env and fill in your credentials."
        )


# ── Docker-out-of-Docker helpers ──────────────────────────────────────────────

def _running_in_docker() -> bool:
    return os.path.exists("/.dockerenv")


def _self_container_id(client: docker.DockerClient) -> str | None:
    """Return our own container ID when running inside Docker (DooD mode)."""
    hostname = _socket.gethostname()
    try:
        return client.containers.get(hostname).id
    except docker.errors.NotFound:
        pass
    try:
        with open("/proc/self/cgroup") as f:
            for line in f:
                parts = line.strip().split("/")
                if "docker" in line and parts:
                    cid = parts[-1]
                    if len(cid) >= 12:
                        return cid
    except Exception:
        pass
    return None


def _clone_volume(client: docker.DockerClient, src: str, dst: str) -> None:
    """Copy all files from src Docker volume to dst via a transient Alpine container."""
    client.containers.run(
        "alpine",
        command=["sh", "-c", "cp -a /src/. /dst/"],
        volumes={
            src: {"bind": "/src", "mode": "ro"},
            dst: {"bind": "/dst", "mode": "rw"},
        },
        remove=True,
    )


# ── RedcapStack ───────────────────────────────────────────────────────────────

class RedcapStack:
    """
    Manages a single-use MySQL + REDCap Docker stack for one test.

    Fast path (tests that need pre-installed REDCap):
        stack.start_from_snapshot(vol_name, db_dump, env={...})

    Full install path (test_fresh_install only):
        stack.start(env={...})

    Both paths:
        logs = stack.assert_booted()
        stack.restart_redcap(env={...})   # keep DB + volume, swap env
        stack.stop()                      # called automatically by fixture
    """

    BOOT_MARKER = "Start REDCap now..."

    def __init__(self, client: docker.DockerClient, boot_timeout: int = BOOT_TIMEOUT) -> None:
        self.client = client
        self.boot_timeout = boot_timeout
        self.run_id = uuid.uuid4().hex[:8]
        self._rc_seq = 0

        self._network: docker.models.networks.Network | None = None
        self._db: docker.models.containers.Container | None = None
        self._rc: docker.models.containers.Container | None = None
        self._db_vol: docker.models.volumes.Volume | None = None
        self._rc_vol: docker.models.volumes.Volume | None = None
        self._bak_vol: docker.models.volumes.Volume | None = None

        self._db_host: str = "127.0.0.1"
        self._db_port: int = 3306

        self._in_docker: bool = _running_in_docker()
        self._self_id: str | None = None

    # ── Startup ───────────────────────────────────────────────────────────────

    def _start_mysql(self) -> None:
        """Create network + MySQL container and wait for readiness."""
        rid = self.run_id
        self._network = self.client.networks.create(f"rctest_{rid}", driver="bridge")

        self._db_vol = self.client.volumes.create(f"rctest_db_{rid}")
        self._db = self.client.containers.run(
            MYSQL_IMAGE,
            name=f"rctest_db_{rid}",
            network=self._network.name,
            environment={
                "MYSQL_DATABASE": DB_NAME,
                "MYSQL_USER": DB_USER,
                "MYSQL_PASSWORD": DB_PASSWORD,
                "MYSQL_ROOT_PASSWORD": DB_ROOT_PASSWORD,
                "TZ": "UTC",
            },
            volumes={f"rctest_db_{rid}": {"bind": "/var/lib/mysql", "mode": "rw"}},
            ports={"3306/tcp": None},
            command=[
                "--max_allowed_packet=128M",
                "--bind-address=0.0.0.0",
                "--innodb_file_per_table=1",
            ],
            detach=True,
        )

        self._db.reload()
        host_port = int(self._db.ports["3306/tcp"][0]["HostPort"])

        if self._in_docker:
            self._self_id = _self_container_id(self.client)
            if self._self_id:
                self._network.connect(self._self_id)
                self._db_host = f"rctest_db_{rid}"
                self._db_port = 3306
            else:
                self._db_host = os.environ.get("DOCKER_HOST_IP", "host.docker.internal")
                self._db_port = host_port
        else:
            self._db_host = "127.0.0.1"
            self._db_port = host_port

        self._wait_for_mysql()

    def start(
        self,
        redcap_env: dict,
        extra_volumes: dict[str, str] | None = None,
    ) -> "RedcapStack":
        """Full install path: fresh network, MySQL, empty volumes, REDCap container."""
        rid = self.run_id
        self._start_mysql()
        self._rc_vol = self.client.volumes.create(f"rctest_rc_{rid}")
        self._bak_vol = self.client.volumes.create(f"rctest_bak_{rid}")
        self._rc = self._create_redcap(redcap_env, extra_volumes=extra_volumes)
        return self

    def start_from_snapshot(
        self,
        src_rc_vol_name: str,
        db_dump: bytes,
        redcap_env: dict,
        extra_volumes: dict[str, str] | None = None,
        cap_drop: list[str] | None = None,
        cap_add: list[str] | None = None,
    ) -> "RedcapStack":
        """
        Fast path: clone REDCap files from src_rc_vol_name and restore DB from
        db_dump instead of downloading and installing from scratch.
        Saves ~4 minutes per test compared to start().
        extra_volumes maps Docker volume name → container mount path (read-only).
        cap_drop/cap_add let a test pin the container's Linux capability set
        (e.g. the minimal set documented in SECURITY.md).
        """
        rid = self.run_id
        self._start_mysql()
        self._restore_db(db_dump)
        self._rc_vol = self.client.volumes.create(f"rctest_rc_{rid}")
        self._bak_vol = self.client.volumes.create(f"rctest_bak_{rid}")
        _clone_volume(self.client, src_rc_vol_name, self._rc_vol.name)
        self._rc = self._create_redcap(
            redcap_env, extra_volumes=extra_volumes,
            cap_drop=cap_drop, cap_add=cap_add,
        )
        return self

    def restart_redcap(
        self,
        redcap_env: dict,
        extra_volumes: dict[str, str] | None = None,
        cap_drop: list[str] | None = None,
        cap_add: list[str] | None = None,
    ) -> None:
        """Stop and remove the REDCap container; start a new one with different env."""
        if self._rc is not None:
            try:
                self._rc.stop(timeout=15)
            except Exception:
                pass
            try:
                self._rc.remove(force=True)
            except Exception:
                pass
        self._rc = self._create_redcap(
            redcap_env, extra_volumes=extra_volumes,
            cap_drop=cap_drop, cap_add=cap_add,
        )

    def reboot_in_place(self, timeout: int | None = None) -> str:
        """
        Restart the *same* container (docker restart) so its writable layer —
        and thus any files written to it on the previous boot, e.g. /etc/msmtprc —
        survives. Re-runs the startup scripts and waits for a fresh boot marker.
        Use this to exercise restart-only code paths; restart_redcap() recreates
        the container with a clean layer instead.
        """
        assert self._rc is not None, "no REDCap container to reboot"
        before = len(self.logs())
        self._rc.restart(timeout=15)
        deadline = time.monotonic() + (timeout or self.boot_timeout)
        while time.monotonic() < deadline:
            tail = self.logs()[before:]
            if self.BOOT_MARKER in tail:
                return tail
            self._rc.reload()
            if self._rc.status in ("exited", "dead"):
                return tail
            time.sleep(3)
        return self.logs()[before:]

    def _create_redcap(
        self,
        extra_env: dict,
        extra_volumes: dict[str, str] | None = None,
        cap_drop: list[str] | None = None,
        cap_add: list[str] | None = None,
    ) -> docker.models.containers.Container:
        self._rc_seq += 1
        rid = self.run_id
        env = {
            "DB_HOSTNAME": f"rctest_db_{rid}",
            "DB_NAME": DB_NAME,
            "DB_USERNAME": DB_USER,
            "DB_PASSWORD": DB_PASSWORD,
            "DB_SALT": f"rctest_salt_{rid}",
            "REDCAP_INSTALL_ENABLE": "true",
            **extra_env,
        }
        volumes = {
            f"rctest_rc_{rid}": {"bind": "/var/www/html", "mode": "rw"},
            f"rctest_bak_{rid}": {"bind": "/opt/redcap-docker/backups", "mode": "rw"},
        }
        if extra_volumes:
            for vol_name, mount_path in extra_volumes.items():
                volumes[vol_name] = {"bind": mount_path, "mode": "ro"}
        return self.client.containers.run(
            REDCAP_DOCKER_IMAGE,
            name=f"rctest_rc_{rid}_{self._rc_seq}",
            network=self._network.name,
            environment=env,
            volumes=volumes,
            cap_drop=cap_drop,
            cap_add=cap_add,
            detach=True,
        )

    # ── Snapshot helpers ──────────────────────────────────────────────────────

    def take_snapshot(self) -> tuple[str, bytes]:
        """
        After a successful install, return (rc_vol_name, db_dump_bytes).
        Pass the result to start_from_snapshot() in other tests.
        """
        return self._rc_vol.name, self.dump_db()

    def dump_db(self) -> bytes:
        """Run mysqldump inside the DB container and return raw SQL bytes."""
        # Dump as root: mysqldump --single-transaction issues a FLUSH TABLES,
        # which on MySQL 8.4 (the current mysql:lts) requires the RELOAD /
        # FLUSH_TABLES privilege that the unprivileged DB_USER does not have.
        result = self._db.exec_run(
            [
                "mysqldump",
                "-uroot",
                f"-p{DB_ROOT_PASSWORD}",
                "--single-transaction",
                DB_NAME,
            ],
            demux=True,
        )
        stdout, stderr = result.output
        if result.exit_code != 0:
            raise RuntimeError(f"mysqldump failed: {(stderr or b'').decode()}")
        return stdout or b""

    def _restore_db(self, dump: bytes) -> None:
        """Pipe a SQL dump into MySQL via docker exec."""
        # Restore as root: the root-made dump contains session-variable SET
        # statements that require SESSION_VARIABLES_ADMIN on MySQL 8.4, which the
        # unprivileged DB_USER lacks.
        proc = subprocess.run(
            [
                "docker", "exec", "-i",
                self._db.name,
                "mysql", "-uroot", f"-p{DB_ROOT_PASSWORD}", DB_NAME,
            ],
            input=dump,
            capture_output=True,
        )
        if proc.returncode != 0:
            raise RuntimeError(
                f"DB restore failed (exit {proc.returncode}): {proc.stderr.decode()}"
            )

    # ── Log helpers ───────────────────────────────────────────────────────────

    def wait_for_boot(self, marker: str | None = None, timeout: int | None = None) -> str:
        target = marker or self.BOOT_MARKER
        deadline = time.monotonic() + (timeout or self.boot_timeout)
        emitted = 0  # bytes already printed; tracks position for incremental output
        while time.monotonic() < deadline:
            logs = self.logs()
            # Print any new log content since the last poll (visible when pytest -s is active)
            if len(logs) > emitted:
                print(logs[emitted:], end="", flush=True)
                emitted = len(logs)
            if target in logs:
                return logs
            self._rc.reload()
            if self._rc.status in ("exited", "dead"):
                return logs
            time.sleep(3)
        return self.logs()

    def logs(self) -> str:
        return self._rc.logs().decode("utf-8", errors="replace")

    def assert_booted(self, timeout: int | None = None) -> str:
        logs = self.wait_for_boot(timeout=timeout)
        self._rc.reload()
        assert self._rc.status == "running", (
            f"REDCap container exited unexpectedly.\n\nContainer logs:\n{logs}"
        )
        return logs

    # ── DB helpers ────────────────────────────────────────────────────────────

    def _db_conn(self) -> pymysql.connections.Connection:
        return pymysql.connect(
            host=self._db_host,
            port=self._db_port,
            user=DB_USER,
            password=DB_PASSWORD,
            database=DB_NAME,
            connect_timeout=10,
        )

    def db_query(self, sql: str, args: tuple = ()) -> list:
        conn = self._db_conn()
        try:
            with conn.cursor() as cur:
                cur.execute(sql, args)
                return list(cur.fetchall())
        finally:
            conn.close()

    def get_config(self, field: str) -> str | None:
        rows = self.db_query(
            "SELECT value FROM redcap_config WHERE field_name = %s", (field,)
        )
        return rows[0][0] if rows else None

    def get_redcap_version(self) -> str | None:
        return self.get_config("redcap_version")

    def table_exists(self, table: str) -> bool:
        return bool(self.db_query("SHOW TABLES LIKE %s", (table,)))

    def get_user(self, username: str) -> dict | None:
        rows = self.db_query(
            "SELECT username, user_firstname, user_lastname, user_email "
            "FROM redcap_user_information WHERE username = %s",
            (username,),
        )
        if not rows:
            return None
        cols = ["username", "user_firstname", "user_lastname", "user_email"]
        return dict(zip(cols, rows[0]))

    # ── Container exec ────────────────────────────────────────────────────────

    def exec_run(self, cmd: str | list) -> tuple[int, str]:
        result = self._rc.exec_run(
            ["sh", "-c", cmd] if isinstance(cmd, str) else cmd,
            stdout=True,
            stderr=True,
        )
        return result.exit_code, result.output.decode("utf-8", errors="replace")

    def list_backups(self) -> list[str]:
        _, out = self.exec_run("ls /opt/redcap-docker/backups/ 2>/dev/null || true")
        return [f for f in out.splitlines() if f.strip()]

    # ── MySQL readiness ───────────────────────────────────────────────────────

    def _wait_for_mysql(self, timeout: int = 120) -> None:
        deadline = time.monotonic() + timeout
        last_err: Exception | None = None
        while time.monotonic() < deadline:
            try:
                pymysql.connect(
                    host=self._db_host,
                    port=self._db_port,
                    user=DB_USER,
                    password=DB_PASSWORD,
                    database=DB_NAME,
                    connect_timeout=5,
                ).close()
                return
            except Exception as e:
                last_err = e
                time.sleep(2)
        raise TimeoutError(f"MySQL not ready after {timeout}s. Last error: {last_err}")

    # ── Teardown ──────────────────────────────────────────────────────────────

    def stop(self, keep_rc_vol: bool = False) -> None:
        """
        Stop and remove all containers, volumes, and the network.
        Set keep_rc_vol=True to preserve the REDCap file volume for snapshotting.
        """
        for container in [self._rc, self._db]:
            if container is not None:
                try:
                    container.stop(timeout=10)
                except Exception:
                    pass
                try:
                    container.remove(force=True)
                except Exception:
                    pass

        if self._in_docker and self._self_id and self._network:
            try:
                self._network.disconnect(self._self_id)
            except Exception:
                pass

        vols = [self._bak_vol, self._db_vol]
        if not keep_rc_vol:
            vols.append(self._rc_vol)
        for vol in vols:
            if vol is not None:
                try:
                    vol.remove(force=True)
                except Exception:
                    pass

        if self._network is not None:
            try:
                self._network.remove()
            except Exception:
                pass


# ── Fixtures ──────────────────────────────────────────────────────────────────

@pytest.fixture(scope="session")
def docker_client() -> Generator[docker.DockerClient, None, None]:
    client = docker.from_env()
    yield client
    client.close()


@pytest.fixture(scope="session")
def installed_a_snapshot(
    docker_client: docker.DockerClient,
    require_credentials: None,
) -> Generator[tuple[str, bytes], None, None]:
    """
    Installs REDCAP_TEST_VERSION exactly once per session.
    Yields (rc_vol_name, db_dump_bytes).

    Tests that need a pre-installed REDCap use start_from_snapshot() with this
    data instead of re-downloading and re-installing — saves ~4 min per test.
    The rc_vol is cloned per test so each test gets its own isolated copy.
    """
    stack = RedcapStack(docker_client)
    stack.start({
        "REDCAP_VERSION": REDCAP_TEST_VERSION,
        "REDCAP_COMMUNITY_USER": REDCAP_COMMUNITY_USER,
        "REDCAP_COMMUNITY_PASSWORD": REDCAP_COMMUNITY_PASSWORD,
    })
    stack.assert_booted()

    snapshot = stack.take_snapshot()

    # Tear down everything except the RC file volume — tests will clone it.
    stack.stop(keep_rc_vol=True)

    yield snapshot

    # Final cleanup of the shared file volume.
    if stack._rc_vol is not None:
        try:
            stack._rc_vol.remove(force=True)
        except Exception:
            pass


@pytest.fixture(scope="session")
def b_zip_volume(
    docker_client: docker.DockerClient,
    require_credentials: None,
) -> Generator[tuple[str, str], None, None]:
    """
    Downloads REDCAP_TEST_UPGRADE_VERSION exactly once per session into a Docker volume.
    Yields (volume_name, in-container zip path).

    Mount the volume at B_ZIP_MOUNT in test containers to access the zip:
      extra_volumes={vol_name: B_ZIP_MOUNT}

    Pass zip_path to redcap-upgrade via --zip, or set REDCAP_ZIP_PATH so the
    reconciler uses it instead of downloading.
    """
    rid = uuid.uuid4().hex[:8]
    zip_vol = docker_client.volumes.create(f"rctest_bzip_{rid}")
    zip_filename = f"redcap_v{REDCAP_TEST_UPGRADE_VERSION}.zip"
    zip_path = f"{B_ZIP_MOUNT}/{zip_filename}"

    php_cmd = (
        "require '/opt/redcap-docker/assets/scripts/startup-scripts/php_helpers/redcap_community_downloader.php';"
        "download_redcap_from_community(getenv('RC_USER'), getenv('RC_PASS'), getenv('RC_VER'), getenv('RC_DIR'));"
    )

    container = docker_client.containers.run(
        REDCAP_DOCKER_IMAGE,
        command=["php", "-r", php_cmd],
        environment={
            "RC_USER": REDCAP_COMMUNITY_USER,
            "RC_PASS": REDCAP_COMMUNITY_PASSWORD,
            "RC_VER": REDCAP_TEST_UPGRADE_VERSION,
            "RC_DIR": B_ZIP_MOUNT,
        },
        volumes={zip_vol.name: {"bind": B_ZIP_MOUNT, "mode": "rw"}},
        detach=True,
    )

    try:
        result = container.wait(timeout=600)
        logs_str = container.logs().decode("utf-8", errors="replace")
        print(f"\n[b_zip_volume] Download output:\n{logs_str}")
        if result["StatusCode"] != 0:
            raise RuntimeError(
                f"Version-B zip download failed (exit {result['StatusCode']}):\n{logs_str}"
            )
    finally:
        try:
            container.remove(force=True)
        except Exception:
            pass

    yield zip_vol.name, zip_path

    try:
        zip_vol.remove(force=True)
    except Exception:
        pass


@pytest.fixture
def redcap_stack(docker_client: docker.DockerClient) -> Generator[RedcapStack, None, None]:
    """
    Yields an unstarted RedcapStack. Call stack.start() or
    stack.start_from_snapshot() to bring it up. Teardown is automatic.
    """
    stack = RedcapStack(docker_client)
    yield stack
    stack.stop()
