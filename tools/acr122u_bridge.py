"""ACR122U bridge for ClearTrack.

Reads each tapped card's UID (its factory serial number) over PC/SC and
streams it to the ClearTrack page open in the browser via Server-Sent Events
at http://127.0.0.1:8765/scans. Pages with a [data-nfc] input pick it up.

Setup:  pip install pyscard
Run:    python tools/acr122u_bridge.py https://your-cleartrack-site
        (the ClearTrack address exactly as the browser shows it; only that
        site may read scans. Leave it running, Ctrl+C to stop.)
"""
import queue
import sys
import threading
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

from smartcard.CardMonitoring import CardMonitor, CardObserver
from smartcard.util import toHexString

PORT = 8765
GET_UID = [0xFF, 0xCA, 0x00, 0x00, 0x00]  # PC/SC pseudo-APDU, supported by ACR122U

clients = set()
clients_lock = threading.Lock()


def broadcast(uid):
    with clients_lock:
        for q in clients:
            q.put(uid)


def read_uid(card, attempts=5):
    # Windows' smart card service briefly holds a newly tapped card ("reader not currently
    # available", 0x80100017), so retry for ~0.6 s before giving up.
    for attempt in range(attempts):
        try:
            conn = card.createConnection()
            conn.connect()
            try:
                return conn.transmit(GET_UID)
            finally:
                conn.disconnect()
        except Exception as e:
            if "removed" in str(e) or attempt == attempts - 1:
                raise
            time.sleep(0.15)


class UidReader(CardObserver):
    def update(self, observable, actions):
        added, _removed = actions
        for card in added:
            try:
                data, sw1, sw2 = read_uid(card)
            except Exception as e:
                reason = "card lifted too soon" if "removed" in str(e) else e
                print(f"Read failed ({reason}). Tap again and hold the card still for a second.")
                continue
            if (sw1, sw2) != (0x90, 0x00):
                print(f"Card rejected GET UID (status {sw1:02X}{sw2:02X})")
                continue
            uid = toHexString(data).replace(" ", "")  # e.g. 04A1B2C3D4E5F6
            print(f"Card: {uid}")
            broadcast(uid)


class ScanStream(BaseHTTPRequestHandler):
    def do_OPTIONS(self):
        # Chrome's preflight before a public site may reach 127.0.0.1 (Private Network Access)
        if self.headers.get("Origin") != ALLOWED_ORIGIN:
            self.send_error(403)
            return
        self.send_response(204)
        self.send_header("Access-Control-Allow-Origin", ALLOWED_ORIGIN)
        self.send_header("Access-Control-Allow-Private-Network", "true")
        self.end_headers()

    def do_GET(self):
        if self.path != "/scans":
            self.send_error(404)
            return
        if self.headers.get("Origin") != ALLOWED_ORIGIN:
            self.send_error(403)  # other websites must not read card UIDs
            return
        self.send_response(200)
        self.send_header("Content-Type", "text/event-stream")
        self.send_header("Cache-Control", "no-cache")
        self.send_header("Access-Control-Allow-Origin", ALLOWED_ORIGIN)
        self.end_headers()

        q = queue.Queue()
        with clients_lock:
            clients.add(q)
        try:
            while True:
                try:
                    self.wfile.write(f"data: {q.get(timeout=15)}\n\n".encode())
                except queue.Empty:
                    self.wfile.write(b": ping\n\n")  # keep-alive, also detects closed tabs
                self.wfile.flush()
        except (BrokenPipeError, ConnectionResetError, ConnectionAbortedError):
            pass
        finally:
            with clients_lock:
                clients.discard(q)

    def log_message(self, *args):
        pass


if __name__ == "__main__":
    if len(sys.argv) != 2:
        sys.exit("Usage: python acr122u_bridge.py https://your-cleartrack-site")
    ALLOWED_ORIGIN = sys.argv[1].rstrip("/")
    CardMonitor().addObserver(UidReader())
    server = ThreadingHTTPServer(("127.0.0.1", PORT), ScanStream)
    server.daemon_threads = True
    print(f"ACR122U bridge on http://127.0.0.1:{PORT}/scans - tap a card (Ctrl+C to stop)")
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        pass
