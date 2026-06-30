# tuktuk_provider.py - نسخة محسّنة مع إلغاء حساب العدد الكلي مسبقاً
import re
import base64
import json
from urllib.parse import urljoin, urlparse, quote, unquote
from typing import List, Dict, Optional, Any
from concurrent.futures import ThreadPoolExecutor, as_completed
from .base_provider import BaseProvider
from bs4 import BeautifulSoup


class TukTukProvider(BaseProvider):
    name = "TukTukcima"
    domains = ["https://tuktukhd.com"]
    base_url = "https://tuktukhd.com"
    
    # التخزين المؤقت للصفحات
    _cache = {}
    _cache_max_size = 50
    
    def __init__(self):
        super().__init__()
        self.headers.update({
            'User-Agent': 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36',
            'Accept-Language': 'ar-EG,ar;q=0.9,en-US;q=0.8,en;q=0.7',
            'Referer': self.base_url + '/'
        })
        self.resolved_base = None
        self.executor = ThreadPoolExecutor(max_workers=5)
    
    def _resolve_domain(self) -> str:
        if not self.domains:
            return self.base_url
        for domain in self.domains:
            try:
                resp = self.session.get(domain, timeout=10, allow_redirects=True)
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
    
    def _get_headers(self, referer: Optional[str] = None) -> Dict[str, str]:
        headers = self.headers.copy()
        if referer:
            headers['Referer'] = referer
        else:
            headers['Referer'] = self._get_base_url() + '/'
        return headers
    
    def _get_cached(self, url: str) -> Optional[BeautifulSoup]:
        if url in self._cache:
            return self._cache[url]
        return None
    
    def _set_cache(self, url: str, soup: BeautifulSoup):
        if len(self._cache) >= self._cache_max_size:
            oldest = next(iter(self._cache))
            del self._cache[oldest]
        self._cache[url] = soup
    
    def _smart_get(self, url: str, referer: Optional[str] = None, timeout: int = 15) -> Optional[BeautifulSoup]:
        cached = self._get_cached(url)
        if cached:
            return cached
        
        headers = self._get_headers(referer=referer)
        
        for attempt in range(2):
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
                            soup = BeautifulSoup(resp.text, 'lxml')
                            self._set_cache(url, soup)
                            return soup
                    else:
                        self._set_cache(url, soup)
                        return soup
            except Exception:
                continue
        return None
    
    def _smart_get_raw(self, url: str, referer: Optional[str] = None, headers: Optional[Dict] = None, timeout: int = 15):
        req_headers = self._get_headers(referer=referer)
        if headers:
            req_headers.update(headers)
        
        for attempt in range(2):
            try:
                resp = self.session.get(url, headers=req_headers, timeout=timeout, allow_redirects=True)
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
    
    def _fix_url(self, url: str) -> str:
        if not url:
            return ''
        if url.startswith('//'):
            return 'https:' + url
        if url.startswith('/'):
            return urljoin(self._get_base_url(), url)
        return url
    
    def _to_search_result(self, element) -> Optional[Dict]:
        link_tag = element.select_one('a')
        if not link_tag:
            return None
        
        title = element.select_one('.title')
        title_text = title.get_text(strip=True) if title else link_tag.get('title', '')
        
        href = link_tag.get('href')
        if not href:
            return None
        href = self._fix_url(href)
        
        img_tag = element.select_one('img')
        poster = ''
        if img_tag:
            poster = img_tag.get('data-src') or img_tag.get('src', '')
        
        is_movie = ('فيلم' not in title_text and 
                   'مسلسل' not in title_text and 
                   'حلقة' not in title_text and 
                   '/series/' not in href)
        
        if not is_movie:
            if 'فيلم' in title_text or 'movie' in title_text.lower():
                is_movie = True
            elif 'مسلسل' in title_text or 'series' in title_text.lower() or '/series/' in href:
                is_movie = False
            else:
                is_movie = True
        
        return {
            'title': title_text,
            'url': href,
            'poster': poster,
            'type': 'فيلم' if is_movie else 'مسلسل'
        }
    
    def search(self, query: str) -> List[Dict]:
        encoded = quote(query)
        search_url = f"{self._get_base_url()}/?s={encoded}"
        soup = self._smart_get(search_url)
        if not soup:
            return []
        
        results = []
        for element in soup.select('div.Block--Item, li.Small--Box'):
            result = self._to_search_result(element)
            if result:
                results.append(result)
        
        return self._merge_similar_results(results)
    
    def _merge_similar_results(self, results: List[Dict]) -> List[Dict]:
        grouped = {}
        for item in results:
            title = item['title']
            if 'فيلم' in title or 'فلم' in title or 'movie' in title.lower():
                grouped[title] = item
                continue
            
            normalized = re.sub(r'الحلقة\s*\d+', '', title)
            normalized = re.sub(r'episode\s*\d+', '', normalized, flags=re.IGNORECASE)
            normalized = re.sub(r'\d+$', '', normalized).strip()
            
            if normalized not in grouped:
                grouped[normalized] = item
        
        return list(grouped.values())
    
    def get_details(self, url: str) -> Dict:
        soup = self._smart_get(url)
        if not soup:
            return {'name': '', 'story': '', 'poster': '', 'year': '', 'genres': []}
        
        title_elem = soup.select_one('h1.post-title a') or soup.select_one('h1')
        full_title = title_elem.get_text(strip=True) if title_elem else ''
        name = re.sub(r'\s*(الحلقة\s*\d+|مترجم|مدبلج).*', '', full_title).strip()
        
        poster_elem = soup.select_one('.MainSingle .left .image img')
        poster = poster_elem.get('src') if poster_elem else ''
        if not poster:
            bg_elem = soup.select_one('.homepage__bg')
            if bg_elem:
                style = bg_elem.get('style', '')
                match = re.search(r'url\(([^)]+)\)', style)
                if match:
                    poster = match.group(1).strip('\'" ')
        
        story_elem = soup.select_one('.story p')
        story = story_elem.get_text(strip=True) if story_elem else ''
        
        year_elem = soup.select_one('.RightTaxContent a[href*="release-year"]')
        year = self._extract_numbers(year_elem.get_text(strip=True)) if year_elem else None
        
        score_elem = soup.select_one('.imdbS strong')
        score = float(score_elem.get_text(strip=True)) if score_elem else None
        
        return {
            'name': name,
            'story': story,
            'poster': poster,
            'year': str(year) if year else '',
            'genres': [],
            'score': score
        }
    
    def get_episodes(self, url: str) -> Dict[str, List[Dict]]:
        soup = self._smart_get(url)
        if not soup:
            return {'Season 01': []}
        
        is_series = soup.select_one('.allepcont, .allseasonss') is not None
        if not is_series:
            return {'Film': [{'number': '01', 'title': 'فيلم', 'url': url}]}
        
        base = self._get_base_url()
        episodes = []
        
        season_elements = soup.select('.allseasonss .Block--Item a')
        season_urls = []
        
        if season_elements:
            for season_el in season_elements:
                season_url = season_el.get('href')
                if season_url:
                    season_url = self._fix_url(season_url)
                    season_name = season_el.select_one('h3')
                    season_text = season_name.get_text(strip=True) if season_name else ''
                    season_num = self._extract_numbers(season_text) or 1
                    season_urls.append((season_num, season_url))
        else:
            season_urls.append((1, url))
        
        def fetch_season_episodes(season_num: int, season_url: str) -> List[Dict]:
            season_eps = []
            season_soup = self._smart_get(season_url)
            if not season_soup:
                return []
            
            for ep_link in season_soup.select('.allepcont a'):
                ep_url = ep_link.get('href')
                if not ep_url:
                    continue
                ep_url = self._fix_url(ep_url)
                
                ep_title_elem = ep_link.select_one('.ep-info h2')
                ep_title = ep_title_elem.get_text(strip=True) if ep_title_elem else ''
                
                ep_num_elem = ep_link.select_one('.epnum')
                ep_num = 1
                if ep_num_elem:
                    ep_num_text = ep_num_elem.get_text(strip=True)
                    ep_num = self._extract_numbers(ep_num_text) or 1
                
                ep_thumb = ep_link.select_one('img')
                poster = ''
                if ep_thumb:
                    poster = ep_thumb.get('data-src') or ep_thumb.get('src', '')
                
                season_eps.append({
                    'number': str(ep_num).zfill(2),
                    'title': f"الموسم {season_num} - {ep_title}" if season_num > 1 else ep_title,
                    'url': ep_url,
                    'season': season_num,
                    'episode': ep_num,
                    'poster': poster
                })
            return season_eps
        
        with ThreadPoolExecutor(max_workers=len(season_urls)) as executor:
            futures = {
                executor.submit(fetch_season_episodes, season_num, season_url): season_num
                for season_num, season_url in season_urls
            }
            for future in as_completed(futures):
                episodes.extend(future.result())
        
        episodes.sort(key=lambda x: (x.get('season', 1), int(x['number'])))
        
        seasons_dict = {}
        for ep in episodes:
            season_key = f"Season {str(ep.get('season', 1)).zfill(2)}"
            if season_key not in seasons_dict:
                seasons_dict[season_key] = []
            seasons_dict[season_key].append({
                'number': ep['number'],
                'title': ep['title'],
                'url': ep['url'],
                'poster': ep.get('poster', '')
            })
        
        return seasons_dict if seasons_dict else {'Season 01': []}
    
    def get_servers(self, episode_url: str) -> List[Dict]:
        servers = []
        try:
            soup = self._smart_get(episode_url)
            if not soup:
                return []
            
            iframe = soup.select_one('iframe#main-video-frame')
            if not iframe:
                return []
            
            crypt_data = iframe.get('data-crypt')
            if not crypt_data:
                return []
            
            try:
                player_url = base64.b64decode(crypt_data).decode('utf-8')
            except:
                return []
            
            if not player_url.startswith('http'):
                player_url = self._fix_url(player_url)
            
            base = self._get_base_url()
            player_headers = {
                'User-Agent': 'Mozilla/5.0 (Linux; Android 10) AppleWebKit/537.36 Chrome/120 Mobile Safari/537.36',
                'Referer': base
            }
            
            initial_resp = self._smart_get_raw(player_url, referer=base, headers=player_headers, timeout=10)
            if not initial_resp:
                return []
            
            xsrf_token = None
            if 'XSRF-TOKEN' in initial_resp.cookies:
                xsrf_token = unquote(initial_resp.cookies.get('XSRF-TOKEN'))
            
            version_match = re.search(r'"version":"([^"]+)"', initial_resp.text)
            version = version_match.group(1) if version_match else None
            
            if not xsrf_token or not version:
                return []
            
            inertia_headers = {
                'X-XSRF-TOKEN': xsrf_token,
                'X-Inertia': 'true',
                'X-Inertia-Version': version,
                'X-Inertia-Partial-Component': 'files/mirror/video',
                'X-Inertia-Partial-Data': 'streams',
                'X-Requested-With': 'XMLHttpRequest',
                'Referer': player_url,
                'Content-Type': 'application/json'
            }
            
            inertia_resp = self._smart_get_raw(player_url, referer=player_url, headers=inertia_headers, timeout=10)
            if not inertia_resp:
                return []
            
            try:
                data = json.loads(inertia_resp.text)
                streams = data.get('props', {}).get('streams', {}).get('data', [])
                for stream in streams:
                    for mirror in stream.get('mirrors', []):
                        link = mirror.get('link')
                        if link:
                            if link.startswith('//'):
                                link = 'https:' + link
                            servers.append({
                                'name': 'سيرفر',
                                'type': 'streaming',
                                'embed_url': link
                            })
            except json.JSONDecodeError:
                pass
            
        except Exception as e:
            print(f"Error extracting servers: {str(e)}")
        
        return servers
    
    def extract_all(self, url: str) -> Dict:
        """
        استخراج جميع البيانات مع دعم الفيلم والمسلسل.
        تم تعديل هذه الدالة لتجنب حساب عدد الحلقات الكلي مسبقاً،
        مما يقلل من زمن الانتظار.
        """
        details = self.get_details(url)
        seasons = self.get_episodes(url)
        
        # لا نحسب العدد الكلي للحلقات مسبقاً
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