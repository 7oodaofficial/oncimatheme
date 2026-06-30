# topcinema_provider.py
import re
from urllib.parse import urljoin, urlparse, unquote
from typing import List, Dict, Optional
from .base_provider import BaseProvider
from bs4 import BeautifulSoup
from utils.unpacker import unpack_packer, decode_base64_url


class TopCinemaProvider(BaseProvider):
    name = "Top Cinema"
    domains = ["https://web8.topcinema.cam"]
    base_url = "https://web8.topcinema.cam"
    
    def __init__(self):
        super().__init__()
        self.headers.update({
            'User-Agent': 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36',
            'Accept-Language': 'ar-EG,ar;q=0.9,en-US;q=0.8,en;q=0.7',
            'Referer': self.base_url + '/'
        })
        self.post_headers = self.headers.copy()
        self.post_headers.update({
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'Accept': '*/*'
        })
        self.resolved_base = None
    
    def _resolve_domain(self) -> str:
        """تجاوز لحل النطاق مع حفظ النطاق النهائي"""
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
    
    def _get_headers(self, referer: Optional[str] = None, is_post: bool = False) -> Dict[str, str]:
        """إرجاع الهيدرات مع الـ Referer المناسب"""
        headers = self.post_headers.copy() if is_post else self.headers.copy()
        if referer:
            headers['Referer'] = referer
        else:
            headers['Referer'] = self._get_base_url() + '/'
        return headers
    
    def _smart_get(self, url: str, referer: Optional[str] = None, timeout: int = 20) -> Optional[BeautifulSoup]:
        """جلب الصفحة مع محاولة حل Cloudflare"""
        headers = self._get_headers(referer=referer, is_post=False)
        
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
    
    def _smart_post(self, url: str, data: Dict[str, str], referer: Optional[str] = None, timeout: int = 20) -> Optional[str]:
        """إرسال POST مع محاولة حل Cloudflare"""
        headers = self._get_headers(referer=referer, is_post=True)
        
        for attempt in range(3):
            try:
                resp = self.session.post(url, data=data, headers=headers, timeout=timeout, allow_redirects=True)
                if resp.status_code == 200:
                    return resp.text
            except Exception:
                continue
        return None
    
    def _is_cloudflare_challenge(self, soup: BeautifulSoup) -> bool:
        """التحقق من وجود تحدي Cloudflare"""
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
    
    def _unwrap_play_url(self, url: str) -> str:
        """فك تشفير روابط play.php"""
        if not url:
            return url
        if 'play.php?to=' in url:
            try:
                decoded = unquote(url.split('play.php?to=')[1]).strip()
                if decoded.startswith('http'):
                    return decoded
                elif decoded.startswith('//'):
                    return 'https:' + decoded
                else:
                    return 'https:' + decoded.lstrip(':')
            except Exception:
                return url
        return url
    
    def _get_base_from_url(self, url: str) -> str:
        """استخراج النطاق الأساسي من URL"""
        try:
            parsed = urlparse(url)
            return f"{parsed.scheme}://{parsed.netloc}"
        except Exception:
            return self._get_base_url()
    
    def _to_search_response(self, element) -> Optional[Dict]:
        """تحويل عنصر البحث إلى قاموس النتيجة"""
        link = element.select_one('a')
        if not link:
            return None
        href = link.get('href')
        if not href:
            return None
        title = link.get('title', '')
        if not title:
            title = link.get_text(strip=True)
        
        img = link.select_one('img')
        poster = ''
        if img:
            poster = img.get('data-src') or img.get('src', '')
        
        # تحديد النوع
        is_movie = 'فيلم' in title
        is_series = 'مسلسل' in title or '/series/' in href
        
        # فحص إضافي للتمييز
        if not is_movie and not is_series:
            if element.select_one('.number, .epnum') or '/series/' in href:
                is_series = True
            else:
                is_movie = True
        
        return {
            'title': title,
            'url': urljoin(self._get_base_url(), href),
            'poster': poster,
            'type': 'مسلسل' if is_series else 'فيلم'
        }
    
    def search(self, query: str) -> List[Dict]:
        """البحث عن الأفلام والمسلسلات"""
        base = self._get_base_url()
        search_url = f"{base}/search/?query={query}&type=all"
        soup = self._smart_get(search_url)
        if not soup:
            return []
        
        results = []
        for element in soup.select('.Posts--List .Small--Box'):
            result = self._to_search_response(element)
            if result:
                results.append(result)
        return results
    
    def get_details(self, url: str) -> Dict:
        """استخراج تفاصيل العمل"""
        soup = self._smart_get(url)
        if not soup:
            return {'name': '', 'story': '', 'poster': '', 'year': '', 'genres': []}
        
        # الاسم
        title_elem = soup.select_one('h1.post-title a') or soup.select_one('h1.post-title')
        name = title_elem.get_text(strip=True) if title_elem else ''
        
        # البوستر
        poster_elem = soup.select_one('.MainSingle .left .image img')
        poster = poster_elem.get('src') if poster_elem else ''
        
        # القصة
        story_elem = soup.select_one('.story p')
        story = story_elem.get_text(strip=True) if story_elem else ''
        
        # التصنيفات
        genres = []
        for a in soup.select('.RightTaxContent li:contains(نوع) a'):
            genres.append(a.get_text(strip=True))
        
        # السنة
        year = None
        year_elem = soup.select_one('.RightTaxContent li:contains(الصدور) a')
        if year_elem:
            year_text = year_elem.get_text(strip=True)
            year = self._extract_numbers(year_text)
        
        # التقييم
        score = None
        score_elem = soup.select_one('.imdbR span')
        if score_elem:
            try:
                score = float(score_elem.get_text(strip=True))
            except:
                pass
        
        return {
            'name': name,
            'story': story,
            'poster': poster,
            'year': str(year) if year else '',
            'genres': genres,
            'score': score
        }
    
    def get_episodes(self, url: str) -> Dict[str, List[Dict]]:
        """استخراج المواسم والحلقات للمسلسلات، ومعالجة الأفلام"""
        soup = self._smart_get(url)
        if not soup:
            return {'Season 01': []}
        
        # التحقق من أنها صفحة مسلسل
        is_tv_series = soup.select_one('section.tabs') is not None
        
        if not is_tv_series:
            # فيلم: نعيد حلقة واحدة مع بيانات المشاهدة والتحميل
            watch_url = url.rstrip('/') + '/watch/'
            download_url = url.rstrip('/') + '/download/'
            data = f"{watch_url}||{download_url}"
            return {'Film': [{
                'number': '01',
                'title': 'فيلم',
                'url': url,
                'data': data
            }]}
        
        # مسلسل: استخراج المواسم والحلقات
        episodes = []
        base = self._get_base_url()
        
        # جمع المواسم مع أرقامها
        season_elements = soup.select('section.allseasonss .Small--Box.Season a')
        season_list = []
        for season_el in season_elements:
            season_url = season_el.get('href')
            if not season_url:
                continue
            season_url = urljoin(base, season_url)
            # استخراج رقم الموسم
            season_num_elem = season_el.select_one('.epnum')
            season_num = 1
            if season_num_elem:
                season_text = season_num_elem.get_text(strip=True)
                season_num = self._extract_numbers(season_text) or 1
            season_list.append((season_num, season_url))
        
        # ترتيب المواسم تصاعدياً حسب الرقم
        season_list.sort(key=lambda x: x[0])
        
        for season_num, season_url in season_list:
            # جلب حلقات هذا الموسم
            season_soup = self._smart_get(season_url)
            if not season_soup:
                continue
            
            season_episodes = []
            for ep_link in season_soup.select('.allepcont .row > a'):
                ep_url = ep_link.get('href')
                if not ep_url:
                    continue
                ep_url = urljoin(base, ep_url)
                
                ep_title_elem = ep_link.select_one('h2')
                ep_title = ep_title_elem.get_text(strip=True) if ep_title_elem else ''
                
                ep_num_elem = ep_link.select_one('.epnum')
                ep_num = 1
                if ep_num_elem:
                    ep_num_text = ep_num_elem.get_text(strip=True)
                    ep_num = self._extract_numbers(ep_num_text) or 1
                
                # بيانات المشاهدة والتحميل
                data = f"{ep_url}/watch/||{ep_url}/download/"
                
                season_episodes.append({
                    'number': str(ep_num).zfill(2),
                    'title': f"الموسم {season_num} - {ep_title}",
                    'url': ep_url,
                    'data': data,
                    'season': season_num,
                    'episode': ep_num
                })
            
            # ترتيب الحلقات داخل الموسم تصاعدياً
            season_episodes.sort(key=lambda x: int(x['number']))
            episodes.extend(season_episodes)
        
        # إذا لم نجد مواسم عبر القائمة، نبحث عن حلقات مباشرة في الصفحة الحالية
        if not episodes:
            season_num = 1
            for ep_link in soup.select('.allepcont .row > a'):
                ep_url = ep_link.get('href')
                if not ep_url:
                    continue
                ep_url = urljoin(base, ep_url)
                
                ep_title_elem = ep_link.select_one('h2')
                ep_title = ep_title_elem.get_text(strip=True) if ep_title_elem else ''
                
                ep_num_elem = ep_link.select_one('.epnum')
                ep_num = 1
                if ep_num_elem:
                    ep_num_text = ep_num_elem.get_text(strip=True)
                    ep_num = self._extract_numbers(ep_num_text) or 1
                
                data = f"{ep_url}/watch/||{ep_url}/download/"
                episodes.append({
                    'number': str(ep_num).zfill(2),
                    'title': ep_title,
                    'url': ep_url,
                    'data': data,
                    'season': season_num,
                    'episode': ep_num
                })
            # ترتيب الحلقات
            episodes.sort(key=lambda x: int(x['number']))
        
        # تجميع المواسم
        seasons_dict = {}
        for ep in episodes:
            season_key = f"Season {str(ep.get('season', 1)).zfill(2)}"
            if season_key not in seasons_dict:
                seasons_dict[season_key] = []
            seasons_dict[season_key].append({
                'number': ep['number'],
                'title': ep['title'],
                'url': ep['url'],
                'data': ep.get('data', ep['url'])
            })
        
        # ترتيب المواسم تصاعدياً (المفاتيح)
        sorted_seasons = {}
        for key in sorted(seasons_dict.keys(), key=lambda x: int(x.split()[-1])):
            sorted_seasons[key] = seasons_dict[key]
        
        return sorted_seasons if sorted_seasons else {'Season 01': []}
    
    def get_servers(self, episode_data: str) -> List[Dict]:
        """
        استخراج روابط السيرفرات من بيانات الحلقة أو الفيلم.
        تتوقع البيانات إما:
        - رابط صفحة المشاهدة (للأفلام) أو
        - سلسلة بتنسيق "watch_url||download_url"
        """
        servers = []
        base = self._get_base_url()
        
        # تحليل البيانات
        if '||' in episode_data:
            watch_url, download_url = episode_data.split('||', 1)
        else:
            watch_url = episode_data
            download_url = None
        
        # 1. جلب سيرفرات المشاهدة
        if watch_url:
            try:
                watch_soup = self._smart_get(watch_url)
                if watch_soup:
                    # iframe مباشر
                    iframe = watch_soup.select_one('.player--iframe iframe')
                    if iframe:
                        src = iframe.get('src')
                        if src:
                            servers.append({
                                'name': 'مشاهدة مباشرة',
                                'type': 'streaming',
                                'embed_url': src
                            })
                    
                    # سيرفرات إضافية عبر AJAX
                    server_list = watch_soup.select('.watch--servers--list li.server--item')
                    if server_list:
                        # استخراج النطاق الأساسي لصفحة المشاهدة
                        watch_base = self._get_base_from_url(watch_url)
                        ajax_url = f"{watch_base}/wp-content/themes/movies2023/Ajaxat/Single/Server.php"
                        
                        for server in server_list:
                            server_id = server.get('data-id')
                            server_key = server.get('data-server')
                            if not server_id or not server_key:
                                continue
                            
                            try:
                                response_text = self._smart_post(
                                    ajax_url,
                                    data={'id': server_id, 'i': server_key},
                                    referer=watch_url
                                )
                                if response_text:
                                    server_soup = BeautifulSoup(response_text, 'lxml')
                                    iframe_src = server_soup.select_one('iframe')
                                    if iframe_src:
                                        src = iframe_src.get('src')
                                        if src:
                                            name = server.get_text(strip=True) or 'سيرفر'
                                            servers.append({
                                                'name': name,
                                                'type': 'streaming',
                                                'embed_url': src
                                            })
                            except Exception:
                                continue
            except Exception:
                pass
        
        # 2. جلب روابط التحميل
        if download_url:
            try:
                download_soup = self._smart_get(download_url)
                if download_soup:
                    for a in download_soup.select('a.downloadsLink'):
                        href = a.get('href')
                        if href:
                            name = a.get_text(strip=True) or 'تحميل'
                            servers.append({
                                'name': f"تحميل - {name}",
                                'type': 'download',
                                'embed_url': href
                            })
            except Exception:
                pass
        
        # 3. فك تشفير روابط play.php إن وجدت
        for server in servers:
            embed_url = server['embed_url']
            if 'play.php?to=' in embed_url:
                server['embed_url'] = self._unwrap_play_url(embed_url)
        
        return servers
    
    def _unpack_js(self, p: str, a: int, c: int, k: List[str]) -> str:
        """فك تشفير JavaScript Packer"""
        digits = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"
        
        def int_to_base(num: int, base: int) -> str:
            if num == 0:
                return "0"
            result = []
            while num > 0:
                result.append(digits[num % base])
                num //= base
            return ''.join(reversed(result))
        
        # بناء خريطة الاستبدال
        mapping = {}
        for i in range(min(c, len(k))):
            key = int_to_base(i, a)
            value = k[i] if i < len(k) else ''
            if value:
                mapping[key] = value
        
        # استبدال الرموز في النص
        pattern = re.compile(r'[0-9A-Za-z]+')
        def replacer(match):
            return mapping.get(match.group(0), match.group(0))
        
        return pattern.sub(replacer, p)
    
    def _extract_vidtube_links(self, url: str, referer: str) -> List[Dict]:
        """استخراج روابط من Vidtube باستخدام فك Packer"""
        try:
            resp = self.session.get(url, headers=self._get_headers(referer=referer))
            if resp.status_code != 200:
                return []
            
            response_text = resp.text
            
            # البحث عن كود Packer
            packer_pattern = r'eval\(function\(p,a,c,k,e,d\)\{.*?\}\(\s*([\'"])(.*?)\1\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*([\'"])(.*?)\5\.split'
            match = re.search(packer_pattern, response_text, re.DOTALL)
            
            if not match:
                return []
            
            try:
                p_raw = match.group(2)
                a = int(match.group(3))
                c = int(match.group(4))
                k_str = match.group(6)
                k_list = k_str.split('|') if k_str else []
                
                # فك التشفير
                unpacked = self._unpack_js(p_raw, a, c, k_list)
                
                # استخراج روابط الفيديو
                file_pattern = r'file\s*:\s*"(https?://[^"]+)"'
                label_pattern = r'label\s*:\s*"([^"]+)"'
                
                files = re.findall(file_pattern, unpacked)
                labels = re.findall(label_pattern, unpacked)
                
                results = []
                for idx, file_url in enumerate(files):
                    label = labels[idx] if idx < len(labels) else 'Auto'
                    results.append({
                        'url': file_url,
                        'name': f"Vidtube - {label}",
                        'quality': self._get_quality_from_label(label)
                    })
                
                return results
            except Exception:
                return []
        except Exception:
            return []
    
    def _get_quality_from_label(self, label: str) -> str:
        """استخراج الجودة من التسمية"""
        label_lower = label.lower()
        if '1080' in label_lower or 'full' in label_lower:
            return '1080p'
        elif '720' in label_lower:
            return '720p'
        elif '480' in label_lower:
            return '480p'
        elif '360' in label_lower:
            return '360p'
        return 'Auto'
    
    def extract_all(self, url: str) -> Dict:
        """استخراج جميع البيانات مع دعم الفيلم والمسلسل"""
        details = self.get_details(url)
        seasons = self.get_episodes(url)
        
        total = sum(len(eps) for eps in seasons.values())
        print(f"   📺 تم العثور على {len(seasons)} موسم و {total} حلقة")
        
        for season_name, episodes in seasons.items():
            for idx, ep in enumerate(episodes, 1):
                # استخدام البيانات المخزنة للتحميل (watch||download)
                data = ep.get('data', ep['url'])
                print(f"   ⏳ {season_name} - حلقة {ep.get('number', idx)}: جلب السيرفرات...")
                servers = self.get_servers(data)
                ep['servers'] = servers
        
        details['seasons'] = seasons
        details['provider'] = self.name
        return details