import json
import base64
import html
import re
from flask import Flask, request, Response
from curl_cffi import requests

app = Flask(__name__)

def clean_astro_data(node):
    if isinstance(node, list):
        if len(node) == 2 and isinstance(node[0], int):
            return clean_astro_data(node[1])
        return [clean_astro_data(x) for x in node]
    elif isinstance(node, dict):
        return {k: clean_astro_data(v) for k, v in node.items()}
    return node

@app.route('/', defaults={'path': ''})
@app.route('/<path:path>')
def check_number(path):
    phone = request.args.get('phone')
    
    if not phone:
        err = {
            "status": "error", 
            "message": "Phone number is required! Example: /?phone=+919876543210"
        }
        return Response(json.dumps(err, indent=2, ensure_ascii=False), mimetype='application/json', status=400)

    phone = phone.strip()
    if not phone.startswith('+'):
        phone = '+' + phone

    try:
        payload_data = json.dumps({"q": phone}, separators=(',', ':'))
        encoded_payload = base64.b64encode(payload_data.encode('utf-8')).decode('utf-8').rstrip('=')
        url = f"https://www.truecaller.com/scam-checker/?share=0.{encoded_payload}"

        headers = {
            "accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
            "accept-language": "en-US,en;q=0.9",
            "referer": "https://www.truecaller.com/scam-checker",
            "user-agent": "Mozilla/5.0 (Linux; Android 14; Mobile) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36"
        }

        session = requests.Session(impersonate="chrome120")
        response = session.get(url, headers=headers, timeout=15)

        if response.status_code != 200:
            err = {"status": "error", "message": f"Blocked or status code {response.status_code}"}
            return Response(json.dumps(err, indent=2, ensure_ascii=False), mimetype='application/json', status=502)

        html_content = response.text

        all_props = re.findall(r'props="({&quot;[^"]+})"', html_content)
        
        target_astro = None
        for prop in all_props:
            unescaped = html.unescape(prop)
            if "phoneNumberInsights" in unescaped or "scamFeedInsight" in unescaped:
                target_astro = json.loads(unescaped)
                break

        if not target_astro:
            err = {"status": "error", "message": "No data found"}
            return Response(json.dumps(err, indent=2, ensure_ascii=False), mimetype='application/json', status=404)

        cleaned = clean_astro_data(target_astro)
        query_result = cleaned.get("searchQueryResult", {}).get("data", {})
        
        phone_insights = query_result.get("phoneNumberInsights", [])
        scam_posts = query_result.get("scamFeedInsight", {}).get("scamFeedPosts", [])

        operator_name = "Unknown"
        country_code = "Unknown"
        is_scam = False
        fraud_decision = 0
        total_comments = 0

        if phone_insights:
            info = phone_insights[0]
            country_code = info.get("countryCode", "Unknown")
            search_res = info.get("searchResult", {})
            operator_name = search_res.get("operatorName", "Unknown")
            fraud_decision = search_res.get("fraudDecision", 0)
            total_comments = search_res.get("contactCommentsCount", 0)
            is_scam = fraud_decision > 0

        formatted_reports = []
        for post in scam_posts:
            if isinstance(post, list) and len(post) > 0:
                p_data = post[0] if isinstance(post[0], dict) else post[1]
            else:
                p_data = post

            if not isinstance(p_data, dict):
                continue

            slug = p_data.get("webSlug")
            post_url = f"https://www.truecaller.com/scam-checker/post/{slug}" if slug else None

            formatted_reports.append({
                "reporter": {
                    "name": p_data.get("userName") or "Anonymous",
                    "avatar": p_data.get("avatarUri") or None
                },
                "title": p_data.get("translatedTitle") or p_data.get("title"),
                "description": p_data.get("translatedDescription") or p_data.get("description"),
                "evidenceImage": p_data.get("imageUri") or None,
                "stats": {
                    "upvotes": int(p_data.get("upvotes", 0)),
                    "comments": int(p_data.get("comments", 0))
                },
                "postUrl": post_url,
                "createdAt": p_data.get("createdAt")
            })

        final_response = {
            "status": "success",
            "data": {
                "phoneNumber": phone,
                "countryCode": country_code,
                "operator": operator_name,
                "isScam": is_scam,
                "fraudDecisionCode": fraud_decision,
                "totalReportsCount": total_comments,
                "communityReports": formatted_reports
            }
        }

        return Response(
            json.dumps(final_response, indent=2, ensure_ascii=False),
            mimetype='application/json',
            status=200
        )

    except Exception as e:
        err = {"status": "error", "message": str(e)}
        return Response(json.dumps(err, indent=2, ensure_ascii=False), mimetype='application/json', status=500)
