from flask import Flask, request, jsonify, send_file
import requests
from PIL import Image
import io
import random

app = Flask(__name__)

NASA_BASE_URL = "https://science.nasa.gov/specials/your-name-in-landsat/images"

headers = {
    "User-Agent": "Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.8037.57 Mobile Safari/537.36"
}

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
            res = requests.get(img_url, headers=headers, timeout=8)
            if res.status_code == 200 and len(res.content) > 1000:
                img = Image.open(io.BytesIO(res.content)).convert("RGB")
                return img
        except Exception:
            continue
    return None

def generate_combined_image(name, style_val=None):
    raw_images = []
    
    for char in name:
        if char.isalpha():
            letter = char.lower()
            img = fetch_letter_image(letter, chosen_style=style_val)
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
    total_height = target_height

    final_canvas = Image.new("RGB", (total_width, total_height), color=(10, 10, 10))

    current_x = 0
    for img in processed_images:
        final_canvas.paste(img, (current_x, 0))
        current_x += img.width + gap

    return final_canvas

@app.route('/', methods=['GET'])
def home():
    return jsonify({
        "status": "Online",
        "service": "NASA Landsat Multi-Style Name Image Generator",
        "endpoints": {
            "image": "/image?name=Muhammad&style=1",
            "available_styles": "1 to 5 (or leave empty for random)"
        }
    })

@app.route('/image', methods=['GET'])
def get_name_image():
    name = request.args.get('name', '').strip()
    if not name:
        return jsonify({"error": "Please provide a name parameter, e.g. /image?name=Muhammad&style=1"}), 400

    style_param = request.args.get('style', '').strip()
    try:
        style_val = int(style_param) if style_param.isdigit() else None
    except ValueError:
        style_val = None

    combined_img = generate_combined_image(name, style_val=style_val)
    if not combined_img:
        return jsonify({"error": "Failed to generate image."}), 500

    img_io = io.BytesIO()
    combined_img.save(img_io, 'JPEG', quality=95, subsampling=0)
    img_io.seek(0)

    return send_file(img_io, mimetype='image/jpeg', as_attachment=False)

# Vercel Serverless হ্যান্ডলারের জন্য
if __name__ == '__main__':
    app.run()
