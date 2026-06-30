# downloader.py
import os
import json
import re
import requests
from pathlib import Path
from typing import Dict, Any

def sanitize_filename(name: str) -> str:
    """
    تنظيف اسم الملف من الأحرف غير المسموح بها في أنظمة التشغيل (خاصة Windows).
    """
    if not name:
        name = "unnamed"
    # إزالة أحرف التحكم (مثل tabs, newlines) والمسافات الزائدة
    name = re.sub(r'[\t\n\r]+', ' ', name)  # استبدال التبويب والأسطر الجديدة بمسافة
    name = ' '.join(name.split())  # إزالة المسافات الزائدة
    # استبدال الأحرف غير المسموح بها: \ / : * ? " < > |
    return re.sub(r'[<>:"/\\|?*]', '_', name).strip()

def download_poster(url: str, folder_path: str) -> str:
    """تحميل صورة البوستر وحفظها في المجلد."""
    if not url:
        return ''
    try:
        resp = requests.get(url, timeout=30)
        if resp.status_code == 200:
            content_type = resp.headers.get('Content-Type', '')
            ext = '.jpg'
            if 'png' in content_type:
                ext = '.png'
            elif 'webp' in content_type:
                ext = '.webp'
            poster_path = os.path.join(folder_path, f"poster{ext}")
            with open(poster_path, 'wb') as f:
                f.write(resp.content)
            return poster_path
    except Exception:
        pass
    return ''

def save_data(data: Dict[str, Any], folder_name: str):
    """حفظ البيانات في مجلد منظم."""
    folder_name = sanitize_filename(folder_name)
    os.makedirs(folder_name, exist_ok=True)

    # حفظ الصورة
    poster_url = data.get('poster', '')
    if poster_url:
        download_poster(poster_url, folder_name)

    # حفظ details.txt
    details_path = os.path.join(folder_name, 'details.txt')
    with open(details_path, 'w', encoding='utf-8') as f:
        f.write("="*60 + "\n")
        f.write("معلومات العمل\n")
        f.write("="*60 + "\n\n")
        f.write(f"الاسم: {data.get('name', 'غير معروف')}\n")
        f.write(f"المزود: {data.get('provider', 'غير معروف')}\n")
        f.write(f"السنة: {data.get('year', '')}\n")
        f.write(f"التصنيف: {', '.join(data.get('genres', []))}\n")
        f.write(f"القصة: {data.get('story', '')}\n\n")
        f.write("="*60 + "\n")
        f.write("المواسم والحلقات\n")
        f.write("="*60 + "\n\n")
        for season, episodes in data.get('seasons', {}).items():
            f.write(f"{season}\n")
            f.write("-"*40 + "\n")
            for ep in episodes:
                f.write(f"  حلقة {ep.get('number', '?')}: {ep.get('title', '')}\n")
                if ep.get('servers'):
                    f.write("    السيرفرات:\n")
                    for idx, sv in enumerate(ep['servers'], 1):
                        # استخدام embed_url إن وجد، وإلا استخدام url
                        link = sv.get('embed_url') or sv.get('url', '')
                        f.write(f"      {idx}. {sv.get('name', '')} - {link}\n")
                        if sv.get('direct_url'):
                            f.write(f"         مباشر: {sv['direct_url']}\n")
                f.write("\n")

    # حفظ data.json
    json_path = os.path.join(folder_name, 'data.json')
    with open(json_path, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=2, default=str)

    # حفظ الروابط المباشرة (إذا وجدت)
    direct_path = os.path.join(folder_name, 'direct_urls.txt')
    with open(direct_path, 'w', encoding='utf-8') as f:
        f.write("روابط المشاهدة المباشرة\n")
        f.write("="*60 + "\n\n")
        for season, episodes in data.get('seasons', {}).items():
            f.write(f"{season}\n")
            for ep in episodes:
                f.write(f"  حلقة {ep.get('number', '?')}: {ep.get('title', '')}\n")
                for sv in ep.get('servers', []):
                    if sv.get('direct_url'):
                        f.write(f"    {sv.get('name', '')}: {sv['direct_url']}\n")
                f.write("\n")