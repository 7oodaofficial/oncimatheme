# arabseed_provider.py
import re
import json
from urllib.parse import urljoin, urlparse, quote
from typing import List, Dict, Optional, Any
from .base_provider import BaseProvider
from bs4 import BeautifulSoup


class ArabseedProvider(BaseProvider):
    name = "Arabseed"
    domains = ["https://asd.pics"]
    base_url = "https://asd.pics"
    
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
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        })
        self.resolved_base = None
        self.csrf_token = None
    
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
                self.base_url = base
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
    
    def _get_headers(self, referer: Optional[str] = None, is_post: bool = False) -> Dict[str, str]:
        headers = self.post_headers.copy() if is_post else self.headers.copy()
        if referer:
            headers['Referer'] = referer
        else:
            headers['Referer'] = self._get_base_url() + '/'
        if self.csrf_token and is_post:
            headers['X-CSRF-TOKEN'] = self.csrf_token
        return headers
    
    def _smart_get(self, url: str, referer: Optional[str] = None, timeout: int = 20) -> Optional[BeautifulSoup]:
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
                        self._extract_csrf_token(resp.text)
                        return soup
            except Exception:
                continue
        return None
    
    def _smart_post(self, url: str, data: Dict[str, str], referer: Optional[str] = None, timeout: int = 20) -> Optional[str]:
        headers = self._get_headers(referer=referer, is_post=True)
        
        for attempt in range(3):
            try:
                resp = self.session.post(url, data=data, headers=headers, timeout=timeout, allow_redirects=True)
                if resp.status_code == 200:
                    self._extract_csrf_token(resp.text)
                    return resp.text
                elif resp.status_code == 403 and 'csrf' in resp.text.lower():
                    self.csrf_token = None
                    self._smart_get(self._get_base_url())
                    continue
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
    
    def _extract_csrf_token(self, html: str):
        if self.csrf_token:
            return
        match = re.search(r"'csrf__token':\s*\"([^\"]+)\"", html)
        if match:
            self.csrf_token = match.group(1)
            return
        match = re.search(r"csrf__token['\"]?\s*[:=]\s*['\"]([^'\"]+)['\"]", html)
        if match:
            self.csrf_token = match.group(1)
            return
        match = re.search(r'<input[^>]*name=["\']csrf_token["\'][^>]*value=["\']([^"\']+)["\']', html)
        if match:
            self.csrf_token = match.group(1)
    
    def _extract_numbers(self, text: Optional[str]) -> Optional[int]:
        if not text:
            return None
        match = re.search(r'\d+', text)
        return int(match.group()) if match else None
    
    def _to_search_result(self, element) -> Optional[Dict]:
        a = element if element.name == 'a' else element.select_one('a')
        if not a:
            return None
        
        href = a.get('href')
        if not href:
            return None
        href = self._to_absolute(href)
        
        title = a.get('title', '')
        if not title:
            title_elem = a.select_one('h3, h2, .title, .post__name')
            if title_elem:
                title = title_elem.get_text(strip=True)
        if not title:
            title = a.get_text(strip=True)
        if not title:
            return None
        
        img = a.select_one('img')
        poster = ''
        if img:
            poster = img.get('data-src') or img.get('src', '')
            poster = self._to_absolute(poster)
        
        is_movie = ('فيلم' in title or 
                   'movie' in title.lower() or
                   '/%d9%81%d9%8a%d9%84%d9%85-' in href or
                   '/movie-' in href)
        
        return {
            'title': title,
            'url': href,
            'poster': poster,
            'type': 'فيلم' if is_movie else 'مسلسل'
        }
    
    def _extract_search_results(self, soup: BeautifulSoup) -> List[Dict]:
        """استخراج النتائج من الصفحة باستخدام محددات متعددة."""
        results = []
        selectors = [
            'ul.blocks__ul > li',
            '.blocks__ul > li',
            '.movie__block',
            '.GridItem',
            '.Block--Item',
            'li.Small--Box',
            '.post-item',
            '.search-item'
        ]
        
        for selector in selectors:
            elements = soup.select(selector)
            for element in elements:
                result = self._to_search_result(element)
                if result:
                    results.append(result)
            if results:
                break
        
        return results
    
    def search(self, query: str) -> List[Dict]:
        """
        البحث عن الأفلام والمسلسلات مع دعم معامل type.
        """
        base = self._get_base_url()
        encoded = quote(query.strip().replace(' ', '+'))
        
        results = []
        
        # الطريقة 1: البحث باستخدام /find/ مع type=series للمسلسلات
        search_url_series = f"{base}/find/?word={encoded}&type=series"
        soup = self._smart_get(search_url_series)
        if soup:
            results.extend(self._extract_search_results(soup))
        
        # البحث عن الأفلام
        search_url_movies = f"{base}/find/?word={encoded}&type=movies"
        soup = self._smart_get(search_url_movies)
        if soup:
            results.extend(self._extract_search_results(soup))
        
        # إذا لم تظهر نتائج، نجرب بدون type
        if not results:
            search_url = f"{base}/find/?word={encoded}"
            soup = self._smart_get(search_url)
            if soup:
                results.extend(self._extract_search_results(soup))
        
        # الطريقة 2: البحث من الصفحة الرئيسية
        if not results:
            search_url = f"{base}/?s={encoded}"
            soup = self._smart_get(search_url)
            if soup:
                results.extend(self._extract_search_results(soup))
        
        # الطريقة 3: محاولة POST
        if not results:
            try:
                search_url = f"{base}/wp-admin/admin-ajax.php"
                post_data = {
                    'action': 'search',
                    'query': query
                }
                response_text = self._smart_post(search_url, data=post_data)
                if response_text:
                    try:
                        data = json.loads(response_text)
                        if 'html' in data:
                            temp_soup = BeautifulSoup(data['html'], 'lxml')
                            for element in temp_soup.select('a'):
                                result = self._to_search_result(element)
                                if result:
                                    results.append(result)
                    except:
                        pass
            except:
                pass
        
        # إزالة التكرارات
        seen = set()
        unique_results = []
        for r in results:
            if r['url'] not in seen:
                seen.add(r['url'])
                unique_results.append(r)
        
        return unique_results
    
    def get_details(self, url: str) -> Dict:
        soup = self._smart_get(url)
        if not soup:
            return {'name': '', 'story': '', 'poster': '', 'year': '', 'genres': []}
        
        title_elem = soup.select_one('h1.post__name')
        name = title_elem.get_text(strip=True) if title_elem else ''
        
        poster_elem = soup.select_one('.poster__single img, .single__cover > img:not(.rating__box img), .post__poster img')
        poster = ''
        if poster_elem:
            poster = poster_elem.get('data-src') or poster_elem.get('src', '')
            poster = self._to_absolute(poster)
        
        story_elem = soup.select_one('.post__story > p')
        story = story_elem.get_text(strip=True) if story_elem else ''
        
        year = None
        breadcrumbs = soup.select('.bread__crumbs li a')
        for a in breadcrumbs:
            text = a.get_text(strip=True)
            if text.isdigit() and len(text) == 4:
                year = int(text)
                break
        
        genres = []
        for a in soup.select('.post__tax a'):
            genres.append(a.get_text(strip=True))
        
        return {
            'name': name,
            'story': story,
            'poster': poster,
            'year': str(year) if year else '',
            'genres': genres
        }
    
    def get_episodes(self, url: str) -> Dict[str, List[Dict]]:
        soup = self._smart_get(url)
        if not soup:
            return {'Season 01': []}
        
        season_elements = soup.select('div#seasons__list ul li')
        
        if not season_elements:
            episodes_list = soup.select('ul.episodes__list li a')
            if episodes_list:
                episodes = []
                for ep_el in episodes_list:
                    ep_href = ep_el.get('href')
                    if not ep_href:
                        continue
                    ep_href = self._to_absolute(ep_href)
                    ep_title = ep_el.select_one('.epi__num')
                    ep_title_text = ep_title.get_text(strip=True) if ep_title else ep_el.get_text(strip=True)
                    ep_num = self._extract_numbers(ep_title_text) or 1
                    episodes.append({
                        'number': str(ep_num).zfill(2),
                        'title': ep_title_text,
                        'url': ep_href
                    })
                if episodes:
                    return {'Season 01': episodes}
            return {'Film': [{'number': '01', 'title': 'فيلم', 'url': url}]}
        
        self._extract_csrf_token(str(soup))
        if not self.csrf_token:
            script_text = soup.find('script')
            if script_text:
                self._extract_csrf_token(script_text.string or '')
        
        if not self.csrf_token:
            return {'Season 01': []}
        
        base = self._get_base_url()
        episodes = []
        
        for idx, season_el in enumerate(season_elements, 1):
            season_id = season_el.get('data-term', '').strip()
            if not season_id:
                continue
            
            season_num = idx
            offset = 0
            has_more = True
            
            while has_more:
                post_data = {
                    'season_id': season_id,
                    'offset': str(offset),
                    'csrf_token': self.csrf_token
                }
                
                response_text = self._smart_post(
                    f"{base}/season__episodes/",
                    data=post_data,
                    referer=url
                )
                
                if not response_text:
                    break
                
                try:
                    data = json.loads(response_text)
                    html_content = data.get('html', '')
                    has_more = data.get('hasmore', False)
                    
                    if not html_content:
                        break
                    
                    temp_soup = BeautifulSoup(html_content, 'lxml')
                    new_episodes = temp_soup.select('li a')
                    
                    if not new_episodes:
                        break
                    
                    for ep_el in new_episodes:
                        ep_href = ep_el.get('href')
                        if not ep_href:
                            continue
                        ep_href = self._to_absolute(ep_href)
                        ep_title = ep_el.select_one('.epi__num')
                        ep_title_text = ep_title.get_text(strip=True) if ep_title else ep_el.get_text(strip=True)
                        ep_num = self._extract_numbers(ep_title_text) or 1
                        
                        episodes.append({
                            'number': str(ep_num).zfill(2),
                            'title': f"الموسم {season_num} - {ep_title_text}",
                            'url': ep_href,
                            'season': season_num,
                            'episode': ep_num
                        })
                    
                    offset += len(new_episodes)
                    if not has_more:
                        break
                        
                except json.JSONDecodeError:
                    break
        
        episodes.sort(key=lambda x: (x.get('season', 1), int(x['number'])))
        
        seasons_dict = {}
        for ep in episodes:
            season_key = f"Season {str(ep.get('season', 1)).zfill(2)}"
            if season_key not in seasons_dict:
                seasons_dict[season_key] = []
            seasons_dict[season_key].append({
                'number': ep['number'],
                'title': ep['title'],
                'url': ep['url']
            })
        
        return seasons_dict if seasons_dict else {'Season 01': []}
    
    def get_servers(self, episode_url: str) -> List[Dict]:
        servers = []
        base = self._get_base_url()
        
        soup = self._smart_get(episode_url)
        if not soup:
            return []
        
        watch_btn = soup.select_one('a.btton.watch__btn')
        if not watch_btn:
            return []
        watch_url = watch_btn.get('href')
        if not watch_url:
            return []
        watch_url = self._to_absolute(watch_url)
        
        watch_soup = self._smart_get(watch_url, referer=episode_url)
        if not watch_soup:
            return []
        
        self._extract_csrf_token(str(watch_soup))
        if not self.csrf_token:
            script_text = watch_soup.find('script')
            if script_text:
                self._extract_csrf_token(script_text.string or '')
        
        if not self.csrf_token:
            return []
        
        first_li = watch_soup.select_one('.servers__list li')
        post_id = first_li.get('data-post') if first_li else None
        if not post_id:
            return []
        
        quality_elements = watch_soup.select('.quality__swither ul.qualities__list li')
        
        for quality_el in quality_elements:
            quality = quality_el.get('data-quality')
            if not quality:
                continue
            
            post_data = {
                'post_id': post_id,
                'quality': quality,
                'csrf_token': self.csrf_token
            }
            
            response_text = self._smart_post(
                f"{base}/get__quality__servers/",
                data=post_data,
                referer=watch_url
            )
            
            if not response_text:
                continue
            
            try:
                data = json.loads(response_text)
                html_content = data.get('html', '')
                if not html_content:
                    continue
                
                temp_soup = BeautifulSoup(html_content, 'lxml')
                server_elements = temp_soup.select('li')
                
                for server_el in server_elements:
                    server_id = server_el.get('data-server')
                    if not server_id:
                        continue
                    
                    server_post_data = {
                        'post_id': post_id,
                        'quality': quality,
                        'server': server_id,
                        'csrf_token': self.csrf_token
                    }
                    
                    server_response = self._smart_post(
                        f"{base}/get__watch__server/",
                        data=server_post_data,
                        referer=watch_url
                    )
                    
                    if not server_response:
                        continue
                    
                    try:
                        server_data = json.loads(server_response)
                        iframe_url = server_data.get('server')
                        if iframe_url and iframe_url.startswith('http'):
                            servers.append({
                                'name': f"{quality} - سيرفر",
                                'type': 'streaming',
                                'embed_url': iframe_url
                            })
                    except json.JSONDecodeError:
                        continue
                        
            except json.JSONDecodeError:
                continue
        
        return servers
    
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