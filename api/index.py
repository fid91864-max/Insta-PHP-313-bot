from http.server import BaseHTTPRequestHandler
import json
import os
import requests

TOKEN = os.environ.get("BOT_TOKEN", "8909542012:AAE9x6csWcWrU7PuKd8XqlGIpooygZXFG7c")

def send_telegram(method, payload):
    url = f"https://api.telegram.org/bot{TOKEN}/{method}"
    try:
        r = requests.post(url, json=payload, timeout=10)
        return r.json()
    except Exception:
        return {}

def delete_message(chat_id, message_id):
    send_telegram("deleteMessage", {
        "chat_id": chat_id,
        "message_id": message_id
    })

def send_message(chat_id, text, reply_to=None, reply_markup=None):
    payload = {
        "chat_id": chat_id,
        "text": text,
        "parse_mode": "HTML"
    }
    if reply_to:
        payload["reply_to_message_id"] = reply_to
    if reply_markup:
        payload["reply_markup"] = reply_markup
    return send_telegram("sendMessage", payload)

class handler(BaseHTTPRequestHandler):
    def do_POST(self):
        content_length = int(self.headers.get("Content-Length", 0))
        post_data = self.rfile.read(content_length)

        try:
            update = json.loads(post_data.decode("utf-8"))
        except Exception:
            self.send_response(400)
            self.end_headers()
            return

        message = update.get("message", {})
        chat_id = message.get("chat", {}).get("id")
        message_id = message.get("message_id")
        text = message.get("text", "")

        if text == "/start":
            welcome_text = (
                "👋 <b>স্বাগতম!</b>\n\n"
                "আমাকে ছবি অথবা ভিডিও পাঠান (সর্বোচ্চ ২০ MB)।\n"
                "সরাসরি Catbox লিংক তৈরি হয়ে যাবে।"
            )
            send_message(chat_id, welcome_text, reply_to=message_id)

        else:
            file_id = None
            file_name = "upload.jpg"
            mime_type = "image/jpeg"
            media_type = "Image"

            if "photo" in message:
                file_id = message["photo"][-1]["file_id"]
                file_name = "image.jpg"
                mime_type = "image/jpeg"
                media_type = "Image"
            elif "video" in message:
                vid = message["video"]
                file_id = vid["file_id"]
                file_name = vid.get("file_name", "video.mp4")
                mime_type = vid.get("mime_type", "video/mp4")
                media_type = "Video"
            elif "document" in message:
                doc = message["document"]
                doc_mime = doc.get("mime_type", "")
                if doc_mime.startswith("image/") or doc_mime.startswith("video/"):
                    file_id = doc["file_id"]
                    file_name = doc.get("file_name", "file.bin")
                    mime_type = doc_mime
                    media_type = "Video" if doc_mime.startswith("video/") else "Image"

            if file_id:
                # লোডিং মেসেজ পাঠানো
                status_res = send_message(
                    chat_id,
                    f"⏳ <b>{media_type} ডাউনলোড ও আপলোড হচ্ছে, অপেক্ষা করুন...</b>",
                    reply_to=message_id
                )
                status_msg_id = status_res.get("result", {}).get("message_id")

                try:
                    # ১. ফাইল পাথ আনা
                    file_info_url = f"https://api.telegram.org/bot{TOKEN}/getFile?file_id={file_id}"
                    info_res = requests.get(file_info_url, timeout=10).json()

                    if info_res.get("ok"):
                        file_path = info_res["result"]["file_path"]
                        download_url = f"https://api.telegram.org/file/bot{TOKEN}/{file_path}"

                        # ২. ফাইল ডাউনলোড
                        dl_res = requests.get(download_url, timeout=30)
                        if dl_res.status_code == 200:
                            file_data = dl_res.content

                            # ৩. Catbox আপলোড
                            files = {
                                "reqtype": (None, "fileupload"),
                                "fileToUpload": (file_name, file_data, mime_type)
                            }
                            headers = {
                                "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"
                            }

                            cb_res = requests.post(
                                "https://catbox.moe/user/api.php",
                                files=files,
                                headers=headers,
                                timeout=45
                            )

                            if cb_res.status_code == 200 and cb_res.text.startswith("http"):
                                catbox_url = cb_res.text.strip()
                                reply_text = f"🚀 <b>{media_type} Uploaded!</b>\n\n🔗 <b>Direct Link:</b>\n<code>{catbox_url}</code>"
                                markup = {
                                    "inline_keyboard": [
                                        [{"text": f"🌐 Open {media_type}", "url": catbox_url}]
                                    ]
                                }
                                if status_msg_id:
                                    delete_message(chat_id, status_msg_id)
                                send_message(chat_id, reply_text, reply_to=message_id, reply_markup=markup)
                            else:
                                send_message(chat_id, f"⚠️ Upload failed: {cb_res.text[:50]}", reply_to=message_id)
                        else:
                            send_message(chat_id, "❌ ফাইল ডাউনলোড করা যায়নি (সাইজ ২০ MB এর বেশি হতে পারে)।", reply_to=message_id)
                    else:
                        send_message(chat_id, "❌ ফাইলের তথ্য পাওয়া যায়নি।", reply_to=message_id)

                except Exception as e:
                    send_message(chat_id, f"❌ Error: {str(e)}", reply_to=message_id)

        self.send_response(200)
        self.end_headers()
        self.wfile.write(b"OK")

    def do_GET(self):
        self.send_response(200)
        self.end_headers()
        self.wfile.write(b"Bot is alive!")
