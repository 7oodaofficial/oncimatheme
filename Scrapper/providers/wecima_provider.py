# wecima_provider.py
import re
import base64
from urllib.parse import urljoin, urlparse
from typing import List, Dict, Optional
from .base_provider import BaseProvider
from bs4 import BeautifulSoup
from utils.unpacker import decode_base64_url


class WecimaProvider(BaseProvider):
    name = "We Cima"
    domains = ["https://wecima.ac"]
    base_url = "https://wecima.ac"
    
    def __init__(self):
        super().__init__()
        self.headers.update({
            'User-Agent': 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36',
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language': 'ar-EG,ar;q=0.9,en-US;q=0.8,en;q=0.7',
            'Referer': self.base_url + '/'
        })
        self.post_headers = self.headers.copy()
        self.post_headers.update({
            'Accept': 'application/json, text/javascript, */*; q=0.01',
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        })
        self.cf_clearance = None
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
                if 'cf_clearance' in self.session.cookies:
                    self.cf_clearance = self.session.cookies.get('cf_clearance')
                self.resolved_base = base
                return base
            except Exception:
                continue
        return self.domains[0]
    
    def _get_base_url(self) -> str:
        if self.resolved_base:
            return self.resolved_base
        return self.base_url
    
    def _get_headers(self, is_post: bool = False) -> Dict[str, str]:
        headers = self.post_headers.copy() if is_post else self.headers.copy()
        if self.cf_clearance:
            headers['Cookie'] = f"cf_clearance={self.cf_clearance}"
        return headers
    
    def _smart_get(self, url: str, referer: Optional[str] = None, timeout: int = 20) -> Optional[BeautifulSoup]:
        headers = self._get_headers(is_post=False)
        if referer:
            headers['Referer'] = referer
        else:
            headers['Referer'] = self._get_base_url() + '/'
        
        for attempt in range(3):
            try:
                resp = self.session.get(url, headers=headers, timeout=timeout, allow_redirects=True)
                if 'cf_clearance' in self.session.cookies:
                    self.cf_clearance = self.session.cookies.get('cf_clearance')
                if resp.status_code == 200:
                    soup = BeautifulSoup(resp.text, 'lxml')
                    if self._is_cloudflare_challenge(soup):
                        from utils.cloudflare import solve_cloudflare_with_selenium
                        html, cookies = solve_cloudflare_with_selenium(url)
                        self.cookies.update(cookies)
                        if 'cf_clearance' in cookies:
                            self.cf_clearance = cookies['cf_clearance']
                        resp = self.session.get(url, headers=headers, cookies=self.cookies, timeout=timeout)
                        if resp.status_code == 200:
                            return BeautifulSoup(resp.text, 'lxml')
                    else:
                        return soup
            except Exception:
                if attempt == 0:
                    self.cf_clearance = None
                    headers = self._get_headers(is_post=False)
                continue
        return None
    
    def _smart_post(self, url: str, data: Dict[str, str], referer: Optional[str] = None, timeout: int = 20) -> Optional[str]:
        headers = self._get_headers(is_post=True)
        if referer:
            headers['Referer'] = referer
        else:
            headers['Referer'] = self._get_base_url() + '/'
        
        for attempt in range(3):
            try:
                resp = self.session.post(url, data=data, headers=headers, timeout=timeout, allow_redirects=True)
                if 'cf_clearance' in self.session.cookies:
                    self.cf_clearance = self.session.cookies.get('cf_clearance')
                if resp.status_code == 200:
                    return resp.text
            except Exception:
                if attempt == 0:
                    self.cf_clearance = None
                    headers = self._get_headers(is_post=True)
                continue
        return None
    
    def _is_cloudflare_challenge(self, soup: BeautifulSoup) -> bool:
        title = soup.title.string if soup.title else ''
        html = str(soup)
        return ('Just a moment' in title or
                'Attention Required' in title or
                'cf-turnstile' in html or
                'challenge-platform' in html) and 'Grid--WecimaPosts' not in html
    
    def _fix_url(self, url: str) -> str:
        if not url:
            return ''
        if url.startswith('//'):
            return 'https:' + url
        if url.startswith('/'):
            return urljoin(self._get_base_url(), url)
        return url
    
    def _decode_wecima_url(self, encoded: str) -> Optional[str]:
        if not encoded:
            return None
        try:
            cleaned = encoded.replace('+', '').strip()
            if not cleaned.startswith('aHR0c'):
                cleaned = 'aHR0c' + cleaned
            decoded = base64.b64decode(cleaned).decode('utf-8')
            return decoded
        except Exception:
            return None
    
    def _extract_poster_from_style(self, element) -> Optional[str]:
        if not element:
            return None
        style = element.get('data-lazy-style') or element.get('style', '')
        match = re.search(r'url\((.*?)\)', style)
        if match:
            return match.group(1).strip('\'" ')
        return None
    
    def _extract_numbers(self, text: Optional[str]) -> Optional[int]:
        if not text:
            return None
        match = re.search(r'\d+', text)
        return int(match.group()) if match else None
    
    def _to_search_result(self, element) -> Optional[Dict]:
        title_element = element.select_one('h2, strong')
        if not title_element:
            return None
        title = title_element.get_text(strip=True)
        anchor = element.select_one('a')
        if not anchor:
            return None
        href = anchor.get('href')
        if not href:
            return None
        
        poster = self._extract_poster_from_style(element.select_one('span.BG--GridItem'))
        is_tv = '/series/' in href or element.select_one('.EpisodesList') is not None
        type_ = 'مسلسل' if is_tv else 'فيلم'
        
        return {
            'title': title,
            'url': urljoin(self._get_base_url(), href),
            'poster': poster,
            'type': type_
        }
    
    def search(self, query: str) -> List[Dict]:
        base = self._get_base_url()
        search_url = f"{base}/search"
        response_text = self._smart_post(search_url, data={'q': query}, referer=base)
        if not response_text:
            return []
        
        try:
            import json
            data = json.loads(response_text)
            results = data.get('results', [])
            search_results = []
            for item in results:
                if item.get('istv') == 2:
                    continue
                title = item.get('title')
                slug = item.get('slug')
                if not title or not slug:
                    continue
                type_ = 'مسلسل' if item.get('istv') == 1 else 'فيلم'
                prefix = '/series/' if item.get('istv') == 1 else '/watch/'
                item_url = f"{base}{prefix}{slug}"
                search_results.append({
                    'title': title,
                    'url': item_url,
                    'poster': item.get('image'),
                    'year': item.get('year'),
                    'type': type_
                })
            return search_results
        except Exception:
            return []
    
    def get_details(self, url: str) -> Dict:
        soup = self._smart_get(url)
        if not soup:
            return {'name': '', 'story': '', 'poster': '', 'year': '', 'genres': []}
        
        title = soup.select_one('div.Title--Content--Single-begin h1')
        name = title.get_text(strip=True) if title else ''
        if not name:
            meta_title = soup.select_one("meta[property='og:title']")
            if meta_title:
                name = meta_title.get('content', '').strip()
        
        poster = None
        meta_poster = soup.select_one("meta[property='og:image']")
        if meta_poster:
            poster = meta_poster.get('content')
        if not poster:
            poster = self._extract_poster_from_style(soup.select_one('wecima.separated--top'))
        
        story = soup.select_one('div.StoryMovieContent')
        story = story.get_text(strip=True) if story else ''
        
        year = None
        genres = []
        for li in soup.select('ul.Terms--Content--Single-begin li'):
            span = li.select_one('span')
            if not span:
                continue
            key = span.get_text(strip=True)
            if 'السنة' in key:
                p = li.select_one('p')
                if p:
                    year_text = p.get_text(strip=True)
                    year = self._extract_numbers(year_text)
            elif 'النوع' in key:
                for a in li.select('p a'):
                    genres.append(a.get_text(strip=True))
        
        return {
            'name': name,
            'story': story,
            'poster': poster or '',
            'year': str(year) if year else '',
            'genres': genres
        }
    
    def get_episodes(self, url: str) -> Dict[str, List[Dict]]:
        soup = self._smart_get(url)
        if not soup:
            return {'Season 01': []}
        
        is_series = '/series/' in url or soup.select_one('.List--Seasons--Episodes') is not None
        
        if not is_series:
            breadcrumb = soup.select_one('.Breadcrumb--UX a[href*="/series/"]')
            if breadcrumb:
                series_url = breadcrumb.get('href')
                if series_url:
                    return self.get_episodes(urljoin(self._get_base_url(), series_url))
            return {'Film': [{'number': '01', 'title': 'فيلم', 'url': url}]}
        
        episodes = []
        base = self._get_base_url()
        season_elements = soup.select('div.List--Seasons--Episodes a.SeasonsEpisodes')
        
        if not season_elements:
            for a in soup.select('.EpisodesList.Full--Width a'):
                ep_href = a.get('href')
                if not ep_href:
                    continue
                ep_title = a.select_one('episodetitle')
                ep_name = ep_title.get_text(strip=True) if ep_title else 'حلقة'
                ep_num = self._extract_numbers(ep_name)
                episodes.append({
                    'number': str(ep_num).zfill(2) if ep_num else '01',
                    'title': ep_name,
                    'url': urljoin(base, ep_href)
                })
            episodes = self._deduplicate_episodes(episodes)
            # ترتيب الحلقات تصاعدياً
            episodes.sort(key=lambda x: int(x['number']) if x['number'].isdigit() else 0)
            return {'Season 01': episodes}
        
        # معالجة المواسم
        season_list = []  # لتخزين (season_num, episodes)
        for season_el in season_elements:
            season_text = season_el.get_text(strip=True)
            season_num = self._extract_numbers(season_text)
            if not season_num:
                continue
            data_id = season_el.get('data-id')
            data_season = season_el.get('data-season')
            if not data_id or not data_season:
                continue
            
            ajax_url = f"{base}/ajax/Episode"
            response_text = self._smart_post(ajax_url, data={'post_id': data_id, 'season': data_season}, referer=url)
            if response_text:
                season_soup = BeautifulSoup(response_text, 'lxml')
                season_eps = []
                for a in season_soup.select('a.hoverable.activable'):
                    ep_href = a.get('href')
                    if not ep_href:
                        continue
                    ep_title = a.select_one('episodetitle')
                    ep_name = ep_title.get_text(strip=True) if ep_title else 'حلقة'
                    ep_num = self._extract_numbers(ep_name)
                    season_eps.append({
                        'number': str(ep_num).zfill(2) if ep_num else '01',
                        'title': f"الموسم {season_num} {ep_name}",
                        'url': urljoin(base, ep_href)
                    })
                if season_eps:
                    # ترتيب حلقات هذا الموسم
                    season_eps.sort(key=lambda x: int(x['number']) if x['number'].isdigit() else 0)
                    season_list.append((season_num, season_eps))
        
        # ترتيب المواسم حسب الرقم
        season_list.sort(key=lambda x: x[0])
        
        # بناء القاموس النهائي
        seasons_dict = {}
        for season_num, eps in season_list:
            season_key = f"Season {str(season_num).zfill(2)}"
            seasons_dict[season_key] = eps
        
        return seasons_dict if seasons_dict else {'Season 01': []}
    
    def _deduplicate_episodes(self, episodes: List[Dict]) -> List[Dict]:
        seen = set()
        unique = []
        for ep in episodes:
            if ep['url'] not in seen:
                seen.add(ep['url'])
                unique.append(ep)
        return unique
    
    def get_servers(self, episode_url: str) -> List[Dict]:
        soup = self._smart_get(episode_url)
        if not soup:
            return []
        
        servers = []
        
        for btn in soup.select('ul.WatchServersList li btn'):
            encoded_url = btn.get('data-url')
            if encoded_url:
                decoded = self._decode_wecima_url(encoded_url)
                if decoded and decoded.startswith('http'):
                    servers.append({
                        'name': 'سيرفر',
                        'type': 'streaming',
                        'embed_url': decoded
                    })
        
        for download_btn in soup.select('.openLinkDown'):
            encoded_url = download_btn.get('data-href')
            if encoded_url:
                decoded = self._decode_wecima_url(encoded_url)
                if decoded and decoded.startswith('http'):
                    quality = download_btn.select_one('resolution')
                    quality_text = quality.get_text(strip=True) if quality else ''
                    name = f"تحميل {quality_text}" if quality_text else 'تحميل'
                    servers.append({
                        'name': name,
                        'type': 'download',
                        'embed_url': decoded
                    })
        
        return servers
    
    def extract_all(self, url: str) -> Dict:
        details = self.get_details(url)
        seasons = self.get_episodes(url)
        
        total = sum(len(eps) for eps in seasons.values())
        print(f"   📺 تم العثور على {len(seasons)} موسم و {total} حلقة")
        
        for season_name, episodes in seasons.items():
            for idx, ep in enumerate(episodes, 1):
                print(f"   ⏳ {season_name} - حلقة {ep.get('number', idx)}: جلب السيرفرات...")
                servers = self.get_servers(ep['url'])
                ep['servers'] = servers
        
        details['seasons'] = seasons
        details['provider'] = self.name
        return details