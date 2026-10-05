import json
import re
from flask import Flask, Response, request
import requests

app = Flask(__name__)

BOT_TOKEN = "8785527680:AAFWQkMChNXlpfQVdFc4LkeMvLSybI6DhGE"
TELEGRAM_API = f"https://api.telegram.org/bot{BOT_TOKEN}"
SCAM_API_BASE = "https://truecaller-scam-api-fid91864-maxs-projects.vercel.app/"

# কাস্টম বাটন লেআউট
BOT_KEYBOARD = {
    "inline_keyboard": [
        [{"text": "🔵 Search New", "callback_data": "/action"}],
        [{"text": "🔴 Clear", "callback_data": "/delete"}],
        [{"text": "⚪ Main Menu", "callback_data": "/menu"}],
    ]
}


def send_message(chat_id, text, reply_markup=None):
  payload = {
      "chat_id": chat_id,
      "text": text,
      "parse_mode": "HTML",
      "disable_web_page_preview": True,
  }
  if reply_markup:
    payload["reply_markup"] = reply_markup
  try:
    requests.post(f"{TELEGRAM_API}/sendMessage", json=payload, timeout=10)
  except Exception as e:
    print(f"Error sending message: {e}")


def format_scam_data(data):
  is_scam = data.get("isScam", False)
  phone = data.get("phoneNumber", "N/A")
  country = data.get("countryCode", "N/A")
  operator = data.get("operator", "N/A")
  total_reports = data.get("totalReportsCount", 0)

  verdict = "🚨 <b>POTENTIAL FRAUD / SCAM!</b>" if is_scam else "✅ <b>LIKELY SAFE</b>"

  text = f"{verdict}\n\n"
  text += f"📞 <b>Number:</b> <code>{phone}</code>\n"
  text += f"🌍 <b>Country:</b> {country}\n"
  text += f"📡 <b>Operator:</b> {operator}\n"
  text += f"📊 <b>Total Reports:</b> {total_reports}\n\n"

  reports = data.get("communityReports", [])
  if reports:
    text += "📝 <b>Latest Community Reports:</b>\n"
    text += "────────────────────\n"
    for idx, r in enumerate(reports[:3], 1):
      user = r.get("reporter", {}).get("name", "Anonymous")
      title = r.get("title", "No Title")
      desc = r.get("description", "No Description")
      post_url = r.get("postUrl")

      text += f"<b>{idx}. {title}</b>\n"
      text += f"👤 <i>By: {user}</i>\n"
      text += f"💬 <i>\"{desc}\"</i>\n"
      if post_url:
        text += f"🔗 <a href='{post_url}'>View Post</a>\n"
      text += "\n"
  else:
    text += "ℹ️ <i>No specific user reports found for this number.</i>\n"

  return text


@app.route("/", methods=["POST", "GET"])
@app.route("/api/bot", methods=["POST", "GET"])
def telegram_webhook():
  if request.method == "GET":
    return "Bot Webhook is running successfully!", 200

  update = request.get_json(force=True, silent=True)
  if not update:
    return Response(status=200)

  # ১. Callback Query (বাটনে চাপলে)
  if "callback_query" in update:
    cb = update["callback_query"]
    chat_id = cb["message"]["chat"]["id"]
    cb_data = cb.get("data", "")

    if cb_data == "/action":
      send_message(
          chat_id,
          "🔵 <b>Send a phone number with country code:</b>\n<i>Example:</i>"
          " <code>+919876543210</code>",
          BOT_KEYBOARD,
      )
    elif cb_data == "/delete":
      send_message(chat_id, "🔴 <b>Session cleared.</b>", BOT_KEYBOARD)
    elif cb_data == "/menu":
      send_message(
          chat_id,
          "⚪ <b>Main Menu:</b>\nSend any number directly to check.",
          BOT_KEYBOARD,
      )

    try:
      requests.post(
          f"{TELEGRAM_API}/answerCallbackQuery",
          json={"callback_query_id": cb["id"]},
          timeout=5,
      )
    except Exception:
      pass
    return Response(status=200)

  # ২. টেক্সট মেসেজ
  message = update.get("message", {})
  chat_id = message.get("chat", {}).get("id")
  text = message.get("text", "").strip()

  if not chat_id or not text:
    return Response(status=200)

  if text == "/start":
    welcome = (
        "👋 <b>Welcome to Scam Checker Bot!</b>\n\n"
        "Send any phone number with country code to check if it's safe or"
        " scam.\n"
        "<i>Example:</i> <code>+919876543210</code>"
    )
    send_message(chat_id, welcome, BOT_KEYBOARD)
    return Response(status=200)

  # ফোন নম্বর ফিল্টার
  phone_match = re.search(r"(\+?\d{8,15})", text)
  if not phone_match:
    send_message(
        chat_id,
        "⚠️ Please send a valid phone number (e.g."
        " <code>+919876543210</code>).",
        BOT_KEYBOARD,
    )
    return Response(status=200)

  phone = phone_match.group(1)
  if not phone.startswith("+"):
    phone = "+" + phone

  send_message(chat_id, f"🔍 <i>Checking {phone}...</i>")

  try:
    res = requests.get(f"{SCAM_API_BASE}?phone={phone}", timeout=15)
    res_data = res.json()

    if res_data.get("status") == "success":
      result_msg = format_scam_data(res_data.get("data", {}))
      send_message(chat_id, result_msg, BOT_KEYBOARD)
    else:
      err_msg = res_data.get("message", "Could not fetch details.")
      send_message(chat_id, f"❌ <b>Error:</b> {err_msg}", BOT_KEYBOARD)

  except Exception:
    send_message(
        chat_id,
        "⚠️️ Failed to connect to Scam Checker API. Please try again.",
        BOT_KEYBOARD,
    )

  return Response(status=200)
