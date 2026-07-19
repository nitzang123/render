import os
import io
import requests
import threading
from flask import Flask, request, jsonify
from PIL import Image

app = Flask(__name__)

# מפתח אבטחה שנגדיר ב-Render וב-PHP
API_KEY = os.environ.get("RENDER_API_KEY", "my_super_secret_key")

# שמירת סטטוס של משימות (בזיכרון השרת)
jobs_status = {}

def process_images_background(access_token, folder_id):
    headers = {"Authorization": f"Bearer {access_token}"}
    jobs_status[folder_id] = {"status": "processing", "total": 0, "done": 0}

    try:
        # 1. משיכת כל התמונות מתיקיית המקור
        query = f"'{folder_id}' in parents and trashed=false and mimeType contains 'image/'"
        url = f"https://www.googleapis.com/drive/v3/files?q={requests.utils.quote(query)}&fields=files(id,name)&pageSize=1000&supportsAllDrives=true"
        res = requests.get(url, headers=headers).json()
        files = res.get('files', [])
        
        if not files:
            jobs_status[folder_id]["status"] = "completed"
            return
            
        jobs_status[folder_id]["total"] = len(files)

        # 2. יצירת תיקיית resize
        folder_metadata = {
            "name": "resize",
            "mimeType": "application/vnd.google-apps.folder",
            "parents": [folder_id]
        }
        res_folder = requests.post(
            "https://www.googleapis.com/drive/v3/files?supportsAllDrives=true", 
            headers={**headers, "Content-Type": "application/json"}, 
            json=folder_metadata
        ).json()
        resize_folder_id = res_folder.get('id')

        # 3. מתן הרשאות לכולם לתיקיית resize
        perm_data = {"type": "anyone", "role": "writer"}
        requests.post(
            f"https://www.googleapis.com/drive/v3/files/{resize_folder_id}/permissions?supportsAllDrives=true",
            headers={**headers, "Content-Type": "application/json"},
            json=perm_data
        )

        # 4. עיבוד התמונות
        for file in files:
            file_id = file['id']
            file_name = file['name']

            # הורדת התמונה לזיכרון השרת
            img_response = requests.get(f"https://www.googleapis.com/drive/v3/files/{file_id}?alt=media&supportsAllDrives=true", headers=headers)
            if img_response.status_code != 200:
                continue
                
            img_data = io.BytesIO(img_response.content)
            
            with Image.open(img_data) as img:
                # כיווץ ל-2048
                img_2048 = img.copy()
                img_2048.thumbnail((2048, 1365), Image.Resampling.LANCZOS)
                buf_2048 = io.BytesIO()
                # שמירה כ-JPEG עם איכות טובה
                img_2048.convert('RGB').save(buf_2048, format='JPEG', quality=85)
                buf_2048.seek(0)

                # עדכון הקובץ הקיים בדרייב
                requests.patch(
                    f"https://www.googleapis.com/upload/drive/v3/files/{file_id}?uploadType=media&supportsAllDrives=true",
                    headers={**headers, "Content-Type": "image/jpeg"},
                    data=buf_2048
                )

                # כיווץ ל-440 (מתוך המקור שכבר בזיכרון)
                img_440 = img.copy()
                img_440.thumbnail((440, 293), Image.Resampling.LANCZOS)
                buf_440 = io.BytesIO()
                img_440.convert('RGB').save(buf_440, format='JPEG', quality=85)
                buf_440.seek(0)

                # העלאת הקובץ הקטן לתיקיית resize
                # שלב א': יצירת המטא-דאטה של הקובץ
                new_file_meta = {"name": file_name, "parents": [resize_folder_id]}
                meta_res = requests.post(
                    "https://www.googleapis.com/drive/v3/files?supportsAllDrives=true",
                    headers={**headers, "Content-Type": "application/json"},
                    json=new_file_meta
                ).json()
                new_file_id = meta_res.get('id')

                # שלב ב': העלאת התוכן (media)
                if new_file_id:
                    requests.patch(
                        f"https://www.googleapis.com/upload/drive/v3/files/{new_file_id}?uploadType=media&supportsAllDrives=true",
                        headers={**headers, "Content-Type": "image/jpeg"},
                        data=buf_440
                    )
            
            # קידום ספירת ההתקדמות
            jobs_status[folder_id]["done"] += 1

        jobs_status[folder_id]["status"] = "completed"

    except Exception as e:
        jobs_status[folder_id] = {"status": "error", "message": str(e)}

# נתיב לקבלת הפקודה מה-PHP
@app.route('/start_resize', methods=['POST'])
def start_resize():
    if request.headers.get("X-API-KEY") != API_KEY:
        return jsonify({"error": "Unauthorized"}), 401
    
    data = request.json
    access_token = data.get("access_token")
    folder_id = data.get("folder_id")
    
    if not access_token or not folder_id:
        return jsonify({"error": "Missing parameters"}), 400

    # הפעלת הפונקציה ברקע כדי שה-API יחזיר תשובה מיד
    threading.Thread(target=process_images_background, args=(access_token, folder_id)).start()
    
    return jsonify({"status": "started", "folder_id": folder_id})

# נתיב לבדיקת הסטטוס על ידי ה-PHP או ה-JS
@app.route('/status/<folder_id>', methods=['GET'])
def get_status(folder_id):
    if request.headers.get("X-API-KEY") != API_KEY:
        return jsonify({"error": "Unauthorized"}), 401
        
    status = jobs_status.get(folder_id, {"status": "not_found"})
    return jsonify(status)

if __name__ == '__main__':
    port = int(os.environ.get('PORT', 5000))
    app.run(host='0.0.0.0', port=port)
