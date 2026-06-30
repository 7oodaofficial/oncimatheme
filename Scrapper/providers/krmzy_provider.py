# krmzy_provider.py
import re
import json
from urllib.parse import urljoin, urlparse, quote
from typing import List, Dict, Optional, Any
from .base_provider import BaseProvider
from bs4 import BeautifulSoup
from utils.unpacker import unpack_packer


class KrmzyProvider(BaseProvider):
    name = "قرمزي"
    domains = ["https://krmzi.org"]
    base_url = "https://krmzi.org"
    
    def __init__(self):
        super().__init__()
        self.headers.update({
            'User-Agent': 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36',
            'Accept-Language': 'ar-EG,ar;q=0.9,en-US;q=0.8,en;q=0.7',
            'Referer': self.base_url + '/'
        })
        self.resolved_base = None
    
    def _resolve_domain(self) -> str:
        if not self.domains:
            return self.base_url
        for domain in self.domains:
            try:
                resp = self.session.get(domain, timeout=15, allow_redirects=True)
                final_url = resp.url
                parsed = urlparse(final_url)
                base = f"{parsed.scheme}://{parsed.netloc}"
                self.resolved_base = base
                return base
            except Exception:
                continue
        return self.domains[0]
    
    def _get_base_url(self) -> str:
        if self.resolved_base:
            return self.resolved_base
        return self.base_url
    
    def _to_absolute(self, url: str) -> str:
        if not url:
            return ''
        if url.startswith('http'):
            return url
        if url.startswith('//'):
            return 'https:' + url
        return urljoin(self._get_base_url(), url)
    
    def _get_headers(self, referer: Optional[str] = None) -> Dict[str, str]:
        headers = self.headers.copy()
        if referer:
            headers['Referer'] = referer
        else:
            headers['Referer'] = self._get_base_url() + '/'
        return headers
    
    def _smart_get(self, url: str, referer: Optional[str] = None, timeout: int = 20) -> Optional[BeautifulSoup]:
        headers = self._get_headers(referer=referer)
        
        for attempt in range(3):
            try:
                resp = self.session.get(url, headers=headers, timeout=timeout, allow_redirects=True)
                if resp.status_code == 200:
                    soup = BeautifulSoup(resp.text, 'lxml')
                    if self._is_cloudflare_challenge(soup):
                        from utils.cloudflare import solve_cloudflare_with_selenium
                        html, cookies = solve_cloudflare_with_selenium(url)
                        self.cookies.update(cookies)
                        resp = self.session.get(url, headers=headers, cookies=self.cookies, timeout=timeout)
                        if resp.status_code == 200:
                            return BeautifulSoup(resp.text, 'lxml')
                    else:
                        return soup
            except Exception:
                continue
        return None
    
    def _smart_get_raw(self, url: str, referer: Optional[str] = None, timeout: int = 20):
        headers = self._get_headers(referer=referer)
        for attempt in range(3):
            try:
                resp = self.session.get(url, headers=headers, timeout=timeout, allow_redirects=True)
                if resp.status_code == 200:
                    return resp
            except Exception:
                continue
        return None
    
    def _is_cloudflare_challenge(self, soup: BeautifulSoup) -> bool:
        title = soup.title.string if soup.title else ''
        html = str(soup)
        return ('Just a moment' in title or
                'Attention Required' in title or
                'cf-turnstile' in html or
                'challenge-platform' in html)
    
    def _extract_numbers(self, text: Optional[str]) -> Optional[int]:
        if not text:
            return None
        match = re.search(r'\d+', text)
        return int(match.group()) if match else None
    
    def _to_search_response(self, element) -> Optional[Dict]:
        link = element.select_one('a')
        if not link:
            return None
        href = link.get('href')
        if not href:
            return None
        href = self._to_absolute(href)
        
        title_elem = link.select_one('div.title')
        title = title_elem.get_text(strip=True) if title_elem else link.get('title', '')
        if not title:
            title = link.get_text(strip=True)
        
        poster = None
        img_div = link.select_one('div.imgSer, div.imgBg')
        if img_div:
            style = img_div.get('style', '')
            match = re.search(r'url\([\'"]?(.*?)[\'"]?\)', style)
            if match:
                poster = self._to_absolute(match.group(1))
        
        return {
            'title': title,
            'url': href,
            'poster': poster or '',
            'type': 'مسلسل'  # هذا الموقع متخصص في المسلسلات
        }
    
    def search(self, query: str) -> List[Dict]:
        encoded = quote(query.strip())
        # الصفحة الأولى
        search_url = f"{self._get_base_url()}/?s={encoded}"
        soup = self._smart_get(search_url)
        results = []
        if soup:
            for article in soup.select('div.block-post'):
                result = self._to_search_response(article)
                if result:
                    results.append(result)
        # إذا لم تظهر نتائج، نحاول البحث في صفحة البحث المخصصة
        if not results:
            search_url = f"{self._get_base_url()}/search/{encoded}/"
            soup = self._smart_get(search_url)
            if soup:
                for article in soup.select('div.block-post'):
                    result = self._to_search_response(article)
                    if result:
                        results.append(result)
        return results
    
    def get_details(self, url: str) -> Dict:
        soup = self._smart_get(url)
        if not soup:
            return {'name': '', 'story': '', 'poster': '', 'year': '', 'genres': []}
        
        # إذا كانت الصفحة تحتوي على رابط المسلسل الرئيسي (مثل صفحة حلقة)
        series_link = soup.select_one('div.singleSeries div.info h1 a')
        if series_link:
            series_url = series_link.get('href')
            if series_url:
                return self.get_details(self._to_absolute(series_url))
        
        title_elem = soup.select_one('div.info h1')
        name = title_elem.get_text(strip=True) if title_elem else ''
        
        poster = None
        cover = soup.select_one('div.cover div.img')
        if cover:
            style = cover.get('style', '')
            match = re.search(r'url\([\'"]?(.*?)[\'"]?\)', style)
            if match:
                poster = self._to_absolute(match.group(1))
        
        story_elem = soup.select_one('div.story')
        story = story_elem.get_text(strip=True) if story_elem else ''
        
        return {
            'name': name,
            'story': story,
            'poster': poster or '',
            'year': '',
            'genres': []
        }
    
    def get_episodes(self, url: str) -> Dict[str, List[Dict]]:
        soup = self._smart_get(url)
        if not soup:
            return {'Season 01': []}
        
        # إذا كانت صفحة حلقة، نحصل على رابط المسلسل الرئيسي
        series_link = soup.select_one('div.singleSeries div.info h1 a')
        if series_link:
            series_url = series_link.get('href')
            if series_url:
                return self.get_episodes(self._to_absolute(series_url))
        
        # التحقق من أنه فيلم
        if '/movies/' in url:
            return {'Film': [{'number': '01', 'title': 'فيلم', 'url': url}]}
        
        # استخراج الحلقات
        episodes = []
        for article in soup.select('article.postEp'):
            link = article.select_one('a')
            if not link:
                continue
            ep_url = link.get('href')
            if not ep_url:
                continue
            ep_url = self._to_absolute(ep_url)
            
            title_elem = link.select_one('div.title')
            ep_title = title_elem.get_text(strip=True) if title_elem else ''
            
            num_elem = link.select_one('div.episodeNum span:last-child')
            ep_num = self._extract_numbers(num_elem.get_text(strip=True)) if num_elem else None
            if not ep_num:
                ep_num = 1
            
            # صورة الحلقة
            img_div = link.select_one('div.imgSer')
            poster = None
            if img_div:
                style = img_div.get('style', '')
                match = re.search(r'url\([\'"]?(.*?)[\'"]?\)', style)
                if match:
                    poster = self._to_absolute(match.group(1))
            
            episodes.append({
                'number': str(ep_num).zfill(2),
                'title': ep_title or f'حلقة {ep_num}',
                'url': ep_url,
                'poster': poster or ''
            })
        
        # ترتيب تصاعدي
        episodes.sort(key=lambda x: int(x['number']))
        
        if not episodes:
            return {'Season 01': []}
        
        # تجميع في موسم واحد (الموقع لا يدعم مواسم متعددة صراحة)
        return {'Season 01': episodes}
    
    def get_servers(self, episode_url: str) -> List[Dict]:
        """
        استخراج سيرفرات المشاهدة من صفحة الحلقة.
        يحاكي منطق loadLinks في الكوتلن.
        """
        servers = []
        soup = self._smart_get(episode_url)
        if not soup:
            return []
        
        # البحث عن رابط extractor
        extractor_link = soup.select_one('a.fullscreen-clickable')
        if not extractor_link:
            return []
        extractor_url = extractor_link.get('href')
        if not extractor_url:
            return []
        extractor_url = self._to_absolute(extractor_url)
        
        # إذا كان الرابط مباشراً (m3u8 أو mp4)
        if extractor_url.endswith('.m3u8') or extractor_url.endswith('.mp4'):
            servers.append({
                'name': 'مباشر',
                'type': 'streaming',
                'embed_url': extractor_url
            })
            return servers
        
        # جلب صفحة extractor
        extractor_soup = self._smart_get(extractor_url, referer=episode_url)
        if not extractor_soup:
            return []
        
        # قائمة السيرفرات
        server_items = extractor_soup.select('ul.serversList li')
        if not server_items:
            return []
        
        main_page_host = self._get_base_url()
        
        def ensure_http(u: str) -> str:
            if not u:
                return ''
            if u.startswith('//'):
                return 'https:' + u
            if u.startswith('http'):
                return u
            return 'https://' + u
        
        def dailymotion_from_li(li) -> Optional[str]:
            a = li.select_one('code a')
            if a:
                return a.get('href')
            code = li.select_one('code')
            if code:
                return code.get_text(strip=True)
            return None
        
        for li in server_items:
            server_id = li.get('data-server') or li.get('data-server-id', '')
            server_type_raw = li.get('data-name') or li.get('data-type', '')
            server_type = server_type_raw.lower().strip()
            
            embed_url = None
            # معالجة أنواع السيرفرات المختلفة
            if server_type == 'youtube':
                embed_url = f"https://www.youtube.com/watch?v={server_id}"
            elif server_type == 'youtube_in':
                embed_url = f"https://www.youtube.com/embed/{server_id}"
            elif server_type == 'express':
                embed_url = server_id if server_id else None
            elif server_type == 'dailymotion':
                embed_url = dailymotion_from_li(li)
            elif server_type == 'facebook':
                embed_url = f"https://app.videas.fr/embed/media/{server_id}"
            elif server_type == 'estream':
                embed_url = f"https://arabveturk.com/embed-{server_id}.html"
            elif server_type in ('arab hd', 'arabhd', 'arab-hd'):
                embed_url = f"https://v.turkvearab.com/embed-{server_id}.html"
            elif server_type == 'box':
                embed_url = f"https://youdboox.com/embed-{server_id}.html"
            elif server_type == 'now':
                embed_url = f"https://extreamnow.org/embed-{server_id}.html"
            elif server_type == 'ok':
                embed_url = ensure_http(f"//ok.ru/videoembed/{server_id}")
            elif server_type in ('red hd', 'redhd', 'red-hd'):
                embed_url = f"https://iplayerhls.com/e/{server_id}"
            elif server_type in ('pro hd', 'prohd', 'pro-hd'):
                embed_url = f"https://ebtv.upns.live/#{server_id}"
            elif server_type == 'pro':
                embed_url = f"https://mdna.upns.online/#{server_id}"
            else:
                # Fallback: try href or data-src
                fallback_href = li.select_one('a')
                if fallback_href:
                    embed_url = fallback_href.get('href')
                if not embed_url:
                    embed_url = li.get('data-src')
                if not embed_url:
                    continue
            
            if not embed_url:
                continue
            
            embed_url = self._to_absolute(embed_url)
            
            # معالجة السيرفرات التي تحتاج فك تشفير (Packer)
            if server_type in ('arab hd', 'arabhd', 'arab-hd', 'estream'):
                try:
                    extracted_m3u8 = self._extract_link_from_obfuscated_page(embed_url, [main_page_host, 'https://newaat.com/'])
                    if extracted_m3u8:
                        # نضيف السيرفر مع الرابط المفكوك
                        servers.append({
                            'name': f"{server_type_raw} (مفكوك)",
                            'type': 'streaming',
                            'embed_url': extracted_m3u8
                        })
                        continue
                except Exception:
                    pass
                # إذا فشل الفك، نضيف الرابط الأصلي
                servers.append({
                    'name': server_type_raw,
                    'type': 'streaming',
                    'embed_url': embed_url
                })
            else:
                # السيرفرات العادية
                servers.append({
                    'name': server_type_raw,
                    'type': 'streaming',
                    'embed_url': embed_url
                })
        
        return servers
    
    def _extract_link_from_obfuscated_page(self, url: str, referers: List[str]) -> Optional[str]:
        """
        فك تشفير صفحة تحتوي على كود Packer (eval) واستخراج رابط الفيديو.
        """
        page_text = None
        for ref in referers:
            try:
                resp = self._smart_get_raw(url, referer=ref)
                if resp and 'eval(function' in resp.text:
                    page_text = resp.text
                    break
            except Exception:
                continue
        
        if not page_text:
            return None
        
        # استخدام unpack_packer من utils
        unpacked = unpack_packer(page_text)
        if not unpacked:
            return None
        
        # البحث عن file: "url"
        match = re.search(r'["\']?file["\']?\s*:\s*["\']([^"\']+)["\']', unpacked)
        if match:
            return match.group(1).replace('\\/', '/')
        return None
    
    def extract_all(self, url: str) -> Dict:
        details = self.get_details(url)
        seasons = self.get_episodes(url)
        
        season_count = len(seasons)
        print(f"   📺 جاري استخراج {season_count} موسم...")
        
        for season_name, episodes in seasons.items():
            for idx, ep in enumerate(episodes, 1):
                print(f"   ⏳ {season_name} - حلقة {ep.get('number', idx)}: جلب السيرفرات...")
                servers = self.get_servers(ep['url'])
                ep['servers'] = servers
        
        details['seasons'] = seasons
        details['provider'] = self.name
        return details