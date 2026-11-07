import argparse
import datetime as dt
import http.server
import json
import pathlib
import sys
import tempfile
import threading
import time
import unittest
import unittest.mock as mock

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parents[1] / "scripts" / "soak"))
import run
import report
import server_probe


def manifest():
    return {
        "schema": 1, "run_id": "synthetic-run", "target": run.TARGET,
        "wall": {"width": 3840, "height": 2160},
        "expires_at": (dt.datetime.now(dt.timezone.utc) + dt.timedelta(days=16)).isoformat(),
        "users": [{"id": i + 1, "email": f"soak-{i}@example.invalid", "password": "test-only",
                   "wall_x": 64, "wall_y": 180 + i, "project_row": i % 25,
                   "project_id": i // 25 + 1, "group_id": i // 25 + 1} for i in range(50)],
    }


def probe():
    return {
        "timestamp": time.time(), "cpu_total": 1000, "cpu_idle": 500,
        "cpu_iowait": 0, "cores": 2, "memory_total": 4 * 1024**3,
        "memory_available": 2 * 1024**3, "swap_used": 0,
        "disk_total": 120 * 1024**3, "disk_free": 100 * 1024**3,
        "containers": [{"name": "staging-app", "running": True, "oom": False, "restarts": 0}],
        "database": {"orders": 0, "bytes": 1000},
    }


class Handler(http.server.BaseHTTPRequestHandler):
    seen = []

    def log_message(self, *_):
        pass

    def do_GET(self):
        self.reply()

    def do_POST(self):
        self.reply()

    def reply(self):
        payload = self.rfile.read(int(self.headers.get("Content-Length", 0)))
        self.seen.append((self.command, self.path, dict(self.headers), payload))
        if self.path == "/login" and self.command == "POST":
            self.send_response(302)
            self.send_header("Location", "/dashboard")
            self.send_header("Set-Cookie", "session=synthetic; Path=/")
            self.end_headers()
            return
        if self.path in ("/login", "/de"):
            body = b'<meta name="csrf-token" content="synthetic-csrf">'
        elif self.path == "/api/soak/status":
            body = json.dumps({"run_id": "synthetic-run", "target": self.server.target,
                               "users": 50, "user_id": 1, "wall": {"width": 3840, "height": 2160}}).encode()
        elif self.path == "/api/wall":
            body = b'{"width":3840,"height":2160}'
        elif self.path == "/api/pixels/status":
            body = b'{"authenticated":true,"free":50}'
        elif self.path == "/api/pixels":
            body = b'{"success":true}'
        elif self.path == "/api/pixels/locks":
            body = b"not-json"
        else:
            self.send_response(503)
            self.end_headers()
            return
        self.send_response(200)
        self.end_headers()
        self.wfile.write(body)


class SoakTests(unittest.TestCase):
    def test_histograms_percentiles_and_reset(self):
        metrics = run.Metrics()
        for i in range(100):
            metrics.record("quota", i + 1, 200, i != 0, 20)
        metrics.touch(1, "wall")
        window = metrics.take()
        self.assertEqual(100, window["endpoints"]["quota"]["count"])
        self.assertEqual(1, window["endpoints"]["quota"]["errors"])
        self.assertEqual(100, run.percentile(window["endpoints"]["quota"]["histogram"], .95))
        self.assertEqual({"wall": 1}, window["active_users"])
        self.assertEqual({}, metrics.take()["endpoints"])

    def test_atomic_report_handles_only_transient_windows_sharing_violations(self):
        with tempfile.TemporaryDirectory(prefix="pixriot-soak-atomic-") as directory:
            path = pathlib.Path(directory) / "state.json"
            replace = pathlib.Path.replace
            sharing = PermissionError("temporary Windows sharing lock")
            sharing.winerror = 32
            calls = 0

            def delayed_replace(source, destination):
                nonlocal calls
                calls += 1
                if calls == 1:
                    raise sharing
                return replace(source, destination)

            with mock.patch.object(pathlib.Path, "replace", delayed_replace), mock.patch.object(run.time, "sleep"):
                run.atomic_json(path, {"status": "running"})
            self.assertEqual(2, calls)
            self.assertEqual({"status": "running"}, json.loads(path.read_text()))
            with mock.patch.object(pathlib.Path, "replace", side_effect=PermissionError("permission denied")):
                with self.assertRaises(PermissionError):
                    run.atomic_json(path, {"status": "aborted"})
            self.assertEqual({"status": "running"}, json.loads(path.read_text()))

    def test_manifest_target_user_count_and_expiry(self):
        value = manifest()
        run.validate_manifest(value)
        for field, replacement in (("target", "https://pixriot.online"), ("users", []),
                                   ("expires_at", "2020-01-01T00:00:00+00:00")):
            bad = {**value, field: replacement}
            with self.assertRaises(ValueError):
                run.validate_manifest(bad)

    def test_client_real_http_cookies_csrf_and_strict_route_allowlist(self):
        Handler.seen = []
        server = http.server.ThreadingHTTPServer(("127.0.0.1", 0), Handler)
        server.target = f"http://127.0.0.1:{server.server_port}"
        thread = threading.Thread(target=server.serve_forever)
        thread.start()
        try:
            metrics = run.Metrics()
            client = run.Client(server.target, manifest()["users"][0], "synthetic-run", metrics, threading.Event())
            client.login()
            client.request("POST", "/api/pixels", "wall.paint", {"x": 64, "y": 180, "color": "#ffffff"})
            post = Handler.seen[-1]
            self.assertEqual("synthetic-csrf", post[2]["X-Csrf-Token"])
            self.assertIn("session=synthetic", post[2]["Cookie"])
            for path in ("/api/shop", "/shop/checkout", "/logout", "https://example.com/de"):
                with self.assertRaises(ValueError):
                    client.request("GET", path, "forbidden")
            with self.assertRaises(json.JSONDecodeError):
                client.request("GET", "/api/pixels/locks", "wall.locks")
            with self.assertRaises(run.RequestFailure):
                client.request("GET", "/api/community/phase-updates", "phase")
            self.assertEqual(1, metrics.take()["endpoints"]["wall.locks"]["errors"])
            self.assertFalse(any("/dashboard" in row[1] for row in Handler.seen))
        finally:
            server.shutdown()
            server.server_close()
            thread.join()

    def test_http_and_resource_abort_thresholds(self):
        safety = run.Safety()
        window = {"endpoints": {"paint": {"count": 100, "errors": 2, "histogram": {"10": 100}}}}
        for _ in range(4):
            self.assertIsNone(safety.http(window))
        self.assertIsNotNone(safety.http(window))
        self.assertIsNone(run.Safety().resources(probe()))
        for change in (
            {"disk_free": 1024}, {"database": {"orders": 1}},
            {"containers": [{"name": "app", "running": False, "oom": False, "restarts": 0}]},
        ):
            self.assertIsNotNone(run.Safety().resources({**probe(), **change}))
        safety = run.Safety()
        for i in range(10):
            value = {**probe(), "memory_available": 1}
            result = safety.resources(value)
            self.assertEqual(i == 9, result is not None)
        safety = run.Safety()
        safety.resources(probe())
        changed = probe()
        changed["containers"][0]["restarts"] = 1
        self.assertIsNotNone(safety.resources(changed))

    def test_resume_preserves_deadline_and_aborted_run_cannot_restart(self):
        with tempfile.TemporaryDirectory(prefix="pixriot-soak-test-") as directory:
            root = pathlib.Path(directory)
            path = root / "manifest.json"
            path.write_text(json.dumps(manifest()))
            args = argparse.Namespace(manifest=path, output=root / "results", smoke=False, resume=False)
            first = run.Run(args)
            end = first.state["ends_at"]
            first.file_lock.close()
            args.resume = True
            second = run.Run(args)
            self.assertEqual(end, second.state["ends_at"])
            second.abort("test threshold")
            second.file_lock.close()
            with self.assertRaises(ValueError):
                run.Run(args)

    def test_server_telemetry_failure_aborts_and_ssh_is_not_a_shell(self):
        runner = run.Run.__new__(run.Run)
        runner.stop = threading.Event()
        runner.safety = run.Safety()
        runner.probe = mock.Mock(side_effect=OSError("test failure"))
        runner.write = mock.Mock()
        runner.abort = mock.Mock(side_effect=lambda *_: runner.stop.set())
        with mock.patch.object(runner.stop, "wait", return_value=False):
            runner.monitor()
        self.assertEqual(3, runner.probe.call_count)
        runner.abort.assert_called_once()
        runner.args = argparse.Namespace(ssh_host="alias; bad", compose_project="pixel-staging", data_path="/opt/pixel-data")
        with self.assertRaises(ValueError):
            run.Run.probe(runner)

    def test_elapsed_deadline_never_starts_more_traffic(self):
        with tempfile.TemporaryDirectory(prefix="pixriot-soak-deadline-") as directory:
            root = pathlib.Path(directory)
            path = root / "manifest.json"
            path.write_text(json.dumps(manifest()))
            args = argparse.Namespace(manifest=path, output=root / "results", smoke=False, resume=False)
            first = run.Run(args)
            first.state.update(status="interrupted", ends_at=time.time() - 10)
            run.atomic_json(first.state_path, first.state)
            first.file_lock.close()
            args.resume = True
            second = run.Run(args)
            with mock.patch.object(second, "probe") as remote:
                self.assertEqual(0, second.execute())
                remote.assert_not_called()
            self.assertEqual("completed", second.state["status"])
            second.file_lock.close()

    def test_per_endpoint_latency_is_not_hidden_by_fast_polling(self):
        metrics = run.Metrics()
        for _ in range(1000):
            metrics.record("quota", 50, 200, True)
        for _ in range(10):
            metrics.record("wall.paint", 2500, 200, True)
        window = metrics.take()
        safety = run.Safety()
        for _ in range(4):
            self.assertIsNone(safety.http(window))
        self.assertIsNotNone(safety.http(window))

    def test_cpu_saturation_aborts_after_ten_samples(self):
        safety = run.Safety()
        first = probe()
        safety.resources(first)
        for i in range(1, 11):
            value = {**probe(), "cpu_total": 1000 + 100 * i, "cpu_idle": 500}
            result = safety.resources(value)
            self.assertEqual(i == 10, result is not None)

    def test_linux_probe_returns_host_docker_and_database_metrics_without_secrets(self):
        texts = {
            "/proc/meminfo": "MemTotal: 4096000 kB\nMemAvailable: 2048000 kB\nSwapTotal: 0 kB\nSwapFree: 0 kB\n",
            "/proc/stat": "cpu 100 0 100 800 10 0 0 0\ncpu0 50 0 50 400 5 0 0 0\ncpu1 50 0 50 400 5 0 0 0\n",
            "/proc/loadavg": "0.1 0.2 0.3 1/100 1\n",
        }
        services = {"a": "app", "n": "nginx", "s": "scheduler", "p": "postgres"}

        def command(args):
            if args[:3] == ["docker", "ps", "-aq"]:
                return "a n s p"
            if args[:2] == ["docker", "stats"]:
                return '{"CPUPerc":"1%","MemUsage":"10MiB / 4GiB"}\n'
            if args[:2] == ["docker", "inspect"]:
                service = services[args[-1]]
                return json.dumps({"name": service, "service": service, "running": True, "oom": False, "restarts": 0})
            if args[:3] == ["docker", "ps", "-q"]:
                return "p"
            if args[:2] == ["docker", "exec"]:
                self.assertIn("ON_ERROR_STOP=1", " ".join(args))
                self.assertIn("SELECT", args[-1])
                return '{"bytes":1000,"orders":0}'
            self.fail(f"Unexpected command {args}")

        with mock.patch.object(server_probe.pathlib, "Path", side_effect=lambda path: mock.Mock(
                read_text=lambda: texts[path])), mock.patch.object(
                server_probe.shutil, "disk_usage",
                return_value=mock.Mock(total=120 * 1024**3, free=100 * 1024**3)), mock.patch.object(
                server_probe, "command", side_effect=command):
            result = server_probe.probe("pixel-staging", "/opt/pixel-data/staging")
        self.assertEqual(2, result["cores"])
        self.assertEqual(4096000 * 1024, result["memory_total"])
        self.assertEqual(4, len(result["containers"]))
        self.assertEqual(1000, result["database"]["bytes"])
        self.assertNotIn("POSTGRES_PASSWORD", json.dumps(result))

    def test_report_streaming_and_incomplete_verdict_no_credentials(self):
        with tempfile.TemporaryDirectory(prefix="pixriot-soak-report-") as directory:
            root = pathlib.Path(directory)
            now = time.time()
            run.atomic_json(root / "state.json", {
                "status": "completed", "smoke": False, "run_id": "synthetic",
                "target": run.TARGET, "started_at": now - 100, "steady_at": now - 60,
                "wall": {"width": 3840, "height": 2160},
                "ends_at": now, "reason": None,
            })
            metrics = run.Metrics()
            metrics.record("wall.paint", 50, 200, True, 40)
            window = metrics.take()
            window.update(timestamp=now, seconds=60, active_users={"wall": 50})
            (root / "http-2026-11-07.jsonl").write_text(json.dumps(window) + "\n")
            sample = probe()
            run.Safety().resources(sample)
            (root / "server-2026-11-07.jsonl").write_text(json.dumps(sample) + "\n")
            result = report.build(root)
            # Short fabricated runs are not a 14-day capacity certificate.
            self.assertEqual("INCOMPLETE", result["verdict"])
            self.assertEqual(100, result["coverage_percent"])
            self.assertNotIn("password", json.dumps(result))
            self.assertIn("p95", report.markdown(result))
            window["active_users"] = {"wall": 49}
            (root / "http-2026-11-07.jsonl").write_text(json.dumps(window) + "\n")
            self.assertEqual("INCOMPLETE", report.build(root)["verdict"])

    def test_probe_rejects_compose_injection_before_commands(self):
        with mock.patch.object(server_probe, "command") as command:
            with self.assertRaises(ValueError):
                server_probe.probe("pixel-staging;bad", "/")
            command.assert_not_called()

    def test_all_fifty_workers_paint_and_project_chat_without_shop(self):
        for project_mode in (False, True):
            runner = run.Run.__new__(run.Run)
            runner.state = {"started_at": time.time() - 301}
            runner.args = argparse.Namespace(smoke=False)
            runner.manifest = manifest()
            runner.stop = threading.Event()
            runner.metrics = run.Metrics()
            runner.abort = mock.Mock(side_effect=lambda *_: runner.stop.set())
            completed = set()
            paths = []
            lock = threading.Lock()

            class FakeClient:
                def __init__(self, _target, user, *_):
                    self.user = user

                def login(self):
                    pass

                def identity(self):
                    pass

                def page(self, path, _):
                    with lock:
                        paths.append(path)

                def request(self, method, path, name, *_args, **_kwargs):
                    with lock:
                        paths.append(path)
                        runner.metrics.record(name, 10, 200, True)
                        if name.endswith(".paint"):
                            completed.add(self.user["id"])
                            if len(completed) == 50:
                                runner.stop.set()
                    return {"server_time": "2026-11-07T01:00:00Z", "messages": []}

            with mock.patch.object(run, "Client", FakeClient), mock.patch.object(
                    run.random, "random", return_value=.1 if project_mode else .9):
                threads = [threading.Thread(target=runner.player, args=(i, user))
                           for i, user in enumerate(runner.manifest["users"])]
                for thread in threads:
                    thread.start()
                for thread in threads:
                    thread.join(5)
                runner.stop.set()
                self.assertFalse(any(thread.is_alive() for thread in threads))
            self.assertEqual(50, len(completed))
            runner.abort.assert_not_called()
            self.assertFalse(any("shop" in path for path in paths))
            window = runner.metrics.take()
            self.assertEqual(50, window["endpoints"]["project.paint" if project_mode else "wall.paint"]["count"])
            if project_mode:
                self.assertEqual(50, window["endpoints"]["chat.send"]["count"])


if __name__ == "__main__":
    unittest.main()
