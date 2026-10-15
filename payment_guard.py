#!/usr/bin/env python3
"""Payment spam guard for panelbaku.com admin API v2.

Her POLL_INTERVAL saniyede ödeme listesini çeker. Bir kullanıcı aynı ödeme
yöntemiyle WINDOW_SECONDS içinde THRESHOLD'dan fazla deneme yaptıysa:
  1) Telegram'a bildirim gönderir (kullanıcı adı + ödeme yöntemi + sayı),
  2) o ödeme yöntemini kullanıcının "disallowed methods" listesine ekler.

Sadece standart kütüphane kullanır. Gizli bilgiler ortam değişkenlerinden okunur.

Zorunlu ortam değişkenleri:
  PANEL_API_KEY, TG_BOT_TOKEN, TG_CHAT_ID
Opsiyonel (varsayılanlar panel API'sine göre değiştirilebilir):
  PANEL_API_URL        https://panelbaku.com/admin/adminapi/v2/
  API_KEY_HEADER       X-Api-Key
  PAYMENTS_PATH        payments            (liste endpoint'i)
  PAYMENTS_PARAMS      limit=500           (query string)
  USER_PATH            users/{id}          (tek kullanıcı GET)
  USER_UPDATE_PATH     users/{id}          (güncelleme endpoint'i)
  USER_UPDATE_METHOD   PUT
  DISALLOW_FIELD       disallowed_methods  (kullanıcıdaki alan adı)
  F_PAYMENT_USER_ID    user_id | F_PAYMENT_USERNAME  username
  F_PAYMENT_METHOD     method  | F_PAYMENT_TIME      created_at
  POLL_INTERVAL=120  WINDOW_SECONDS=180  THRESHOLD=5
  STATE_FILE           payment_guard_state.json
  TG_PROXY             (gerekirse) http://host:port

Kullanım:
  python3 payment_guard.py --discover   # ham API cevabının yapısını göster
  python3 payment_guard.py --once --dry-run
  python3 payment_guard.py              # sürekli çalış
"""
import argparse
import json
import logging
import os
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from collections import defaultdict
from datetime import datetime, timezone

E = os.environ.get
API_URL = E("PANEL_API_URL", "https://panelbaku.com/admin/adminapi/v2/").rstrip("/") + "/"
API_KEY = E("PANEL_API_KEY", "")
KEY_HEADER = E("API_KEY_HEADER", "X-Api-Key")
PAYMENTS_PATH = E("PAYMENTS_PATH", "payments")
PAYMENTS_PARAMS = E("PAYMENTS_PARAMS", "limit=500")
USER_PATH = E("USER_PATH", "users/{id}")
USER_UPDATE_PATH = E("USER_UPDATE_PATH", "users/{id}")
USER_UPDATE_METHOD = E("USER_UPDATE_METHOD", "PUT").upper()
DISALLOW_FIELD = E("DISALLOW_FIELD", "disallowed_methods")
F_UID = E("F_PAYMENT_USER_ID", "user_id")
F_UNAME = E("F_PAYMENT_USERNAME", "username")
F_METHOD = E("F_PAYMENT_METHOD", "method")
F_TIME = E("F_PAYMENT_TIME", "created_at")
INTERVAL = int(E("POLL_INTERVAL", "120"))
WINDOW = int(E("WINDOW_SECONDS", "180"))
THRESHOLD = int(E("THRESHOLD", "5"))
STATE_FILE = E("STATE_FILE", "payment_guard_state.json")
TG_TOKEN = E("TG_BOT_TOKEN", "")
TG_CHAT = E("TG_CHAT_ID", "")
TG_PROXY = E("TG_PROXY", "")

log = logging.getLogger("payment_guard")


def http(url, method="GET", body=None, headers=None, proxy=None, retries=3):
    data = json.dumps(body).encode() if body is not None else None
    hdrs = {"Accept": "application/json", "User-Agent": "payment-guard/1.0"}
    if body is not None:
        hdrs["Content-Type"] = "application/json"
    hdrs.update(headers or {})
    handlers = [urllib.request.ProxyHandler({"https": proxy, "http": proxy})] if proxy else []
    opener = urllib.request.build_opener(*handlers)
    for attempt in range(1, retries + 1):
        try:
            req = urllib.request.Request(url, data=data, headers=hdrs, method=method)
            with opener.open(req, timeout=30) as r:
                raw = r.read().decode("utf-8", "replace")
                return json.loads(raw) if raw.strip() else {}
        except urllib.error.HTTPError as e:
            if e.code < 500 and e.code != 429:  # kalıcı hata: tekrar deneme
                raise RuntimeError(f"HTTP {e.code} {url}: {e.read()[:300]!r}") from None
            err = f"HTTP {e.code}"
        except (urllib.error.URLError, TimeoutError, json.JSONDecodeError) as e:
            err = str(e)
        if attempt < retries:
            time.sleep(2 ** attempt)
    raise RuntimeError(f"{method} {url} başarısız: {err}")


def api(path, method="GET", body=None, params=""):
    url = API_URL + path.lstrip("/") + (("?" + params) if params else "")
    return http(url, method, body, {KEY_HEADER: API_KEY})


def telegram(text):
    if not (TG_TOKEN and TG_CHAT):
        log.warning("Telegram ayarlı değil, mesaj atlanıyor: %s", text)
        return
    try:
        http(f"https://api.telegram.org/bot{TG_TOKEN}/sendMessage", "POST",
             {"chat_id": TG_CHAT, "text": text, "parse_mode": "HTML",
              "disable_web_page_preview": True}, proxy=TG_PROXY or None)
    except Exception as e:  # bildirim hatası ana döngüyü durdurmasın
        log.error("Telegram gönderilemedi: %s", e)


def extract_list(resp):
    """Cevabın içinden kayıt listesini bul (data/items/payments/... ya da düz liste)."""
    if isinstance(resp, list):
        return resp
    if isinstance(resp, dict):
        for k in ("data", "items", "payments", "results", "rows", "list"):
            v = resp.get(k)
            if isinstance(v, list):
                return v
            if isinstance(v, dict):
                inner = extract_list(v)
                if inner:
                    return inner
        for v in resp.values():
            if isinstance(v, list) and v and isinstance(v[0], dict):
                return v
    return []


def parse_time(v):
    if v in (None, ""):
        return None
    if isinstance(v, (int, float)) or str(v).isdigit():
        t = float(v)
        return t / 1000 if t > 1e11 else t
    s = str(v).strip().replace("Z", "+00:00")
    try:
        d = datetime.fromisoformat(s)
    except ValueError:
        for fmt in ("%Y-%m-%d %H:%M:%S", "%d.%m.%Y %H:%M:%S", "%Y/%m/%d %H:%M:%S"):
            try:
                d = datetime.strptime(s, fmt)
                break
            except ValueError:
                d = None
        if d is None:
            return None
    if d.tzinfo is None:  # panel saati bilinmiyorsa yerel saat varsay (TZ ile ayarlanır)
        return d.timestamp()
    return d.astimezone(timezone.utc).timestamp()


def load_state():
    try:
        with open(STATE_FILE) as f:
            return json.load(f)
    except (OSError, ValueError):
        return {"blocked": {}}


def save_state(st):
    tmp = STATE_FILE + ".tmp"
    with open(tmp, "w") as f:
        json.dump(st, f, indent=1, ensure_ascii=False)
    os.replace(tmp, STATE_FILE)


def as_list(v):
    if v is None or v == "":
        return []
    if isinstance(v, list):
        return [str(x) for x in v]
    return [x.strip() for x in str(v).split(",") if x.strip()]


def disallow_method(uid, method, dry_run):
    """Kullanıcının disallowed methods listesine yöntemi ekle. True = yeni eklendi."""
    user = api(USER_PATH.format(id=uid))
    if isinstance(user, dict) and isinstance(user.get("data"), dict):
        user = user["data"]
    current = as_list(user.get(DISALLOW_FIELD))
    if str(method) in current:
        return False
    new = current + [str(method)]
    if dry_run:
        log.info("[dry-run] user %s %s -> %s", uid, DISALLOW_FIELD, new)
        return True
    # liste mi virgüllü string mi: mevcut formatı koru
    value = ",".join(new) if isinstance(user.get(DISALLOW_FIELD), str) else new
    api(USER_UPDATE_PATH.format(id=uid), USER_UPDATE_METHOD, {DISALLOW_FIELD: value})
    return True


def run_cycle(dry_run=False):
    payments = extract_list(api(PAYMENTS_PATH, params=PAYMENTS_PARAMS))
    now = time.time()
    groups = defaultdict(list)
    names = {}
    for p in payments:
        ts = parse_time(p.get(F_TIME))
        uid, method = p.get(F_UID), p.get(F_METHOD)
        if ts is None or uid in (None, "") or method in (None, ""):
            continue
        if now - ts <= WINDOW:
            groups[(str(uid), str(method))].append(ts)
            names[str(uid)] = p.get(F_UNAME) or names.get(str(uid)) or str(uid)

    state = load_state()
    for (uid, method), times in groups.items():
        count = len(times)
        key = f"{uid}|{method}"
        if count <= THRESHOLD or key in state["blocked"]:
            continue
        log.warning("Spam: user=%s method=%s count=%d", uid, method, count)
        try:
            changed = disallow_method(uid, method, dry_run)
            action = "✅ Ödəniş üsulu deaktiv edildi" if changed else "ℹ️ Artıq qadağan idi"
            ok = True
        except Exception as e:
            action, ok = f"❌ Deaktiv edilə bilmədi: {e}", False
        telegram(
            "🚨 <b>Ödəniş spam</b>\n"
            f"👤 İstifadəçi: <b>{names[uid]}</b> (ID: {uid})\n"
            f"💳 Üsul: <b>{method}</b>\n"
            f"🔁 {count} cəhd / {WINDOW // 60} dəq\n{action}"
            + (" [dry-run]" if dry_run else ""))
        if ok and not dry_run:
            state["blocked"][key] = {"user": names[uid], "count": count, "at": int(now)}
    if not dry_run:
        save_state(state)
    log.info("Döngü bitdi: %d ödəniş, %d aktiv qrup", len(payments), len(groups))


def discover():
    resp = api(PAYMENTS_PATH, params=PAYMENTS_PARAMS)
    items = extract_list(resp)
    print("Üst səviyyə açarlar:", list(resp) if isinstance(resp, dict) else "list")
    print("Qeyd sayı:", len(items))
    if items:
        print("İlk qeyd:\n" + json.dumps(items[0], indent=2, ensure_ascii=False))
        uid = items[0].get(F_UID)
        if uid not in (None, ""):
            u = api(USER_PATH.format(id=uid))
            print("\nİstifadəçi nümunəsi:\n" + json.dumps(u, indent=2, ensure_ascii=False)[:2000])


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--once", action="store_true", help="bir dəfə işlə və çıx")
    ap.add_argument("--dry-run", action="store_true", help="dəyişiklik etmə")
    ap.add_argument("--discover", action="store_true", help="API cavabının strukturunu göstər")
    a = ap.parse_args()
    logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
    if not API_KEY:
        sys.exit("PANEL_API_KEY ortam dəyişəni təyin edilməyib")
    if a.discover:
        return discover()
    if not a.dry_run and not (TG_TOKEN and TG_CHAT):
        log.warning("TG_BOT_TOKEN / TG_CHAT_ID təyin edilməyib: bildiriş getməyəcək")
    while True:
        try:
            run_cycle(a.dry_run)
        except Exception as e:
            log.error("Döngü xətası: %s", e)
        if a.once:
            break
        time.sleep(INTERVAL)


if __name__ == "__main__":
    main()
