"""Build a shareable, credential-free JSON and Markdown report from bounded daily logs."""

import argparse
import datetime as dt
import json
import pathlib

from run import atomic_json, percentile


def rows(directory, kind):
    for path in sorted(directory.glob(f"{kind}-????-??-??.jsonl")):
        with path.open(encoding="utf-8") as file:
            for number, line in enumerate(file, 1):
                try:
                    yield json.loads(line)
                except json.JSONDecodeError as error:
                    raise ValueError(f"Incomplete/corrupt log: {path.name}:{number}") from error


def build(directory):
    state = json.loads((directory / "state.json").read_text())
    endpoints = {}
    coverage, measured = 0, 0
    worst = []
    hourly = {}
    for window in rows(directory, "http"):
        start = window["timestamp"] - window["seconds"]
        end = min(window["timestamp"], state["ends_at"])
        steady_seconds = max(0, end - max(start, state["steady_at"]))
        measured += steady_seconds
        if sum(window["active_users"].values()) == 50:
            coverage += steady_seconds
        hour = dt.datetime.fromtimestamp(window["timestamp"], dt.timezone.utc).strftime("%Y-%m-%dT%H:00Z")
        trend = hourly.setdefault(hour, {"requests": 0, "errors": 0, "histogram": {},
                                         "minimum_active": 50})
        trend["minimum_active"] = min(trend["minimum_active"], sum(window["active_users"].values()))
        for name, row in window["endpoints"].items():
            total = endpoints.setdefault(name, {"count": 0, "errors": 0, "bytes": 0,
                                                "max_ms": 0, "histogram": {}, "statuses": {}})
            for key in ("count", "errors", "bytes"):
                total[key] += row[key]
            total["max_ms"] = max(total["max_ms"], row["max_ms"])
            for key in ("histogram", "statuses"):
                for bucket, amount in row[key].items():
                    total[key][bucket] = total[key].get(bucket, 0) + amount
            trend["requests"] += row["count"]
            trend["errors"] += row["errors"]
            for bucket, amount in row["histogram"].items():
                trend["histogram"][bucket] = trend["histogram"].get(bucket, 0) + amount
            worst.append({"timestamp": window["timestamp"], "endpoint": name,
                          "p95_ms": percentile(row["histogram"], .95), "errors": row["errors"],
                          "requests": row["count"]})
        worst = sorted(worst, key=lambda w: (w["errors"] / w["requests"], w["p95_ms"]), reverse=True)[:20]
    for row in list(endpoints.values()) + list(hourly.values()):
        row["p50_ms"] = percentile(row["histogram"], .50)
        row["p95_ms"] = percentile(row["histogram"], .95)
        row["p99_ms"] = percentile(row["histogram"], .99)
        del row["histogram"]
    server = {"samples": 0, "peak_cpu_percent": 0, "peak_memory_percent": 0,
              "peak_swap_bytes": 0, "minimum_disk_free_bytes": None,
              "first": None, "last": None, "hourly": {}}
    for sample in rows(directory, "server"):
        server["samples"] += 1
        server["peak_cpu_percent"] = max(server["peak_cpu_percent"], sample["cpu_percent"])
        server["peak_memory_percent"] = max(server["peak_memory_percent"], sample["memory_used_percent"])
        server["peak_swap_bytes"] = max(server["peak_swap_bytes"], sample["swap_used"])
        server["minimum_disk_free_bytes"] = min(
            sample["disk_free"], server["minimum_disk_free_bytes"] or sample["disk_free"])
        compact = {key: sample[key] for key in ("timestamp", "cores", "memory_total", "database", "containers")}
        if server["first"] is None:
            server["first"] = compact
        server["last"] = compact
        hour = dt.datetime.fromtimestamp(sample["timestamp"], dt.timezone.utc).strftime("%Y-%m-%dT%H:00Z")
        trend = server["hourly"].setdefault(hour, {
            "samples": 0, "cpu_sum": 0, "peak_cpu_percent": 0, "peak_memory_percent": 0,
            "minimum_disk_free_bytes": sample["disk_free"],
        })
        trend["samples"] += 1
        trend["cpu_sum"] += sample["cpu_percent"]
        trend["peak_cpu_percent"] = max(trend["peak_cpu_percent"], sample["cpu_percent"])
        trend["peak_memory_percent"] = max(trend["peak_memory_percent"], sample["memory_used_percent"])
        trend["minimum_disk_free_bytes"] = min(trend["minimum_disk_free_bytes"], sample["disk_free"])
        trend["database_bytes"] = sample["database"]["bytes"]
    for trend in server["hourly"].values():
        trend["mean_cpu_percent"] = round(trend.pop("cpu_sum") / trend["samples"], 2)
    if server["first"]:
        server["database_growth_bytes"] = server["last"]["database"]["bytes"] - server["first"]["database"]["bytes"]
    total = sum(row["count"] for row in endpoints.values())
    errors = sum(row["errors"] for row in endpoints.values())
    slo_failures = []
    for name, row in endpoints.items():
        target = 750 if name.endswith((".paint", ".send", ".lock")) else 500
        if row["p95_ms"] > target or row["p99_ms"] > 1500 or row["errors"] / row["count"] > .001:
            slo_failures.append(name)
    required = state["ends_at"] - state["steady_at"]
    evidence_complete = (state["status"] == "completed" and not state["smoke"]
                         and required >= 14 * 86400 and coverage >= required * .999)
    verdict = "PASS" if evidence_complete and not slo_failures else "INCOMPLETE" if not evidence_complete else "FAIL"
    return {
        "schema": 1, "generated_at": dt.datetime.now(dt.timezone.utc).isoformat(),
        "run_id": state["run_id"], "target": state["target"], "status": state["status"],
        "wall": state["wall"],
        "reason": state["reason"], "verdict": verdict, "smoke": state["smoke"],
        "started_at": state["started_at"], "steady_at": state["steady_at"], "ends_at": state["ends_at"],
        "required_steady_seconds": required, "measured_steady_seconds": measured,
        "fifty_user_seconds": coverage, "coverage_percent": round(100 * coverage / required, 4),
        "requests": total, "errors": errors, "slo_failures": slo_failures,
        "endpoints": endpoints, "hourly": hourly, "worst_windows": worst, "server": server,
    }


def markdown(report):
    lines = [
        "# PixRiot: 50-Nutzer-Dauertest", "",
        f"- Ergebnis: **{report['verdict']}**; Status: {report['status']}",
        f"- Ziel: {report['target']}; Test-ID: {report['run_id']}",
        f"- Test-Wall: {report['wall']['width']} x {report['wall']['height']}",
        f"- Abdeckung mit 50 aktiven Sitzungen: {report['coverage_percent']}%",
        f"- Anfragen: {report['requests']}; Fehler: {report['errors']}",
        f"- Abbruchgrund: {report['reason'] or '-'}", "",
        "## Antwortzeiten (ms, Histogramm-Aufloesung 10 ms)", "",
        "| Ablauf | Anfragen | Fehler | p50 | p95 | p99 | Maximum |",
        "|---|---:|---:|---:|---:|---:|---:|",
    ]
    for name, row in sorted(report["endpoints"].items()):
        lines.append(f"| {name} | {row['count']} | {row['errors']} | {row['p50_ms']} | "
                     f"{row['p95_ms']} | {row['p99_ms']} | {row['max_ms']} |")
    server = report["server"]
    lines += ["", "## Server", "",
              f"- CPU-Spitze: {server['peak_cpu_percent']}%; RAM-Spitze: {server['peak_memory_percent']}%",
              f"- Minimale freie Datenplatte: {server['minimum_disk_free_bytes']} Bytes",
              f"- Maximal belegter Swap: {server['peak_swap_bytes']} Bytes",
              f"- Servermessungen: {server['samples']}", "",
              f"- Datenbankwachstum: {server.get('database_growth_bytes', 'noch keine Messung')} Bytes", "",
              "## Bewertung", "",
              "PASS verlangt 14 Tage, mindestens 99,9% Zeitabdeckung mit 50 Sitzungen,",
              "HTTP-Fehler <=0,1%, p95 <=500 ms fuer Lesen bzw. <=750 ms fuer",
              "Malen/Chat/Reservieren und p99 <=1500 ms je Ablauf.",
              "Ein Probelauf, Abbruch oder eine unvollstaendige Messung ist kein Kapazitaetsnachweis.",
              "INCOMPLETE kann bereits auffaellige SLO-Abweichungen enthalten:", "",
              ", ".join(report["slo_failures"]) or "Keine bisher gemessenen SLO-Abweichungen.", "",
              "Stuendliche Verlaeufe, schlechteste Messfenster, erste/letzte Datenbankgroesse,",
              "Transaktionen und Containerzustaende stehen in report.json; Rohwerte in server-*.jsonl.",
              "HTTP-Simulation misst keine Smartphone-Bildrate, Touchlatenz oder Layoutfehler.",
              "50 Testnutzer sind kein Nachweis fuer beliebig viele weitere reale Nutzer.", ""]
    return "\n".join(lines)


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("directory", type=pathlib.Path)
    args = parser.parse_args()
    report = build(args.directory)
    atomic_json(args.directory / "report.json", report)
    (args.directory / "report.md").write_text(markdown(report), encoding="utf-8")
    print(f"{report['verdict']}: {args.directory / 'report.md'}")
