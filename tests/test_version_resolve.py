# Tests for the symbolic version resolver (REDCAP_VERSION=latest-lts / latest-std).
#
# These run the resolver inside the built image against the PUBLIC community
# portal version-listing endpoint. They perform NO download and need NO
# credentials — so they add zero load to the consortium's download policy.
# See tests/README.md.

import re

import docker
import pytest

from conftest import REDCAP_DOCKER_IMAGE

_HELPER = (
    "/opt/redcap-docker/assets/scripts/startup-scripts/"
    "php_helpers/redcap_community_downloader.php"
)

_VERSION_RE = re.compile(r"^\d+\.\d+\.\d+$")


def _run_php(docker_client: docker.DockerClient, code: str) -> tuple[int, str]:
    """Run a php -r snippet inside the built image, return (exit_code, output)."""
    # Split entrypoint/command so the image's default CMD (container_start.sh)
    # is fully overridden rather than appended as a stray php argument.
    container = docker_client.containers.run(
        REDCAP_DOCKER_IMAGE,
        entrypoint=["php"],
        command=["-r", f'require "{_HELPER}"; {code}'],
        detach=True,
    )
    try:
        result = container.wait()
        logs = container.logs().decode("utf-8", "replace").strip()
        return int(result.get("StatusCode", 1)), logs
    finally:
        container.remove(force=True)


@pytest.mark.timeout(120)
@pytest.mark.parametrize("symbol", ["latest-lts", "latest-std"])
def test_resolver_returns_concrete_version(
    docker_client: docker.DockerClient, symbol: str
) -> None:
    """latest-lts / latest-std must resolve to a concrete X.Y.Z version."""
    code, out = _run_php(docker_client, f'echo resolve_redcap_version("{symbol}");')
    assert code == 0, f"resolver exited {code} for {symbol}.\nOutput:\n{out}"
    assert _VERSION_RE.match(out), f"Expected an X.Y.Z version for {symbol}, got: {out!r}"


@pytest.mark.timeout(60)
def test_resolver_is_case_insensitive(docker_client: docker.DockerClient) -> None:
    code, out = _run_php(docker_client, 'echo resolve_redcap_version("LATEST-LTS");')
    assert code == 0 and _VERSION_RE.match(out), f"Got: {out!r} (exit {code})"


@pytest.mark.timeout(60)
def test_resolver_rejects_unknown_symbol(docker_client: docker.DockerClient) -> None:
    """An unknown symbolic value must raise (non-zero exit)."""
    code, out = _run_php(
        docker_client, 'echo resolve_redcap_version("latest-bogus");'
    )
    assert code != 0, f"Expected failure for unknown symbol, but exited 0.\nOutput:\n{out}"


@pytest.mark.timeout(60)
def test_is_symbolic_classification(docker_client: docker.DockerClient) -> None:
    code, out = _run_php(
        docker_client,
        'echo (int)is_symbolic_redcap_version("latest-lts");'
        'echo (int)is_symbolic_redcap_version("14.9.5");',
    )
    assert code == 0, f"exit {code}\n{out}"
    assert out == "10", f"Expected '10' (symbolic=1, concrete=0), got: {out!r}"
