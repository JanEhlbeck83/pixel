"""Bounded, resumable PixRiot soak test. Python 3.11+, standard library only."""

import argparse
import base64
import bisect
import collections
import datetime as dt
import hashlib
import html.parser
import http.cookiejar
import json
import math
import os
import pathlib
import random
import re
import signal
import shutil
import subprocess
import tempfile
import threading
import time
import urllib.error
import urllib.parse
import urllib.request

TARGET = "https://staging.pixriot.online"
BUCKETS = [10 * i for i in range(1, 1001)] + [float("inf")]
GET_PATHS = {
    "/login", "/de", "/de/community", "/api/soak/status", "/api/pixels/status",
    "/api/pixels/recent", "/api/pixels/locks", "/api/wall", "/api/community/reservations",
    "/api/community/phase-updates", "/api/wall-drafts", "/api/pixels",
}
POST_PATHS = {"/login", "/api/pixels", "/api/pixels/lock", "/analytics/heartbeat"}
PROJECT_PATH = re.compile(r"/de/community/(?:projects/\d+(?:/(?:pixels|chat))?|groups/\d+)")


def atomic_json(path, value):
    descriptor, name = tempfile.mkstemp(prefix=path.name + "-", suffix=".tmp", dir=path.parent)
    temporary = pathlib.Path(name)
    try:
        with os.fdopen(descriptor, "w", encoding="utf-8") as file:
            json.dump(value, file, ensure_ascii=True)
            file.flush()
            os.fsync(file.fileno())
        for attempt in range(6):
            try:
                temporary.replace(path)
                break
            except PermissionError as error:
                if getattr(error, "winerror", None) != 32 or attempt == 5:
                    raise
                time.sleep(.05 * 2**attempt)
    finally:
        temporary.unlink(missing_ok=True)


def percentile(histogram, fraction):
    target = math.ceil(sum(histogram.values()) * fraction)
    if target == 0:
        return 0
    count = 0
    for bound, amount in sorted((int(k), v) for k, v in histogram.items()):
        count += amount
        if count >= target:
            return bound
    raise ValueError("Invalid histogram")


class Metrics:
    def __init__(self):
        self.lock = threading.Lock()
        self.rows = {}
        self.users = {}
        self.started = time.monotonic()

    def record(self, name, elapsed, status, ok, size=0):
        with self.lock:
            row = self.rows.setdefault(name, {
                "count": 0, "errors": 0, "bytes": 0, "max_ms": 0,
                "histogram": {}, "statuses": {},
            })
            row["count"] += 1
            row["errors"] += int(not ok)
            row["bytes"] += size
            row["max_ms"] = max(row["max_ms"], round(elapsed, 2))
            bucket = min(10010, (bisect.bisect_left(BUCKETS, elapsed) + 1) * 10)
            key = str(bucket)
            row["histogram"][key] = row["histogram"].get(key, 0) + 1
            key = str(status)
            row["statuses"][key] = row["statuses"].get(key, 0) + 1

    def touch(self, user_id, location):
        with self.lock:
            self.users[user_id] = (time.time(), location)

    def take(self):
        with self.lock:
            rows, self.rows = self.rows, {}
            active = collections.Counter(location for last, location in self.users.values()
                                         if time.time() - last < 30)
            duration = time.monotonic() - self.started
            self.started = time.monotonic()
        return {"timestamp": time.time(), "seconds": duration, "endpoints": rows,
                "active_users": dict(active)}


class PageParser(html.parser.HTMLParser):
    def __init__(self, text):
        super().__init__()
        self.csrf = None
        self.assets = set()
        self.feed(text)

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == "meta" and attrs.get("name") == "csrf-token":
            self.csrf = attrs.get("content")
        if tag == "input" and attrs.get("name") == "_token" and not self.csrf:
            self.csrf = attrs.get("value")
        asset = attrs.get("src") if tag == "script" else attrs.get("href") if tag == "link" else None
        if asset and "/build/" in asset:
            self.assets.add(asset)


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class RequestFailure(Exception):
    def __init__(self, status):
        self.status = status
        super().__init__(f"Unexpected HTTP status/response: {status}")


class Client:
    def __init__(self, target, user, run_id, metrics, stop):
        self.target, self.user, self.run_id = target, user, run_id
        self.metrics, self.stop = metrics, stop
        self.csrf = None
        self.assets = set()
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())

    def request(self, method, path, name, payload=None, json_response=True, expected=(200,)):
        url = urllib.parse.urljoin(self.target + "/", path)
        parsed = urllib.parse.urlsplit(url)
        if (parsed.scheme, parsed.netloc) != (
                urllib.parse.urlsplit(self.target).scheme, urllib.parse.urlsplit(self.target).netloc):
            raise ValueError("Cross-origin requests are forbidden")
        route = parsed.path
        allowed = route in (GET_PATHS if method == "GET" else POST_PATHS)
        allowed |= method == "GET" and (route.startswith("/build/")
                                       or bool(re.fullmatch(r"/images/wall-tiles/\d+-\d+\.png", route)))
        allowed |= bool(PROJECT_PATH.fullmatch(route)) and (
            method == "GET" or method == "POST" and route.endswith(("/pixels", "/chat")))
        if not allowed or method not in ("GET", "POST"):
            raise ValueError(f"Forbidden request: {method} {route}")
        if self.stop.is_set():
            raise RequestFailure("stopped")
        headers = {"Accept": "application/json" if json_response else "text/html,*/*",
                   "User-Agent": "PixRiot-Endurance/1.0", "X-App-Locale": "de"}
        basic = os.environ.get("SOAK_BASIC_AUTH")
        if basic:
            headers["Authorization"] = "Basic " + base64.b64encode(basic.encode()).decode()
        data = None
        if payload is not None:
            if not self.csrf:
                raise ValueError("Missing CSRF token")
            headers.update({"Content-Type": "application/json", "X-CSRF-TOKEN": self.csrf})
            data = json.dumps(payload).encode()
        request = urllib.request.Request(url, data=data, headers=headers, method=method)
        start = time.monotonic()
        status, content, ok, result = 0, b"", False, None
        try:
            try:
                response = self.opener.open(request, timeout=10)
            except urllib.error.HTTPError as error:
                response = error
            with response:
                status = response.code
                content = response.read(8 * 1024 * 1024 + 1)
            if len(content) > 8 * 1024 * 1024:
                raise RequestFailure("oversized")
            if status not in expected:
                raise RequestFailure(status)
            result = json.loads(content) if json_response else content
            if json_response and not isinstance(result, dict):
                raise RequestFailure("invalid-json")
            if name in ("wall.paint", "project.paint", "wall.lock") and result.get("success") is not True:
                raise RequestFailure("missing-success")
            if name == "chat.send" and not isinstance(result.get("message", {}).get("id"), int):
                raise RequestFailure("missing-message")
            ok = True
            return result
        finally:
            self.metrics.record(name, (time.monotonic() - start) * 1000, status, ok, len(content))

    def identity(self):
        identity = self.request("GET", "/api/soak/status", "identity")
        if (identity.get("run_id") != self.run_id or identity.get("target") != self.target
                or identity.get("user_id") != self.user["id"] or identity.get("users") != 50
                or identity.get("wall") != {"width": 3840, "height": 2160}):
            raise ValueError("Staging fixture identity mismatch")
        return identity

    def login(self):
        page = self.request("GET", "/login", "login.page", json_response=False)
        self.csrf = PageParser(page.decode()).csrf
        self.request("POST", "/login", "login.submit", {
            "email": self.user["email"], "password": self.user["password"],
        }, json_response=False, expected=(302,))
        identity = self.identity()
        dimensions = self.request("GET", "/api/wall", "wall.dimensions")
        if any(dimensions.get(key) != identity.get("wall", {}).get(key) for key in ("width", "height")):
            raise ValueError("Wall dimensions differ from prepared fixtures")
        self.page("/de", "wall.page")
        quota = self.request("GET", "/api/pixels/status", "quota")
        if quota.get("authenticated") is not True:
            raise ValueError("User session is not authenticated")

    def page(self, path, name):
        page = self.request("GET", path, name, json_response=False)
        parsed = PageParser(page.decode())
        if not parsed.csrf:
            raise ValueError("Authenticated page has no CSRF token")
        self.csrf = parsed.csrf
        for asset in sorted(parsed.assets - self.assets):
            self.request("GET", asset, "static.asset", json_response=False)
            self.assets.add(asset)


class Safety:
    def __init__(self):
        self.bad_http = 0
        self.bad_resources = 0
        self.previous_probe = None

    def http(self, window):
        histogram, count, errors = {}, 0, 0
        for row in window["endpoints"].values():
            count += row["count"]
            errors += row["errors"]
            for bucket, amount in row["histogram"].items():
                histogram[bucket] = histogram.get(bucket, 0) + amount
        bad = count >= 20 and (errors / count > .01 or percentile(histogram, .95) > 2000
                              or percentile(histogram, .99) > 5000)
        bad |= any(row["count"] >= 10 and (row["errors"] / row["count"] > .01
                   or percentile(row["histogram"], .95) > 2000
                   or percentile(row["histogram"], .99) > 5000)
                   for row in window["endpoints"].values())
        self.bad_http = self.bad_http + 1 if bad else 0
        return "HTTP errors >1% or p95 >2s / p99 >5s for five windows" if self.bad_http >= 5 else None

    def resources(self, probe):
        previous, self.previous_probe = self.previous_probe, probe
        cpu = 0
        if previous:
            total = probe["cpu_total"] - previous["cpu_total"]
            idle = probe["cpu_idle"] - previous["cpu_idle"]
            cpu = 100 * (1 - idle / total) if total > 0 else 0
        probe["cpu_percent"] = round(cpu, 2)
        used = 1 - probe["memory_available"] / probe["memory_total"]
        probe["memory_used_percent"] = round(used * 100, 2)
        if probe["disk_free"] < max(2 * 1024**3, probe["disk_total"] * .10):
            return "Server data disk has <10% or <2 GiB free"
        if any(c["oom"] or not c["running"] for c in probe["containers"]):
            return "Staging container stopped or OOM-killed"
        if previous:
            before = {c["name"]: c["restarts"] for c in previous["containers"]}
            if any(c["restarts"] > before.get(c["name"], 0) for c in probe["containers"]):
                return "Staging container restarted"
        if probe["database"]["orders"] != 0:
            return "Orders detected in isolated database; stop immediately"
        self.bad_resources = self.bad_resources + 1 if cpu > 95 or used > .95 else 0
        return "Server CPU or memory >95% for five minutes" if self.bad_resources >= 10 else None


def validate_manifest(manifest):
    if manifest.get("schema") != 1 or manifest.get("target") != TARGET:
        raise ValueError("Only https://staging.pixriot.online is authorized")
    users = manifest.get("users", [])
    if len(users) != 50 or len({u["id"] for u in users}) != 50:
        raise ValueError("Manifest must contain 50 distinct users")
    if manifest.get("wall") != {"width": 3840, "height": 2160}:
        raise ValueError("Expected the current 3840 x 2160 wall size")
    for user in users:
        if (not user["email"].endswith("@example.invalid") or not user["email"].startswith("soak-")
                or not user.get("password") or not 0 <= user["project_row"] < 25
                or user["wall_x"] != 64 or not 180 <= user["wall_y"] < 230):
            raise ValueError("Invalid synthetic user fixture")
    expires = dt.datetime.fromisoformat(manifest["expires_at"]).timestamp()
    if expires < time.time() + 60:
        raise ValueError("Fixtures expire too early; prepare shortly before the run")


def acquire_lock(path):
    file = path.open("a+b")
    try:
        if os.name == "nt":
            import msvcrt
            file.seek(0)
            if not file.read(1):
                file.write(b"\0")
                file.flush()
            file.seek(0)
            msvcrt.locking(file.fileno(), msvcrt.LK_NBLCK, 1)
        else:
            import fcntl
            fcntl.flock(file, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except OSError:
        file.close()
        raise ValueError("Another runner holds this output directory") from None
    return file


class Run:
    def __init__(self, args):
        self.args = args
        self.manifest = json.loads(args.manifest.read_text(encoding="utf-8"))
        validate_manifest(self.manifest)
        self.output = args.output.resolve()
        self.output.mkdir(mode=0o700, parents=True, exist_ok=True)
        self.file_lock = acquire_lock(self.output / "runner.lock")
        self.state_path = self.output / "state.json"
        self.metrics, self.safety, self.stop = Metrics(), Safety(), threading.Event()
        self.state_lock = threading.Lock()
        try:
            self.initialize()
        except BaseException:
            self.file_lock.close()
            raise

    def initialize(self):
        args = self.args
        fingerprint = hashlib.sha256(args.manifest.read_bytes()).hexdigest()
        if self.state_path.exists():
            if not args.resume:
                raise ValueError("Output already contains a run; use --resume")
            self.state = json.loads(self.state_path.read_text())
            if self.state["fingerprint"] != fingerprint or self.state["target"] != TARGET:
                raise ValueError("Cannot resume a different fixture")
            if self.state["status"] in ("aborted", "completed"):
                raise ValueError("Terminal runs cannot be restarted; inspect the report first")
        else:
            now = time.time()
            ramp = 0 if args.smoke else 300
            self.state = {
                "schema": 1, "run_id": self.manifest["run_id"], "fingerprint": fingerprint,
                "target": TARGET, "started_at": now, "steady_at": now + ramp,
                "ends_at": now + ramp + (60 if args.smoke else 14 * 86400),
                "status": "running", "reason": None, "smoke": args.smoke,
                "wall": self.manifest["wall"],
            }
        if self.state["smoke"] != args.smoke:
            raise ValueError("Cannot change smoke mode when resuming")
        if dt.datetime.fromisoformat(self.manifest["expires_at"]).timestamp() < self.state["ends_at"] + 60:
            raise ValueError("Fixtures do not remain valid until the planned end")
        if time.time() >= self.state["ends_at"]:
            self.state.update(status="completed", stopped_at=time.time(),
                              reason="Deadline elapsed while runner was stopped")
        else:
            self.state["status"] = "running"
        atomic_json(self.state_path, self.state)

    def write(self, kind, value):
        day = dt.datetime.fromtimestamp(time.time(), dt.timezone.utc).strftime("%Y-%m-%d")
        with (self.output / f"{kind}-{day}.jsonl").open("a", encoding="utf-8") as file:
            file.write(json.dumps(value, ensure_ascii=True) + "\n")

    def abort(self, reason):
        with self.state_lock:
            if self.state["status"] == "aborted":
                return
            self.state.update(status="aborted", reason=reason, stopped_at=time.time())
            atomic_json(self.state_path, self.state)
            self.write("events", {"timestamp": time.time(), "event": "aborted", "reason": reason})
        self.stop.set()

    def probe(self):
        args = self.args
        if not re.fullmatch(r"[A-Za-z0-9][A-Za-z0-9_.-]*", args.ssh_host):
            raise ValueError("Use a local SSH alias, not a shell command")
        if not re.fullmatch(r"[a-z0-9][a-z0-9_-]{0,62}", args.compose_project):
            raise ValueError("Invalid Compose project")
        if not re.fullmatch(r"/[A-Za-z0-9_./-]+", args.data_path):
            raise ValueError("Invalid server data directory")
        source = pathlib.Path(__file__).with_name("server_probe.py").read_text(encoding="utf-8")
        result = subprocess.run([
            "ssh", "-T", "-o", "BatchMode=yes", "-o", "StrictHostKeyChecking=yes",
            "-o", "ConnectTimeout=10", "-o", "ServerAliveInterval=10",
            args.ssh_host, "python3", "-", "--project", args.compose_project,
            "--data-path", args.data_path,
        ], input=source, text=True, capture_output=True, timeout=55, check=True)
        value = json.loads(result.stdout)
        if abs(value["timestamp"] - time.time()) > 60:
            raise ValueError("Server clock differs by more than 60 seconds")
        return value

    def monitor(self):
        failures = 0
        while not self.stop.is_set():
            started = time.monotonic()
            try:
                value = self.probe()
                reason = self.safety.resources(value)
                self.write("server", value)
                failures = 0
                if reason:
                    self.abort(reason)
            except (OSError, ValueError, KeyError, subprocess.SubprocessError) as error:
                failures += 1
                self.write("events", {"timestamp": time.time(), "event": "monitor-error",
                                     "type": type(error).__name__, "consecutive": failures})
                if failures >= 3:
                    self.abort("Server telemetry failed three times")
            self.stop.wait(max(0, 30 - (time.monotonic() - started)))

    def player(self, index, user):
        if self.stop.wait(max(0, self.state["started_at"] + (0 if self.args.smoke else index * 6) - time.time())):
            return
        client = Client(TARGET, user, self.manifest["run_id"], self.metrics, self.stop)
        mode, newest, point, failures, viewport_y = "wall", 0, 0, 0, 0
        due = collections.defaultdict(float)
        project = f'/de/community/projects/{user["project_id"]}'
        try:
            client.login()
            while not self.stop.is_set():
                now = time.monotonic()
                self.metrics.touch(user["id"], mode)
                try:
                    if now >= due["identity"]:
                        client.identity()
                        due["identity"] = now + 300
                    if now >= due["switch"]:
                        mode = "project" if random.random() < .4 else "wall"
                        if mode == "project":
                            client.page(project, "project.page")
                            client.request("GET", project + "/pixels", "project.pixels")
                        else:
                            client.page("/de", "wall.page")
                            client.request("GET", "/api/wall-drafts", "draft.list")
                            due["tiles"] = 0
                        due["switch"] = now + random.uniform(300, 900)
                        newest = 0
                    if now >= due["quota"]:
                        client.request("GET", "/api/pixels/status", "quota")
                        due["quota"] = now + (5 if mode == "project" else 10)
                    if now >= due["heartbeat"]:
                        client.request("POST", "/analytics/heartbeat", "heartbeat", {"seconds": 15},
                                       json_response=False, expected=(204,))
                        due["heartbeat"] = now + 15
                    if now >= due["phase"]:
                        client.request("GET", "/api/community/phase-updates", "phase")
                        due["phase"] = now + 30
                    if mode == "wall":
                        area = f"x=0&y={viewport_y}&width=900&height=600"
                        if now >= due["recent"]:
                            path = "/api/pixels/recent?" + area
                            if due.get("since"):
                                path += "&since=" + urllib.parse.quote(due["since"])
                            recent = client.request("GET", path, "wall.recent")
                            due["since"] = recent["server_time"]
                            due["recent"] = now + 5
                        for key, path, interval in [
                            ("dimensions", "/api/wall", 15), ("locks", "/api/pixels/locks", 15),
                            ("reservations", "/api/community/reservations?" + area, 30),
                        ]:
                            if now >= due[key]:
                                client.request("GET", path, "wall." + key)
                                due[key] = now + interval
                        if now >= due["tiles"]:
                            viewport_y = random.choice([0, 100])
                            area = f"x=0&y={viewport_y}&width=900&height=600"
                            for x in (0, 300, 600):
                                for y in range(0, viewport_y + 600, 300):
                                    client.request("GET", f"/images/wall-tiles/{x}-{y}.png",
                                                   "static.tile", json_response=False)
                            client.request("GET", "/api/pixels/recent?" + area, "wall.pan")
                            due["tiles"] = now + random.uniform(60, 120)
                    elif now >= due["chat.read"]:
                        chat = client.request("GET", project + f"/chat?after_id={newest}", "chat.read")
                        for message in chat["messages"]:
                            newest = max(newest, message["id"])
                        due["chat.read"] = now + 5
                    if mode == "project" and now >= due["chat.send"]:
                        due["chat.send"] = now + random.uniform(240, 600)
                        client.request("POST", project + "/chat", "chat.send", {
                            "message": random.choice([
                                "Test: Ich male die naechste Reihe.",
                                "Test: Farbe gewaehlt, sieht gut aus.",
                                "Test: Kurze Pause, danach geht es weiter.",
                                "Test: Ich sehe eure neuen Pixel.",
                            ]) + f" [synthetic {index + 1}]",
                        }, expected=(201,))
                    if now >= due["paint"]:
                        due["paint"] = now + random.uniform(35, 65)
                        point += 1
                        pixel = {"x": point % 32, "y": user["project_row"],
                                 "color": random.choice(["#ff415f", "#1dd1a1", "#222f3e", "#54a0ff"])}
                        if mode == "project":
                            client.request("POST", project + "/pixels", "project.paint", pixel)
                        else:
                            pixel.update(x=user["wall_x"] + point % 128, y=user["wall_y"])
                            client.request("POST", "/api/pixels/lock", "wall.lock",
                                           {**pixel, "width": 1, "height": 1})
                            client.request("POST", "/api/pixels", "wall.paint", pixel)
                    failures = 0
                except RequestFailure as error:
                    if self.stop.is_set():
                        break
                    failures += 1
                    if error.status in (401, 419, 302):
                        client.login()
                    if failures >= 3:
                        raise
                    self.stop.wait(5)
                except (urllib.error.URLError, TimeoutError, ConnectionError):
                    failures += 1
                    if failures >= 3:
                        raise
                    self.stop.wait(5)
                self.stop.wait(.5)
        except Exception as error:
            # A dead player must abort the run, never silently reduce concurrency.
            self.abort(f"Player {index + 1} failed ({type(error).__name__})")

    def execute(self):
        if self.state["status"] == "completed":
            return 0
        # Fail closed before any HTTP traffic if SSH or critical server checks fail.
        try:
            first = self.probe()
        except (OSError, ValueError, KeyError, subprocess.SubprocessError):
            self.abort("Initial server telemetry failed; no HTTP traffic started")
            raise
        reason = self.safety.resources(first)
        self.write("server", first)
        if reason:
            self.abort(reason)
            return 2
        self.write("events", {"timestamp": time.time(), "event": "started-or-resumed",
                              "users": 3 if self.args.smoke else 50})
        threads = [threading.Thread(target=self.monitor, name="monitor")]
        users = self.manifest["users"][:3] if self.args.smoke else self.manifest["users"]
        threads += [threading.Thread(target=self.player, args=(i, user), name=f"player-{i + 1}")
                    for i, user in enumerate(users)]
        for thread in threads:
            thread.start()
        next_window = time.monotonic() + 60
        try:
            while not self.stop.wait(1):
                if time.time() >= self.state["ends_at"]:
                    with self.state_lock:
                        if self.state["status"] == "running":
                            self.state.update(status="completed", stopped_at=time.time())
                    self.stop.set()
                if (self.output / "STOP").exists():
                    self.abort("Operator STOP file")
                if time.monotonic() >= next_window:
                    window = self.metrics.take()
                    self.write("http", window)
                    reason = self.safety.http(window)
                    expected = len(users)
                    if time.time() >= self.state["steady_at"] + 60 and sum(window["active_users"].values()) < expected:
                        reason = f"Fewer than {expected} responsive users"
                    if shutil.disk_usage(self.output).free < 1024**3:
                        reason = "Generator output disk has <1 GiB free"
                    if reason:
                        self.abort(reason)
                    with self.state_lock:
                        atomic_json(self.state_path, self.state)
                    next_window = time.monotonic() + 60
        finally:
            self.stop.set()
            for thread in threads:
                thread.join(timeout=70)
            if any(thread.is_alive() for thread in threads):
                self.abort("Worker failed to stop within timeout")
            self.write("http", self.metrics.take())
            with self.state_lock:
                if self.state["status"] == "running":
                    self.state.update(status="interrupted", stopped_at=time.time())
                atomic_json(self.state_path, self.state)
        return 2 if self.state["status"] == "aborted" else 0


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--manifest", type=pathlib.Path, required=True)
    parser.add_argument("--output", type=pathlib.Path, required=True)
    parser.add_argument("--ssh-host", required=True, help="Preconfigured SSH alias on the generator")
    parser.add_argument("--compose-project", default="pixel-staging")
    parser.add_argument("--data-path", default="/opt/pixel-data/staging")
    parser.add_argument("--confirm-isolated", action="store_true", required=True)
    parser.add_argument("--resume", action="store_true")
    parser.add_argument("--smoke", action="store_true", help="Only 3 users for 60 seconds; still writes synthetic data")
    args = parser.parse_args()
    os.umask(0o077)
    try:
        run = Run(args)
        for sig in (signal.SIGINT, signal.SIGTERM):
            signal.signal(sig, lambda *_: run.stop.set())
        with run.file_lock:
            return run.execute()
    except (ValueError, OSError, KeyError, subprocess.SubprocessError) as error:
        print(f"Runner could not start/continue ({type(error).__name__}): {error}", flush=True)
        return 2


if __name__ == "__main__":
    raise SystemExit(main())
