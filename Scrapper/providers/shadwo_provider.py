import re
from urllib.parse import urljoin
from .base_provider import BaseProvider
from bs4 import BeautifulSoup


# patterns للبحث عن روابط الفيديو المباشرة في JavaScript
_VIDEO_PATTERNS = [
    # رابط http/https كامل ينتهي بـ mp4/m3u8/mkv
    r'["\']?(https?://[^"\'<>\s]+\.(?:mp4|m3u8|mkv)[^"\'<>\s]*)["\']?',
    # رابط بروتوكول نسبي //
    r'["\']?(//[^"\'<>\s]+\.(?:mp4|m3u8|mkv)[^"\'<>\s]*)["\']?',
    # file: "url"  أو  "file": "url"
    r'["\']?file["\']?\s*:\s*["\']([^"\']+)["\']',
    # src = "url.mp4" أو src: "url.mp4"
    r'\bsrc\s*[=:]\s*["\']([^"\']+\.(?:mp4|m3u8|mkv)[^"\']*)["\']',
]

MAX_SEARCH_PAGES = 1   # أقصى عدد صفحات بحث نجلبها


class ShadwoProvider(BaseProvider):
    name = "Shadwo Pro"
    domains = ["https://w.shadwo.pro"]
    MAX_EPISODES = 500

    # ──────────────────────────────────────────────────────────────
    # SEARCH  — يجلب كل صفحات البحث ويُزيل التكرار
    # ──────────────────────────────────────────────────────────────
    def search(self, query):
        """
        الموقع يعرض كل حلقة كنتيجة منفصلة؛ ولذلك:
        1. نجلب كل صفحات البحث (pagination) حتى لا تضيع نتائج.
        2. نجمع الحلقات بـ dedup_key حتى يظهر كل مسلسل مرة واحدة فقط.
        """
        seen = {}     # dedup_key → item
        next_url = f"{self.base_url}/?s={query.replace(' ', '+')}"

        for _ in range(MAX_SEARCH_PAGES):
            if not next_url:
                break
            html = self._fetch(next_url)
            if not html:
                break

            soup = BeautifulSoup(html, 'lxml')

            for li in soup.select("li.wp-block-post"):
                h2 = li.select_one("h2.wp-block-post-title a")
                if not h2:
                    continue
                title = h2.get_text(strip=True)
                href = h2.get('href', '')
                if not href:
                    continue

                full_url = urljoin(self.base_url, href)

                # مفتاح التجميع: نشيل رقم الحلقة (eXXX) من نهاية الرابط
                # مثال: .../gunesin-kizlari-s01e39/  →  .../gunesin-kizlari-s01
                dedup_key = re.sub(r'e\d+/?$', '', full_url)

                if dedup_key not in seen:
                    series_title = re.sub(r'\s*الحلقة\s*\d+', '', title).strip()
                    seen[dedup_key] = {
                        'title': series_title,
                        'url': full_url,
                        'type': 'مسلسل',
                    }

            # الصفحة التالية
            next_link = soup.select_one('a.wp-block-query-pagination-next')
            next_url = next_link.get('href') if next_link else None

        return list(seen.values())

    # ──────────────────────────────────────────────────────────────
    # DETAILS
    # ──────────────────────────────────────────────────────────────
    def get_details(self, url):
        html = self._fetch(url)
        if not html:
            return {'name': '', 'story': '', 'poster': '', 'year': '', 'genres': []}

        soup = BeautifulSoup(html, 'lxml')
        name = ''
        title_tag = soup.find('title')
        if title_tag:
            title_text = title_tag.get_text(strip=True)
            name = re.sub(r'\s*الحلقة\s*\d+', '', title_text).strip()

        return {'name': name, 'story': '', 'poster': '', 'year': '', 'genres': []}

    # ──────────────────────────────────────────────────────────────
    # EPISODES  — يبدأ من S01E01 ويتنقّل تلقائياً بين المواسم
    # ──────────────────────────────────────────────────────────────
    def get_episodes(self, url):
        """
        يستخرج الجزء الثابت من URL (قبل رقم الموسم والحلقة)، ثم يمشي:
          • على كل موسم بدءاً من S01، وداخله على كل حلقة بدءاً من E01.
          • عند انتهاء حلقات موسم (لا iframe) → ينتقل للموسم التالي.
          • عند عدم وجود E01 في الموسم الجديد → يتوقف نهائياً.

        مثال بنية URL المتوقعة:
            https://w.shadwo.pro/series-name-s01e39/
                                            ↑    ↑
                                          season  ep
        """
        # استخراج الجذر ورقم الموسم الأولي من URL المُمرَّر
        season_match = re.match(r'(.*?)(s)(\d+)(e)\d+/?$', url, re.IGNORECASE)
        if not season_match:
            # URL لا يحمل نمط sXXeYY → نعامله كحلقة منفردة
            return {'Season 01': [{'number': '01', 'title': 'حلقة 1', 'url': url}]}

        series_base = season_match.group(1)   # مثال: https://w.shadwo.pro/gunesin-kizlari-
        s_letter    = season_match.group(2)   # 's'  أو  'S'
        e_letter    = season_match.group(4)   # 'e'  أو  'E'

        seasons: dict[str, list] = {}

        for season_num in range(1, 100):          # حدّ أعلى غير واقعي للمواسم
            season_str = str(season_num).zfill(2)
            season_key = f"Season {season_str}"
            episodes   = []

            for episode_num in range(1, self.MAX_EPISODES + 1):
                ep_str = str(episode_num).zfill(2) if episode_num < 100 else str(episode_num)
                ep_url = f"{series_base}{s_letter}{season_str}{e_letter}{ep_str}/"

                html = self._fetch(ep_url)
                if not html:
                    # الصفحة غير موجودة إطلاقاً
                    if episode_num == 1:
                        # الحلقة الأولى من هذا الموسم غير موجودة → توقف كامل
                        return seasons if seasons else {
                            'Season 01': [{'number': '01', 'title': 'حلقة 1', 'url': url}]
                        }
                    break   # انتهت حلقات الموسم الحالي → جرّب الموسم التالي

                soup = BeautifulSoup(html, 'lxml')
                if not soup.select_one("div.video-con iframe"):
                    # الصفحة موجودة لكن لا iframe = انتهت الحلقات الفعلية
                    if episode_num == 1:
                        # الحلقة الأولى من الموسم الجديد بلا iframe → توقف
                        return seasons if seasons else {
                            'Season 01': [{'number': '01', 'title': 'حلقة 1', 'url': url}]
                        }
                    break

                episodes.append({
                    'number': ep_str,
                    'title': f"الحلقة {episode_num}",
                    'url': ep_url,
                })

            if episodes:
                seasons[season_key] = episodes
            else:
                # لا حلقات في هذا الموسم → توقف
                break

        return seasons if seasons else {
            'Season 01': [{'number': '01', 'title': 'حلقة 1', 'url': url}]
        }

    # ──────────────────────────────────────────────────────────────
    # SERVERS  — يكتشف السيرفرات الفعّالة من HTML، ويتجنّب الفارغة
    # ──────────────────────────────────────────────────────────────
    def get_servers(self, episode_url):
        """
        1. جلب HTML الحلقة مرة واحدة لاكتشاف أسماء السيرفرات الفعّالة.
        2. لكل سيرفر فعّال: جلب صفحة ?serv=N وقراءة iframe src.
        3. لـ MP4Plus (serv=2): محاولة استخراج الرابط المباشر.
        السيرفرات ذات الاسم الفارغ (serv=4 إلى 8 عادةً) تُهمَل تلقائياً.
        """
        servers = []
        clean_url = episode_url.rstrip('/')

        # ── الخطوة 1: اكتشاف السيرفرات الفعّالة ──
        base_html = self._fetch(episode_url)
        available_servers = {}   # {serv_id: name}

        if base_html:
            base_soup = BeautifulSoup(base_html, 'lxml')
            for a in base_soup.select("ul.aplr-menu a.aplr-link[href*='serv=']"):
                serv_match = re.search(r'serv=(\d+)', a.get('href', ''))
                if not serv_match:
                    continue
                serv_id = int(serv_match.group(1))
                name = a.get_text(strip=True)
                if name:   # اسم فارغ = سيرفر غير مفعّل
                    available_servers[serv_id] = name

        # ── الخطوة 2: جلب embed_url لكل سيرفر ──
        for serv_id, server_name in sorted(available_servers.items()):
            serv_url = f"{clean_url}/?serv={serv_id}"
            html = self._fetch(serv_url)
            if not html:
                continue

            soup = BeautifulSoup(html, 'lxml')
            iframe = soup.select_one("div.video-con iframe")
            if not iframe:
                continue

            src = iframe.get('src', '').strip()
            if not src:
                continue

            server_entry = {
                'name': server_name,
                'embed_url': src,
            }

            # ── الخطوة 3: رابط مباشر لـ MP4Plus ──
            if serv_id == 2:
                direct = self._extract_direct_url(src)
                if direct:
                    server_entry['direct_url'] = direct

            servers.append(server_entry)

        return servers

    # ──────────────────────────────────────────────────────────────
    # HELPERS
    # ──────────────────────────────────────────────────────────────
    def _extract_direct_url(self, iframe_src):
        """
        يستخرج الرابط المباشر (mp4/m3u8) من داخل صفحة iframe.
        يبحث في:
          1. <video src="...">
          2. <source src="...">
          3. JavaScript في الصفحة بأنماط شائعة
        """
        if not iframe_src:
            return None

        iframe_html = self._fetch(iframe_src)
        if not iframe_html:
            return None

        soup = BeautifulSoup(iframe_html, 'lxml')

        # 1. <video src>
        video = soup.find('video')
        if video and video.get('src'):
            return self._normalize_url(video['src'].strip(), iframe_src)

        # 2. <source src>
        source = soup.find('source')
        if source and source.get('src'):
            return self._normalize_url(source['src'].strip(), iframe_src)

        # 3. JavaScript
        for script in soup.find_all('script'):
            js_text = script.get_text()
            if not js_text.strip():
                continue
            for pattern in _VIDEO_PATTERNS:
                m = re.search(pattern, js_text, re.IGNORECASE)
                if m:
                    # نأخذ أول group غير فارغ
                    src = next((g for g in m.groups() if g), None)
                    if src:
                        src = src.strip().strip('"\'')
                        normalized = self._normalize_url(src, iframe_src)
                        # نتحقق إنه رابط منطقي
                        if normalized.startswith('http'):
                            return normalized

        return None

    @staticmethod
    def _normalize_url(src, base):
        """تحويل أي رابط نسبي إلى رابط مطلق."""
        if not src:
            return src
        if src.startswith('//'):
            return 'https:' + src
        if src.startswith('/'):
            return urljoin(base, src)
        return src