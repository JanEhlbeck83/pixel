"""Read-only Linux/Docker probe. Sent over SSH stdin; no installation on the server."""

import argparse
import json
import pathlib
import re
import shutil
import subprocess
import time


def command(args):
    return subprocess.run(args, check=True, capture_output=True, text=True, timeout=15).stdout


def probe(project, data_path):
    if not re.fullmatch(r"[a-z0-9][a-z0-9_-]{0,62}", project):
        raise ValueError("Invalid Compose project")
    mem = {}
    for line in pathlib.Path("/proc/meminfo").read_text().splitlines():
        key, value = line.split(":", 1)
        mem[key] = int(value.strip().split()[0]) * 1024
    cpu = list(map(int, pathlib.Path("/proc/stat").read_text().splitlines()[0].split()[1:9]))
    disk = shutil.disk_usage(data_path)
    containers = []
    ids = command(["docker", "ps", "-aq", "--filter", f"label=com.docker.compose.project={project}",
                   "--filter", "label=com.docker.compose.oneoff=False"]).split()
    if not ids:
        raise RuntimeError("No containers found for the specified Compose project")
    stats_text = command(["docker", "stats", "--no-stream", "--format", "{{json .}}", *ids])
    stats = [json.loads(line) for line in stats_text.splitlines() if line.strip()]
    for container_id in ids:
        state = json.loads(command([
            "docker", "inspect", "--format",
            '{"name":{{json .Name}},"restarts":{{.RestartCount}},'
            '"running":{{.State.Running}},"oom":{{.State.OOMKilled}},'
            '"service":{{json (index .Config.Labels "com.docker.compose.service")}}}', container_id,
        ]))
        if state["service"] in ("app", "nginx", "scheduler", "postgres"):
            containers.append(state)
    if {container["service"] for container in containers} != {"app", "nginx", "scheduler", "postgres"}:
        raise RuntimeError("Missing required app/nginx/scheduler/postgres container")
    db_ids = command(["docker", "ps", "-q", "--filter",
                      f"label=com.docker.compose.project={project}",
                      "--filter", "label=com.docker.compose.service=postgres"]).split()
    if len(db_ids) != 1:
        raise RuntimeError("Expected exactly one running staging postgres container")
    sql = """SELECT json_build_object(
        'bytes', pg_database_size(current_database()),
        'connections', numbackends, 'commits', xact_commit, 'rollbacks', xact_rollback,
        'deadlocks', deadlocks, 'cache_hits', blks_hit, 'disk_reads', blks_read,
        'messages', (SELECT count(*) FROM community_project_messages),
        'pixels', (SELECT count(*) FROM pixels),
        'project_pixels', (SELECT count(*) FROM community_project_pixels),
        'orders', (SELECT count(*) FROM orders))
        FROM pg_stat_database WHERE datname=current_database();"""
    database = json.loads(command([
        "docker", "exec", db_ids[0], "sh", "-c",
        'exec psql -X -v ON_ERROR_STOP=1 -At -U "$POSTGRES_USER" -d "$POSTGRES_DB" -c "$1"',
        "probe", sql,
    ]))
    return {
        "timestamp": time.time(), "cpu_total": sum(cpu), "cpu_idle": cpu[3] + cpu[4],
        "cpu_iowait": cpu[4], "cores": sum(
            1 for line in pathlib.Path("/proc/stat").read_text().splitlines()
            if re.match(r"cpu\d+ ", line)),
        "memory_total": mem["MemTotal"], "memory_available": mem["MemAvailable"],
        "swap_used": mem["SwapTotal"] - mem["SwapFree"],
        "load": pathlib.Path("/proc/loadavg").read_text().strip(),
        "disk_total": disk.total, "disk_free": disk.free,
        "containers": containers, "docker_stats": stats, "database": database,
    }


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--project", required=True)
    parser.add_argument("--data-path", required=True)
    args = parser.parse_args()
    print(json.dumps(probe(args.project, args.data_path)))
