# webhook.py
# Telegram webhook handler (Flask)
from flask import Flask, request
import requests
import json
import time
import os

TOKEN = os.getenv("BOT_TOKEN", "8950660109:AAFB8lKqUYFuGJMIlNOQkjVMqoJe1_GEXm4")
CHANNEL = os.getenv("CHANNEL", "@kehanetsorgu")
CHANNEL_LINK = "https://t.me/kehanetsorgu"

SRC1 = "https://api.ajaxsystems.fun"
SRC2 = "https://mayte.pro/api/query"

SMS_BASE = "https://ucretsizservicetr.onrender.com/smsotp.php"
BINANCE_BASE = "https://ucretsizservicetr.onrender.com/binancesrg.php"
GPT_BASE = "https://ucretsizservicetr.onrender.com/gptpro.php"
API_KEY = "cmrbaskani_2026_secret_key_xyz"
DEVICE_ID = os.getenv("DEVICE_ID", "dev_qb8t0pznir_1794392412963")

HEADERS = {
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
    "Accept": "application/json, text/javascript, */*; q=0.01",
}

app = Flask(__name__)

# State yonetimi (RAM, tek process icin)
STATE = {}
STATE_TTL = 300


def api(method, params=None):
    url = f"https://api.telegram.org/bot{TOKEN}/{method}"
    try:
        r = requests.post(url, data=params or {}, timeout=20)
        return r.json()
    except Exception:
        return {"ok": False}


def http_get(url, timeout=25):
    try:
        r = requests.get(url, headers=HEADERS, timeout=timeout)
        if r.status_code == 200:
            return r.json()
    except Exception:
        pass
    return None


def http_post_json(url, body, timeout=25):
    try:
        r = requests.post(url, json=body, headers=HEADERS, timeout=timeout)
        if r.status_code == 200:
            return r.json()
    except Exception:
        pass
    return None


def uye_mi(uid):
    r = api("getChatMember", {"chat_id": CHANNEL, "user_id": uid})
    if not r.get("ok"):
        return False
    return r["result"]["status"] in ["member", "administrator", "creator"]


def dosya_gonder(chat_id, sonuc, isim):
    if not sonuc:
        api("sendMessage", {
            "chat_id": chat_id,
            "text": "Baktım ama bir şey bulamadım 🤷‍♂️\nBilgileri kontrol edip tekrar dener misin?",
        })
        return
    yol = f"/tmp/{isim}_{int(time.time())}.json"
    with open(yol, "w", encoding="utf-8") as f:
        json.dump(sonuc, f, indent=2, ensure_ascii=False)
    with open(yol, "rb") as f:
        requests.post(
            f"https://api.telegram.org/bot{TOKEN}/sendDocument",
            data={"chat_id": chat_id, "caption": "İşte bulduklarım 📄"},
            files={"document": f},
            timeout=60,
        )
    os.remove(yol)


def ana_menu():
    return {
        "inline_keyboard": [
            [
                {"text": "👤 Kişi Sorgulama", "callback_data": "menu_kisi"},
                {"text": "🔍 Kişi Detay Sorgulama", "callback_data": "menu_kisidetay"},
            ],
            [
                {"text": "👨‍👩‍👧 Aile Sorgulama", "callback_data": "menu_aile"},
                {"text": "🌳 Sülale Sorgulama", "callback_data": "menu_sulale"},
            ],
            [
                {"text": "📞 TC'den GSM Bulma", "callback_data": "menu_tcgsm"},
                {"text": "📱 GSM'den TC Bulma", "callback_data": "menu_gsmtc"},
            ],
            [
                {"text": "🏠 Adres Sorgulama", "callback_data": "menu_adres"},
                {"text": "📜 Tapu Sorgulama", "callback_data": "menu_tapu"},
            ],
            [
                {"text": "🗺️ Ada Parsel Sorgulama", "callback_data": "menu_adaparsel"},
                {"text": "🎒 Okul Bilgisi Sorgulama", "callback_data": "menu_okul"},
            ],
            [
                {"text": "📇 Ad Soyad ile Arama", "callback_data": "menu_adsoyad"},
                {"text": "🧩 Toplu Sorgulama", "callback_data": "menu_toplu"},
            ],
            [
                {"text": "💼 İşyeri Sorgulama", "callback_data": "menu_isyeri"},
                {"text": "🏡 Kişi Tapu Sorgulama", "callback_data": "menu_tapukisi"},
            ],
            [
                {"text": "💬 SMS OTP Sorgulama", "callback_data": "menu_smsotp"},
                {"text": "🪙 Binance Sorgulama", "callback_data": "menu_binance"},
            ],
            [
                {"text": "🤖 GPT'ye Sor", "callback_data": "menu_gpt"},
            ],
        ]
    }


def tc_sorgu(tip, tc):
    m = {
        "kisi": f"{SRC1}/tc.php?tc={tc}",
        "kisidetay": f"{SRC1}/tcpro.php?tc={tc}",
        "aile": f"{SRC1}/aile.php?tc={tc}",
        "sulale": f"{SRC1}/sulale.php?tc={tc}",
        "adres": f"{SRC1}/adres.php?tc={tc}",
        "tapu": f"{SRC1}/tapu.php?tc={tc}",
        "okul": f"{SRC1}/eokul.php?tc={tc}",
    }
    if tip in m:
        return http_get(m[tip])
    if tip == "toplu":
        return http_post_json(f"{SRC2}/toplu-sorgu?page=1", {"mod": "yakin", "tc": tc})
    if tip == "isyeri":
        return http_post_json(f"{SRC2}/isyeri-sorgu?page=1", {"tc": tc})
    if tip == "tapukisi":
        return http_post_json(f"{SRC2}/tapu-kisi?page=1", {"tc": tc})
    return None


@app.route("/sorgu_bot.php", methods=["POST"])
def webhook():
    try:
        u = request.get_json(force=True, silent=True)
    except Exception:
        u = None
    if not u:
        return "ok", 200

    # CALLBACK
    if "callback_query" in u:
        c = u["callback_query"]
        data = c["data"]
        chat_id = c["message"]["chat"]["id"]
        msg_id = c["message"]["message_id"]
        uid = c["from"]["id"]

        if not uye_mi(uid):
            api("sendMessage", {"chat_id": chat_id, "text": "Önce kanala katılman gerek dostum 🙂"})
            return "ok", 200

        if data == "ana_menu":
            isim = c["from"].get("first_name", "dostum")
            api("editMessageText", {
                "chat_id": chat_id,
                "message_id": msg_id,
                "text": f"Geri döndün {isim} 🙂 Ne arayacağını seç.",
                "reply_markup": json.dumps(ana_menu()),
            })
            return "ok", 200

        mp = {
            "menu_kisi": ("kisi", "Kişi sorgulaması için TC kimlik numarasını yaz (11 hane):", "TC"),
            "menu_kisidetay": ("kisidetay", "Detaylı kişi sorgusu için TC kimlik numarasını yaz:", "TC"),
            "menu_aile": ("aile", "Aile sorgusu için TC kimlik numarasını yaz:", "TC"),
            "menu_sulale": ("sulale", "Sülale sorgusu için TC kimlik numarasını yaz:", "TC"),
            "menu_adres": ("adres", "Adres sorgusu için TC kimlik numarasını yaz:", "TC"),
            "menu_tapu": ("tapu", "Tapu sorgusu için TC kimlik numarasını yaz:", "TC"),
            "menu_okul": ("okul", "Okul bilgisi için TC kimlik numarasını yaz:", "TC"),
            "menu_toplu": ("toplu", "Toplu sorgu için TC kimlik numarasını yaz:", "TC"),
            "menu_isyeri": ("isyeri", "İşyeri sorgusu için TC kimlik numarasını yaz:", "TC"),
            "menu_tapukisi": ("tapukisi", "Kişi tapu sorgusu için TC kimlik numarasını yaz:", "TC"),
            "menu_tcgsm": ("tcgsm", "TC'den GSM bulmak için kimlik numarasını yaz:", "TC"),
            "menu_gsmtc": ("gsmtc", "GSM'den TC bulmak için telefon numarasını yaz:", "GSM"),
            "menu_adsoyad": ("adsoyad", "Ad soyad ile arama yapacağız. Önce adı yaz:", "AD"),
            "menu_adaparsel": ("adaparsel", "Ada parsel sorgusu için ili yaz:", "IL"),
            "menu_smsotp": ("smsotp", "Kaç adet SMS OTP kaydı görmek istersin? (örnek: 50):", "SAYI"),
            "menu_binance": ("binance", "Binance sorgusu için ID (userId) yaz:", "SAYI"),
            "menu_gpt": ("gpt", "GPT'ye ne sormak istiyorsun? Sorunu yaz:", "METIN"),
        }
        if data in mp:
            tip, text, expect = mp[data]
            api("editMessageText", {"chat_id": chat_id, "message_id": msg_id, "text": text})
            STATE[uid] = {"step": tip, "expect": expect, "chat_id": chat_id, "ts": time.time()}
        return "ok", 200

    # MESSAGE
    if "message" not in u:
        return "ok", 200

    m = u["message"]
    text = (m.get("text") or "").strip()
    uid = m["from"]["id"]
    chat_id = m["chat"]["id"]

    if text in ("/start", "/help"):
        STATE.pop(uid, None)
        if not uye_mi(uid):
            api("sendMessage", {
                "chat_id": chat_id,
                "text": "Kapıyı çalmadan giremezsin dostum 🙂\n\n"
                        f"Önce kanala bir uğra, sonra buraya dön:\n👉 {CHANNEL_LINK}\n\n"
                        "Geldikten sonra tekrar /start yaz, gerisi kolay.",
            })
            return "ok", 200
        isim = m["from"].get("first_name", "dostum")
        api("sendMessage", {
            "chat_id": chat_id,
            "text": f"Selam {isim} 👋\n\nSorgu botuna hoş geldin. Aşağıdaki butonlardan ne arayacağını seç, "
                    f"gerisini bana bırak. Sonuçları dosya olarak önüne bırakıyorum.\n\n"
                    f"Takıldığın yer olursa @kehanetsorgu'ya yaz.",
            "reply_markup": json.dumps(ana_menu()),
        })
        return "ok", 200

    s = STATE.get(uid)
    if not s or (time.time() - s["ts"]) > STATE_TTL:
        STATE.pop(uid, None)
        return "ok", 200

    step = s["step"]
    expect = s["expect"]

    # Ad soyad 1
    if step == "adsoyad" and expect == "AD":
        STATE[uid] = {"step": "adsoyad_soyad", "expect": "SOYAD", "ad": text.upper(), "chat_id": chat_id, "ts": time.time()}
        api("sendMessage", {"chat_id": chat_id, "text": "Şimdi de soyadı yazar mısın?"})
        return "ok", 200
    # Ad soyad 2
    if step == "adsoyad_soyad":
        STATE.pop(uid, None)
        api("sendMessage", {"chat_id": chat_id, "text": "Bakıyorum... ⏳"})
        sonuc = http_get(f"{SRC1}/adsoyad.php?ad={s['ad']}&soyad={text.upper()}")
        dosya_gonder(chat_id, sonuc, "adsoyad")
        return "ok", 200

    # Ada parsel 1
    if step == "adaparsel" and expect == "IL":
        STATE[uid] = {"step": "adaparsel_ilce", "expect": "ILCE", "il": text.upper(), "chat_id": chat_id, "ts": time.time()}
        api("sendMessage", {"chat_id": chat_id, "text": "İlçeyi de yaz:"})
        return "ok", 200
    # Ada parsel 2
    if step == "adaparsel_ilce":
        STATE.pop(uid, None)
        api("sendMessage", {"chat_id": chat_id, "text": "Ada parsel kayıtlarına bakıyorum... ⏳"})
        sonuc = http_get(f"{SRC1}/adaparsel.php?il={s['il']}&ilce={text.upper()}")
        dosya_gonder(chat_id, sonuc, "adaparsel")
        return "ok", 200

    # TC'den GSM
    if step == "tcgsm":
        STATE.pop(uid, None)
        if not (text.isdigit() and len(text) == 11):
            api("sendMessage", {"chat_id": chat_id, "text": "TC 11 haneli olmalı, tekrar dener misin?"})
            return "ok", 200
        api("sendMessage", {"chat_id": chat_id, "text": "Telefon numarasını arıyorum... ⏳"})
        dosya_gonder(chat_id, http_get(f"{SRC1}/tcgsm.php?tc={text}"), "tcgsm")
        return "ok", 200

    # GSM'den TC
    if step == "gsmtc":
        STATE.pop(uid, None)
        gsm = text.replace(" ", "")
        api("sendMessage", {"chat_id": chat_id, "text": "Bu numaranın sahibine bakıyorum... ⏳"})
        dosya_gonder(chat_id, http_get(f"{SRC1}/gsmtc.php?gsm={gsm}&auth=fire"), "gsmtc")
        return "ok", 200

    # SMS OTP
    if step == "smsotp":
        STATE.pop(uid, None)
        if not text.isdigit():
            api("sendMessage", {"chat_id": chat_id, "text": "Sayı girmelisin, tekrar dener misin?"})
            return "ok", 200
        api("sendMessage", {"chat_id": chat_id, "text": "SMS kayıtlarına bakıyorum... ⏳"})
        sonuc = http_get(f"{SMS_BASE}?key={API_KEY}&device_id={DEVICE_ID}&action=otp&count={text}")
        if not sonuc or not sonuc.get("success"):
            api("sendMessage", {"chat_id": chat_id, "text": "Servis şu an cevap vermiyor, birazdan tekrar dene."})
            return "ok", 200
        kayitlar = sonuc.get("data", [])
        if not kayitlar:
            api("sendMessage", {"chat_id": chat_id, "text": "Yeni SMS kaydı yok gibi görünüyor."})
            return "ok", 200
        for i, k in enumerate(kayitlar[:10], 1):
            metin = (
                f"📩 **{i}. SMS**\n"
                f"Servis: {k.get('service')}\n"
                f"Ülke: {k.get('country')}\n"
                f"Telefon: `{k.get('phone')}`\n"
                f"Kod: `{k.get('otp')}`\n"
                f"Tarih: {k.get('date')}\n"
                f"Mesaj: {str(k.get('message', ''))[:200]}"
            )
            api("sendMessage", {"chat_id": chat_id, "text": metin, "parse_mode": "Markdown"})
        if len(kayitlar) > 10:
            api("sendMessage", {"chat_id": chat_id, "text": f"Toplam {len(kayitlar)} kayıt var, ilk 10'unu gösterdim."})
        return "ok", 200

    # Binance
    if step == "binance":
        STATE.pop(uid, None)
        if not text.isdigit():
            api("sendMessage", {"chat_id": chat_id, "text": "Binance ID sayı olmalı, tekrar dener misin?"})
            return "ok", 200
        api("sendMessage", {"chat_id": chat_id, "text": "Binance kaydına bakıyorum... ⏳"})
        sonuc = http_get(f"{BINANCE_BASE}?key={API_KEY}&device_id={DEVICE_ID}&id={text}")
        if not sonuc:
            api("sendMessage", {"chat_id": chat_id, "text": "Servisten cevap gelmedi, birazdan tekrar dene."})
            return "ok", 200
        if not sonuc.get("success") and not sonuc.get("ok"):
            api("sendMessage", {"chat_id": chat_id, "text": f"Sonuç yok: {sonuc.get('message', 'Bilinmeyen hata')}"})
            return "ok", 200
        metin = "🪙 **Binance Sorgu Sonucu**\n\n"
        data = sonuc.get("data") or sonuc.get("rows") or sonuc
        if isinstance(data, list) and data:
            data = data[0]
        if isinstance(data, dict):
            for k, v in data.items():
                metin += f"• **{k}**: {json.dumps(v, ensure_ascii=False) if isinstance(v, (list, dict)) else v}\n"
        else:
            metin += str(data)
        api("sendMessage", {"chat_id": chat_id, "text": metin[:4000], "parse_mode": "Markdown"})
        return "ok", 200

    # GPT
    if step == "gpt":
        STATE.pop(uid, None)
        api("sendMessage", {"chat_id": chat_id, "text": "Düşünüyorum... 🤔"})
        sonuc = http_get(f"{GPT_BASE}?key={API_KEY}&device_id={DEVICE_ID}&model=pollinations&q=" + requests.utils.quote(text))
        if not sonuc:
            api("sendMessage", {"chat_id": chat_id, "text": "Şu an cevap veremiyorum, birazdan tekrar dene."})
            return "ok", 200
        cevap = sonuc.get("response") or sonuc.get("answer") or sonuc.get("message")
        if isinstance(sonuc.get("data"), str):
            cevap = sonuc["data"]
        if not cevap:
            cevap = json.dumps(sonuc, ensure_ascii=False, indent=2)
        if len(cevap) > 4000:
            cevap = cevap[:4000] + "\n\n... (devamı kesildi)"
        api("sendMessage", {"chat_id": chat_id, "text": cevap})
        return "ok", 200

    # TC isteyenler
    if step in ["kisi", "kisidetay", "aile", "sulale", "adres", "tapu", "okul", "toplu", "isyeri", "tapukisi"]:
        STATE.pop(uid, None)
        if not (text.isdigit() and len(text) == 11):
            api("sendMessage", {"chat_id": chat_id, "text": "TC 11 haneli olmalı, tekrar dener misin?"})
            return "ok", 200
        api("sendMessage", {"chat_id": chat_id, "text": "Bir saniye, bakıyorum... ⏳"})
        dosya_gonder(chat_id, tc_sorgu(step, text), step)
        return "ok", 200

    return "ok", 200


# Ana sayfa
@app.route("/", methods=["GET"])
def index():
    return "Bot aktif 🚀", 200


# Webhook kurma endpoint'i (tarayicidan bir kez ac)
@app.route("/set_hook", methods=["GET"])
def set_hook():
    public_url = os.getenv("PUBLIC_URL", "https://telegrambot-c4s1.onrender.com")
    r = requests.get(f"https://api.telegram.org/bot{TOKEN}/setWebhook", params={
        "url": f"{public_url}/sorgu_bot.php",
        "allowed_updates": json.dumps(["message", "callback_query"]),
    })
    return r.json(), 200


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=int(os.getenv("PORT", 5000)))