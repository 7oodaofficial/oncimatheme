import re
import base64
from typing import Optional

def unpack_packer(js: str) -> Optional[str]:
    """
    فك تشفير كود Packer المشابه لـ eval(function(p,a,c,k,e,d)...)
    يعيد النص المفكوك أو None إذا لم يجد مطابقة.
    """
    # البحث عن القالب الأساسي
    pattern = r'eval\s*\(\s*function\s*\(p,a,c,k,e,d\)\s*\{.*?\}\s*\(\s*([\s\S]*?)\s*\)\s*\)'
    match = re.search(pattern, js, re.DOTALL)
    if not match:
        return None

    params_str = match.group(1)
    # استخراج المعطيات: payload, radix, count, dictionary
    # عادةً تكون على شكل: 'payload', radix, count, 'dict'.split('|')
    # نبحث عن نمط: ('...', عدد, عدد, '...'.split('|'))
    param_pattern = r"['\"](.*?)['\"]\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*['\"](.*?)['\"]\.split\s*\(['\"]\|['\"]\)"
    param_match = re.search(param_pattern, params_str)
    if not param_match:
        return None

    payload_raw = param_match.group(1)
    radix = int(param_match.group(2))
    count = int(param_match.group(3))
    dict_str = param_match.group(4)
    dictionary = dict_str.split('|')

    # دالة تحويل عدد من أساس معين إلى سلسلة (عكس parseInt)
    def to_base(num: int, base: int) -> str:
        chars = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"
        if num == 0:
            return "0"
        res = []
        while num > 0:
            res.append(chars[num % base])
            num //= base
        return ''.join(reversed(res))

    # بناء خريطة الاستبدال
    replace_map = {}
    for i in range(count):
        if i < len(dictionary):
            key = to_base(i, radix)
            replace_map[key] = dictionary[i]

    # استبدال الرموز في النص
    def replacer(m):
        return replace_map.get(m.group(0), m.group(0))

    # نستخدم regex للبحث عن الكلمات (حروف وأرقام)
    unpacked = re.sub(r'\b\w+\b', replacer, payload_raw)
    # تنظيف الهروب
    unpacked = unpacked.replace('\\/', '/')
    return unpacked

def decode_base64_url(encoded: str) -> Optional[str]:
    """فك تشفير Base64 مع إضافة البادئة المفقودة إن لزم."""
    if not encoded:
        return None
    # إزالة المسافات والأحرف الغريبة
    cleaned = encoded.replace('+', '').strip()
    if not cleaned.startswith('aHR0c'):
        cleaned = 'aHR0c' + cleaned
    try:
        decoded = base64.b64decode(cleaned).decode('utf-8')
        return decoded
    except:
        return None