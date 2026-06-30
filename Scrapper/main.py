import os
import sys
from providers.laroza_provider import LarozaProvider
from providers.esk_provider import EskProvider
from providers.egydead_provider import EgyDeadProvider
from providers.krmzy_provider import KrmzyProvider
from providers.faselhd_provider import FaselhdProvider
from providers.shahid4u_provider import Shahid4uProvider
from providers.wecima_provider import WecimaProvider
from providers.arabseed_provider import ArabseedProvider
from providers.bristeg_provider import BristegProvider
from providers.esheaq_provider import EsheaqProvider
from providers.shadwo_provider import ShadwoProvider
from providers.mycima_provider import MyCimaProvider
from providers.topcinema_provider import TopCinemaProvider
from providers.tuktuk_provider import TukTukProvider
from utils.downloader import save_data, sanitize_filename

# قائمة المزودات: (اسم العرض, الكلاس) — بدون إنشاء instances مسبقاً
PROVIDER_REGISTRY = [
    ("Laroza",      LarozaProvider),
    ("Esk",         EskProvider),
    ("EgyDead",     EgyDeadProvider),
    ("Krmzy",       KrmzyProvider),
    ("FaselHD",     FaselhdProvider),
    ("Shahid4u",    Shahid4uProvider),
    ("Wecima",      WecimaProvider),
    ("Arabseed",    ArabseedProvider),
    ("Bristeg",     BristegProvider),
    ("Esheaq",      EsheaqProvider),
    ("Shadwo Pro",  ShadwoProvider),
    ("MY Cima",  MyCimaProvider),
    ("Top Cinema",  TopCinemaProvider),
    ("TukTuk Cinema",  TukTukProvider),
]

def main():
    print("\n" + "="*70)
    print("🎥 نظام استخراج شامل - يدعم 14 مزودات")
    print("="*70)

    query = input("🔍 أدخل اسم العمل: ").strip()
    if not query:
        print("❌ لم تدخل شيئاً")
        return

    print("\n📋 المزودات المتاحة:")
    for i, (name, _) in enumerate(PROVIDER_REGISTRY, 1):
        print(f"  {i}. {name}")

    try:
        choice = int(input(f"\n✨ اختر رقم المزود (1-{len(PROVIDER_REGISTRY)}): ")) - 1
        if choice < 0 or choice >= len(PROVIDER_REGISTRY):
            print("❌ اختيار غير صحيح")
            return
    except ValueError:
        print("❌ أدخل رقماً")
        return

    provider_name, ProviderClass = PROVIDER_REGISTRY[choice]
    print(f"\n⏳ جاري الاتصال بـ {provider_name}...")
    provider = ProviderClass()   # ← الاتصال الفعلي يحدث هنا فقط

    print(f"\n🔍 جاري البحث في {provider.name}...")

    try:
        results = provider.search(query)
    except Exception as e:
        print(f"❌ خطأ في البحث: {str(e)[:100]}")
        return

    if not results:
        print("❌ لم يتم العثور على نتائج")
        return

    print("\n📌 نتائج البحث:")
    for i, r in enumerate(results, 1):
        print(f"  {i}. {r['title']} ({r.get('type', '')})")

    try:
        sel = int(input(f"\nاختر رقم العمل (1-{len(results)}): ")) - 1
        if sel < 0 or sel >= len(results):
            print("❌ اختيار غير صحيح")
            return
    except ValueError:
        print("❌ أدخل رقماً")
        return

    selected = results[sel]
    print(f"\n📄 جاري استخراج بيانات {selected['title']}...")

    try:
        data = provider.extract_all(selected['url'])
    except Exception as e:
        print(f"❌ فشل الاستخراج: {str(e)[:200]}")
        return

    data['selected_title'] = selected['title']
    data['provider'] = provider.name

    # تنظيف اسم المجلد
    folder_name = f"{data['name']} - {provider.name}"
    folder_name = sanitize_filename(folder_name)

    save_data(data, folder_name)

    print(f"\n✅ تم الحفظ في مجلد: {folder_name}")

    total_eps = sum(len(eps) for eps in data.get('seasons', {}).values())
    total_servers = sum(
        len(ep.get('servers', []))
        for season in data.get('seasons', {}).values()
        for ep in season
    )
    print("\n📊 ملخص:")
    print(f"   - الاسم: {data['name']}")
    print(f"   - المواسم: {len(data.get('seasons', {}))}")
    print(f"   - الحلقات: {total_eps}")
    print(f"   - السيرفرات: {total_servers}")

if __name__ == "__main__":
    # تثبيت المكتبات المطلوبة
    try:
        import cloudscraper
        import bs4
        import chardet
    except ImportError:
        print("📦 جاري تثبيت المكتبات المطلوبة...")
        os.system(f"{sys.executable} -m pip install -r requirements.txt")
    main()