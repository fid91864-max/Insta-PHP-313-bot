from flask import Flask, request, Response
import requests
import json
import re
import os

app = Flask(__name__)
app.config['JSON_AS_ASCII'] = False

PROFILE_API_URL = "https://inflact.com/downloader/api/downloader/profile/?lang=en"

# Vercel ও লোকাল উভয় জায়গায় রুট ডিরেক্টরি থেকে ফাইলের পাথ নেওয়া
CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
JSON_FILE_PATH = os.path.join(CURRENT_DIR, "..", "bd_users.json")

HEADERS = {
    "host": "inflact.com",
    "sec-ch-ua-platform": "\"Android\"",
    "user-agent": "Mozilla/5.0 (Linux; Android 14; 2409BRN2CY Build/UP1A.231005.007) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.8037.57 Mobile Safari/537.36",
    "sec-ch-ua": "\"Chromium\";v=\"154\", \"Android WebView\";v=\"154\", \"Not A(Brand\";v=\"99\"",
    "sec-ch-ua-mobile": "?1",
    "x-client-signature": "a742dba2f85e7d04f713b1331346789a84bf49adcfdeead7f834fbe60f10a7eb",
    "x-client-token": "eyJ0aW1lc3RhbXAiOjE3OTE2MzY2MDQsImNsaWVudElkIjoiMjcwMGY2MGI5MjZmNWRiOWJlNmM0MDYwM2YyNzI0YzkiLCJub25jZSI6ImE2YTFiMzg0ZTNmNDM0YjI0YTA4MDE5ZWY0OGNhMTJhIn0=",
    "accept": "*/*",
    "origin": "https://inflact.com",
    "x-requested-with": "mark.via.gp",
    "sec-fetch-site": "same-origin",
    "sec-fetch-mode": "cors",
    "sec-fetch-dest": "empty",
    "referer": "https://inflact.com/instagram-downloader/pfp/",
    "accept-language": "en-US,en;q=0.9"
}

COOKIES = {
    "ingramer_sid": "03nag8dqmevkdehq75eg463am4",
    "_csrf": "bb1367e263b9e3a923b50a92c92949ae9f28a27d5d454ad30a85bff48b8f00dfa",
    "user_timezone": "3edf99fc1da9e15ef8378b44f8b7a901504b5ecf8f42919f55b10393f0b03367a%3A2%3A%7Bi%3A0%3Bs%3A13%3A%22user_timezone%22%3Bi%3A1%3Bs%3A10%3A%22Asia%2FDhaka%22%3B%7D",
    "from_landing": "21fb3640e438032a01ae19d86f8ad23ba384c0692f22312e7538cf7ab5874468a%3A2%3A%7Bi%3A0%3Bs%3A12%3A%22from_landing%22%3Bi%3A1%3Bs%3A10%3A%22downloader%22%3B%7D"
}

DB_BY_USERNAME = {}
DB_BY_PHONE = {}

def normalize_phone(phone_input):
    digits = re.sub(r'\D', '', str(phone_input))
    if digits.startswith('880'):
        return digits
    elif digits.startswith('0'):
        return '88' + digits
    elif len(digits) == 10 and digits.startswith('1'):
        return '880' + digits
    return digits

def load_local_db():
    global DB_BY_USERNAME, DB_BY_PHONE
    if not os.path.exists(JSON_FILE_PATH):
        return

    try:
        with open(JSON_FILE_PATH, 'r', encoding='utf-8') as f:
            for line in f:
                line = line.strip()
                if not line:
                    continue
                try:
                    record = json.loads(line)
                    user = record.get('u', '').strip().lower()
                    phone = record.get('t', '').strip()

                    cleaned = {
                        "username": record.get("u"),
                        "phone": phone,
                        "email": record.get("e"),
                        "name": record.get("n"),
                        "address": record.get("a"),
                        "id": record.get("id")
                    }

                    if user:
                        DB_BY_USERNAME[user] = cleaned
                    if phone:
                        DB_BY_PHONE[normalize_phone(phone)] = cleaned
                except Exception:
                    continue
    except Exception as e:
        print(f"Database error: {e}")

load_local_db()

def clean_json_response(data_dict, status_code=200):
    return Response(
        json.dumps(data_dict, ensure_ascii=False, indent=2),
        status=status_code,
        mimetype="application/json; charset=utf-8"
    )

def fetch_inflact_hd_profile(username):
    files = {'url': (None, username)}
    try:
        res = requests.post(
            PROFILE_API_URL,
            headers=HEADERS,
            cookies=COOKIES,
            files=files,
            timeout=15
        )
        if res.status_code == 200:
            data = res.json()
            profile = data.get("data", {}).get("profile")
            if profile:
                hd_url = profile.get("profile_pic_url_hd") or profile.get("profile_pic_download_url") or profile.get("profile_pic_url")
                return {
                    "live_found": True,
                    "id": profile.get("id"),
                    "username": profile.get("username"),
                    "full_name": profile.get("full_name"),
                    "biography": profile.get("biography"),
                    "followers": profile.get("edge_followed_by", {}).get("count"),
                    "following": profile.get("edge_follow", {}).get("count"),
                    "is_private": profile.get("is_private"),
                    "is_verified": profile.get("is_verified"),
                    "profile_pic_hd": hd_url
                }
    except Exception as e:
        print("Fetch HD error:", e)
    return {"live_found": False}

@app.route('/', methods=['GET'])
def index():
    return clean_json_response({
        "status": "Online",
        "service": "Instagram True HD & Local BD Info API",
        "usage": {
            "by_username": "/api/ig?username=bashars_business",
            "by_phone": "/api/ig?phone=01636040344",
            "direct_hd_pic": "/api/ig/dp?username=bashars_business"
        }
    })

@app.route('/api/ig', methods=['GET'])
def get_user_info():
    username = request.args.get('username', '').strip().lower().replace('@', '')
    phone = request.args.get('phone', '').strip()
    query = request.args.get('query', '').strip()

    if query:
        if query.startswith(('01', '+880', '880')):
            phone = query
        else:
            username = query.lower().replace('@', '')

    local_data = None

    if phone:
        local_data = DB_BY_PHONE.get(normalize_phone(phone))
        if local_data and not username:
            username = (local_data.get('username') or '').lower()

    if username and not local_data:
        local_data = DB_BY_USERNAME.get(username)

    live_data = {}
    if username:
        live_data = fetch_inflact_hd_profile(username)

    if not local_data and not live_data.get("live_found"):
        return clean_json_response({
            "status": "not_found",
            "message": "Neither local record nor live Instagram profile found."
        }), 404

    return clean_json_response({
        "status": "success",
        "local_db_matched": bool(local_data),
        "live_instagram_matched": live_data.get("live_found", False),
        "local_records": local_data if local_data else {
            "message": "Phone number or email not in local bd_users.json"
        },
        "instagram_profile": {
            "username": username or (local_data.get("username") if local_data else None),
            "name": live_data.get("full_name") or (local_data.get("name") if local_data else None),
            "biography": live_data.get("biography"),
            "followers": live_data.get("followers"),
            "following": live_data.get("following"),
            "is_private": live_data.get("is_private"),
            "is_verified": live_data.get("is_verified"),
            "profile_pic_hd": live_data.get("profile_pic_hd")
        }
    })

@app.route('/api/ig/dp', methods=['GET'])
def get_hd_picture_stream():
    username = request.args.get('username') or request.args.get('id') or request.args.get('query') or ''
    username = username.strip().lower().replace('@', '')

    if not username:
        return "Username is required", 400

    live_data = fetch_inflact_hd_profile(username)
    hd_url = live_data.get("profile_pic_hd")

    if not hd_url:
        return "HD image could not be fetched", 404

    try:
        img_res = requests.get(hd_url, headers={"User-Agent": HEADERS["user-agent"]}, timeout=15)
        return Response(img_res.content, mimetype=img_res.headers.get('Content-Type', 'image/jpeg'))
    except Exception as e:
        return str(e), 500

if __name__ == '__main__':
    app.run()
