import io
import os
import random
import requests
from flask import Flask, request, jsonify
from PIL import Image

app = Flask(__name__)

# --- CONFIGURATION ---
BOT_TOKEN = "8712159172:AAF-UYoi9t1Gzf0rfwJ4um6mxFEcMGydhbI"
TG_API_URL = f"https://api.telegram.org/bot{BOT_TOKEN}"
NASA_BASE_URL = "https://science.nasa.gov/specials/your-name-in-landsat/images"

# আপনার ওয়েলকাম ভিডিও লিঙ্ক
WELCOME_VIDEO = "https://files.catbox.moe/ybapbh.mp4"

HEADERS = {
    "User-Agent": "Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.8037.57 Mobile Safari/537.36"
}

# --- NASA IMAGE GENERATOR ENGINE ---
def fetch_letter_image(letter, chosen_style=None):
    all_variants = [1, 2, 3, 4, 5]
    if chosen_style and chosen_style in all_variants:
        priority_list = [chosen_style] + [v for v in all_variants if v != chosen_style]
    else:
        priority_list = all_variants.copy()
        random.shuffle(priority_list)

    for v in priority_list:
        img_url = f"{NASA_BASE_URL}/{letter}_{v}.jpg"
        try:
            res = requests.get(img_url, headers=HEADERS, timeout=8)
            if res.status_code == 200 and len(res.content) > 1000:
                return Image.open(io.BytesIO(res.content)).convert("RGB")
        except Exception:
            continue
    return None

def generate_landsat_image(name, style_val=None):
    raw_images = []
    for char in name:
        if char.isalpha():
            img = fetch_letter_image(char.lower(), chosen_style=style_val)
            if img:
                raw_images.append({"type": "char", "image": img})
        elif char.isspace():
            raw_images.append({"type": "space"})

    if not raw_images:
        return None

    char_images = [item["image"] for item in raw_images if item["type"] == "char"]
    if not char_images:
        return None

    target_height = min(img.height for img in char_images)
    processed_images = []
    gap = 12

    for item in raw_images:
        if item["type"] == "char":
            img = item["image"]
            aspect_ratio = img.width / img.height
            new_width = int(target_height * aspect_ratio)
            resized = img.resize((new_width, target_height), Image.Resampling.LANCZOS)
            processed_images.append(resized)
        elif item["type"] == "space":
            space_width = int(target_height * 0.45)
            space_img = Image.new("RGB", (space_width, target_height), color=(15, 15, 15))
            processed_images.append(space_img)

    total_width = sum(img.width for img in processed_images) + (len(processed_images) - 1) * gap
    final_canvas = Image.new("RGB", (total_width, target_height), color=(10, 10, 10))

    current_x = 0
    for img in processed_images:
        final_canvas.paste(img, (current_x, 0))
        current_x += img.width + gap

    output = io.BytesIO()
    final_canvas.save(output, format='JPEG', quality=95)
    output.seek(0)
    return output

# --- TELEGRAM API HELPERS ---
def send_video(chat_id, video_url, caption, reply_markup=None):
    payload = {
        "chat_id": chat_id,
        "video": video_url,
        "caption": caption,
        "parse_mode": "HTML"
    }
    if reply_markup:
        payload["reply_markup"] = reply_markup
    requests.post(f"{TG_API_URL}/sendVideo", json=payload, timeout=15)

def send_message(chat_id, text, reply_markup=None):
    payload = {
        "chat_id": chat_id,
        "text": text,
        "parse_mode": "HTML"
    }
    if reply_markup:
        payload["reply_markup"] = reply_markup
    requests.post(f"{TG_API_URL}/sendMessage", json=payload, timeout=10)

def send_photo(chat_id, photo_bytes, caption, reply_markup=None):
    files = {"photo": ("landsat.jpg", photo_bytes, "image/jpeg")}
    data = {
        "chat_id": chat_id,
        "caption": caption,
        "parse_mode": "HTML"
    }
    if reply_markup:
        data["reply_markup"] = reply_markup
    requests.post(f"{TG_API_URL}/sendPhoto", data=data, files=files, timeout=30)

def answer_callback(callback_query_id, text=None):
    payload = {"callback_query_id": callback_query_id}
    if text:
        payload["text"] = text
    requests.post(f"{TG_API_URL}/answerCallbackQuery", json=payload, timeout=10)

# --- WEBHOOK ROUTE ---
@app.route('/', methods=['GET', 'POST'])
def webhook():
    if request.method == 'GET':
        return jsonify({"status": "Bot is active and running!"})

    data = request.get_json(force=True, silent=True)
    if not data:
        return "OK"

    # ১. বাটন ক্লিক হ্যান্ডেল (Callback Queries)
    if "callback_query" in data:
        cb = data["callback_query"]
        cb_id = cb["id"]
        chat_id = cb["message"]["chat"]["id"]
        cb_data = cb.get("data", "")

        if cb_data == "create_name":
            answer_callback(cb_id)
            send_message(
                chat_id,
                "🛰 <b>Send me any Name or Word!</b>\n\n"
                "<i>Example:</i> <code>Muhammad</code> or <code>NASA</code>\n\n"
                "💡 <i>Tips:</i> You can also specify style like: <code>Muhammad 2</code>"
            )
        elif cb_data.startswith("style_"):
            parts = cb_data.split("_")
            style_num = int(parts[1])
            name = "_".join(parts[2:])

            answer_callback(cb_id, text=f"Generating Style {style_num}...")
            send_message(chat_id, f"🛰 <i>Generating Landsat satellite image for <b>{name}</b> (Style {style_num})...</i>")

            img_bytes = generate_landsat_image(name, style_val=style_num)
            if img_bytes:
                markup = {
                    "inline_keyboard": [
                        [
                            {"text": "🔄 Style 1", "callback_data": f"style_1_{name}"},
                            {"text": "🔄 Style 2", "callback_data": f"style_2_{name}"},
                            {"text": "🔄 Style 3", "callback_data": f"style_3_{name}"}
                        ],
                        [
                            {"text": "🔄 Style 4", "callback_data": f"style_4_{name}"},
                            {"text": "🔄 Style 5", "callback_data": f"style_5_{name}"}
                        ],
                        [{"text": "✨ New Name", "callback_data": "create_name"}]
                    ]
                }
                caption = f"🛰 <b>NASA Landsat Satellite View</b>\n🏷 <b>Name:</b> <code>{name}</code> | <b>Style:</b> {style_num}"
                send_photo(chat_id, img_bytes, caption, reply_markup=markup)
            else:
                send_message(chat_id, "❌ <i>Failed to generate satellite photo. Please try again!</i>")
        return "OK"

    # ২. মেসেজ হ্যান্ডেল
    if "message" in data:
        msg = data["message"]
        chat_id = msg["chat"]["id"]
        text = msg.get("text", "").strip()

        if text.startswith("/start"):
            welcome_caption = (
                "⚡️ <b>WELCOME TO NASA LANDSAT BOT</b> ⚡️\n\n"
                "◈──────────────────────────◈\n\n"
                "🛰 <b>Your Name in Landsat Satellite Imagery</b>\n"
                "📸 <i>Generate breathtaking orbital satellite names powered by NASA Landsat.</i>\n\n"
                "◈──────────────────────────◈\n\n"
                "👇 <b>Select an option from below to begin:</b>"
            )
            markup = {
                "inline_keyboard": [
                    [{"text": "🛰 Create Landsat Name 🛰", "callback_data": "create_name"}]
                ]
            }
            send_video(chat_id, WELCOME_VIDEO, welcome_caption, reply_markup=markup)
            return "OK"

        # নাম প্রসেস করা
        if text:
            # স্টাইল প্যারামিটার চেক (যেমন: Muhammad 2)
            parts = text.split()
            style_val = None
            if len(parts) > 1 and parts[-1].isdigit() and 1 <= int(parts[-1]) <= 5:
                style_val = int(parts[-1])
                name = " ".join(parts[:-1])
            else:
                name = text

            send_message(chat_id, f"🛰 <i>Processing NASA satellite data for <b>{name}</b>...</i>")

            img_bytes = generate_landsat_image(name, style_val=style_val)
            if img_bytes:
                markup = {
                    "inline_keyboard": [
                        [
                            {"text": "🔄 Style 1", "callback_data": f"style_1_{name}"},
                            {"text": "🔄 Style 2", "callback_data": f"style_2_{name}"},
                            {"text": "🔄 Style 3", "callback_data": f"style_3_{name}"}
                        ],
                        [
                            {"text": "🔄 Style 4", "callback_data": f"style_4_{name}"},
                            {"text": "🔄 Style 5", "callback_data": f"style_5_{name}"}
                        ],
                        [{"text": "✨ Create Another Name", "callback_data": "create_name"}]
                    ]
                }
                caption = f"🛰 <b>NASA Landsat Satellite View</b>\n🏷 <b>Name:</b> <code>{name}</code>"
                send_photo(chat_id, img_bytes, caption, reply_markup=markup)
            else:
                send_message(chat_id, "⚠️ <i>Please send a valid English name containing letters A-Z.</i>")

    return "OK"

if __name__ == '__main__':
    app.run()
