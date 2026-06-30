# mycima_provider.py
import re
import base64
from urllib.parse import urljoin, urlparse
from typing import List, Dict, Optional
from .base_provider import BaseProvider
from bs4 import BeautifulSoup
from utils.unpacker import unpack_packer, decode_base64_url


class MyCimaProvider(BaseProvider):
    name = "MyCima"
    domains = ["https://mycima.boo"]
    base_url = "https://mycima.boo"
    
    def __init__(self):
        super().__init__()
        self.headers.update({
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language': 'ar,en-US;q=0.9,en;q=0.8',
            'Upgrade-Insecure-Requests': '1',
            'Cache-Control': 'no-cache',
            'Pragma': 'no-cache',
        })
        self.redirect_url = None
    
    def _resolve_domain(self) -> str:
        if not self.domains:
            return self.base_url
        for domain in self.domains:
            try:
                resp = self.session.get(domain, timeout=15, allow_redirects=True)
                final_url = resp.url
                parsed = urlparse(final_url)
                base = f"{parsed.scheme}://{parsed.netloc}"
                self.redirect_url = base
                return base
            except Exception:
                continue
        return self.domains[0]
    
    def _get_base_url(self) -> str:
        if self.redirect_url:
            return self.redirect_url
        return self.base_url
    
    def _smart_get(self, url: str, referer: Optional[str] = None, timeout: int = 20) -> Optional[BeautifulSoup]:
        headers = self.headers.copy()
        if referer:
            headers['Referer'] = referer
        else:
            headers['Referer'] = self._get_base_url()
        
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
    
    def _smart_post(self, url: str, data: Dict[str, str], referer: Optional[str] = None, timeout: int = 20) -> Optional[BeautifulSoup]:
        headers = self.headers.copy()
        headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8'
        headers['X-Requested-With'] = 'XMLHttpRequest'
        if referer:
            headers['Referer'] = referer
        else:
            headers['Referer'] = self._get_base_url()
        
        try:
            resp = self.session.post(url, data=data, headers=headers, timeout=timeout, allow_redirects=True)
            if resp.status_code == 200:
                soup = BeautifulSoup(resp.text, 'lxml')
                if not self._is_cloudflare_challenge(soup):
                    return soup
        except Exception:
            pass
        
        try:
            from utils.cloudflare import solve_cloudflare_with_selenium
            html, cookies = solve_cloudflare_with_selenium(self._get_base_url())
            self.cookies.update(cookies)
            resp = self.session.post(url, data=data, headers=headers, cookies=self.cookies, timeout=timeout)
            if resp.status_code == 200:
                return BeautifulSoup(resp.text, 'lxml')
        except Exception:
            pass
        return None
    
    def _is_cloudflare_challenge(self, soup: BeautifulSoup) -> bool:
        title = soup.title.string if soup.title else ''
        html = str(soup)
        return ('Just a moment' in title or
                'Attention Required' in title or
                'cf-turnstile' in html or
                'challenge-platform' in html) and 'Grid--WecimaPosts' not in html
    
    def _extract_numbers(self, text: Optional[str]) -> Optional[int]:
        if not text:
            return None
        match = re.search(r'\d+', text)
        return int(match.group()) if match else None
    
    def _get_poster_from_style(self, element) -> Optional[str]:
        if not element:
            return None
        style = element.get('style') or element.get('data-lazy-style', '')
        if style:
            match = re.search(r'url\((.*?)\)', style)
            if match:
                return match.group(1).strip('\'" ')
        return None
    
    def _extract_server_name(self, element) -> str:
        text = element.get_text(strip=True)
        return ' '.join(text.split())
    
    def _to_search_result(self, element) -> Optional[Dict]:
        link_element = element.select_one('div.Thumb--GridItem a')
        if not link_element:
            return None
        url = link_element.get('href')
        if not url:
            return None
        
        poster = self._get_poster_from_style(link_element.select_one('span.BG--GridItem'))
        title_tag = link_element.select_one('strong')
        if not title_tag:
            return None
        title = title_tag.get_text(strip=True)
        year_tag = title_tag.select_one('span.year')
        year = self._extract_numbers(year_tag.get_text(strip=True)) if year_tag else None
        
        is_movie = element.select_one('div.Episode--number') is None and '/series/' not in url
        
        return {
            'title': title,
            'url': urljoin(self._get_base_url(), url),
            'poster': poster,
            'year': year,
            'type': 'فيلم' if is_movie else 'مسلسل'
        }
    
    def search(self, query: str) -> List[Dict]:
        base = self._get_base_url()
        search_url = f"{base}/filtering/?keywords={query.replace(' ', '+')}"
        soup = self._smart_get(search_url)
        if not soup:
            return []
        
        results = []
        for item in soup.select('div#MainFiltar div.GridItem'):
            result = self._to_search_result(item)
            if result:
                results.append(result)
        return results
    
    def get_details(self, url: str) -> Dict:
        soup = self._smart_get(url)
        if not soup:
            return {'name': '', 'story': '', 'poster': '', 'year': '', 'genres': []}
        
        title = soup.select_one('div.Title--Content--Single-begin > h1')
        if title:
            name = title.get_text(strip=True)
        else:
            meta_title = soup.select_one("meta[property='og:title']")
            if meta_title:
                name = meta_title.get('content', '').strip()
                name = re.sub(r' - وي سيما WECIMA ماي سيما MYCIMA', '', name)
                name = re.sub(r' - ماي سيما', '', name)
            else:
                name = soup.title.string if soup.title else ''
        
        if not name:
            name = ''
        
        poster = self._get_poster_from_style(soup.select_one('wecima.separated--top'))
        
        year_tag = soup.select_one('div.Title--Content--Single-begin h1 a')
        year = self._extract_numbers(year_tag.get_text(strip=True)) if year_tag else None
        
        story = soup.select_one('div.StoryMovieContent')
        story = story.get_text(strip=True) if story else ''
        
        genres = []
        for a in soup.select('ul.Terms--Content--Single-begin li:has(span:contains(النوع)) p a'):
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
        
        is_series = soup.select_one('div.SeasonsList, .Seasons--Episodes') is not None
        
        if not is_series:
            series_link = soup.select_one('ul.Terms--Content--Single-begin li:contains(المسلسل) a')
            if series_link:
                series_url = series_link.get('href')
                if series_url:
                    return self.get_episodes(urljoin(self._get_base_url(), series_url))
            return {'Film': [{'number': '01', 'title': 'فيلم', 'url': url}]}
        
        episodes = []
        post_id = self._extract_post_id(soup)
        base = self._get_base_url()
        ajax_url = f"{base}/wp-content/themes/mycima/Ajaxt/Single/Episodes.php"
        
        season_anchors = soup.select('div.SeasonsList ul li a, .Seasons--Episodes ul li a')
        
        if not season_anchors:
            for a in soup.select('div.EpisodesList a[href], a.episode[href]'):
                ep_title = a.select_one('.episodetitle')
                ep_title_text = ep_title.get_text(strip=True) if ep_title else a.get('title', a.get_text(strip=True))
                ep_num = self._extract_numbers(ep_title_text)
                ep_href = a.get('href') or a.get('data-href')
                if ep_href:
                    ep_href = urljoin(url, ep_href)
                    episodes.append({
                        'number': str(ep_num).zfill(2) if ep_num else '01',
                        'title': f"حلقة {ep_num}" if ep_num else ep_title_text,
                        'url': ep_href
                    })
            episodes = self._deduplicate_episodes(episodes)
            return {'Season 01': episodes}
        
        for idx, season_el in enumerate(season_anchors):
            season_text = season_el.get_text(strip=True)
            season_id = season_el.get('data-season') or season_el.get('data-season-id', '')
            season_href = season_el.get('href') or season_el.get('data-href', '')
            
            season_num = self._extract_numbers(season_text)
            if not season_num and season_id:
                season_num = self._extract_numbers(season_id)
            if not season_num:
                season_num = idx + 1
            
            season_label = f"الموسم {season_num}"
            season_name = f"Season {str(season_num).zfill(2)}"
            
            season_episodes = []
            
            if post_id and season_id:
                try:
                    data = {'season': season_id, 'post_id': post_id}
                    season_soup = self._smart_post(ajax_url, data, referer=url, timeout=10)
                    if season_soup:
                        for a in season_soup.select('a[href]'):
                            ep_title = a.select_one('.episodetitle')
                            ep_title_text = ep_title.get_text(strip=True) if ep_title else a.get('title', a.get_text(strip=True))
                            ep_num = self._extract_numbers(ep_title_text)
                            ep_href = a.get('href') or a.get('data-href')
                            if ep_href:
                                ep_href = urljoin(url, ep_href)
                                season_episodes.append({
                                    'number': str(ep_num).zfill(2) if ep_num else '01',
                                    'title': f"{season_label} حلقة {ep_num}" if ep_num else ep_title_text,
                                    'url': ep_href
                                })
                except Exception:
                    pass
            
            if not season_episodes and season_href:
                try:
                    season_url = urljoin(url, season_href)
                    season_soup = self._smart_get(season_url, referer=url, timeout=10)
                    if season_soup:
                        for a in season_soup.select('div.EpisodesList a[href]'):
                            ep_title = a.select_one('.episodetitle')
                            ep_title_text = ep_title.get_text(strip=True) if ep_title else a.get('title', a.get_text(strip=True))
                            ep_num = self._extract_numbers(ep_title_text)
                            ep_href = a.get('href') or a.get('data-href')
                            if ep_href:
                                ep_href = urljoin(url, ep_href)
                                season_episodes.append({
                                    'number': str(ep_num).zfill(2) if ep_num else '01',
                                    'title': f"{season_label} حلقة {ep_num}" if ep_num else ep_title_text,
                                    'url': ep_href
                                })
                except Exception:
                    pass
            
            if not season_episodes:
                season_block = soup.select('div.SeasonsList, .Seasons--Episodes')
                if idx < len(season_block):
                    block = season_block[idx]
                    for a in block.select('div.EpisodesList a[href]'):
                        ep_title = a.select_one('.episodetitle')
                        ep_title_text = ep_title.get_text(strip=True) if ep_title else a.get('title', a.get_text(strip=True))
                        ep_num = self._extract_numbers(ep_title_text)
                        ep_href = a.get('href') or a.get('data-href')
                        if ep_href:
                            ep_href = urljoin(url, ep_href)
                            season_episodes.append({
                                'number': str(ep_num).zfill(2) if ep_num else '01',
                                'title': f"{season_label} حلقة {ep_num}" if ep_num else ep_title_text,
                                'url': ep_href
                            })
            
            if season_episodes:
                season_episodes.sort(key=lambda x: int(x['number']) if x['number'].isdigit() else 0)
                episodes.extend(season_episodes)
        
        seasons_dict = {}
        for ep in episodes:
            season_match = re.search(r'الموسم (\d+)', ep['title'])
            if season_match:
                season_num = int(season_match.group(1))
                season_key = f"Season {str(season_num).zfill(2)}"
            else:
                season_key = "Season 01"
            
            if season_key not in seasons_dict:
                seasons_dict[season_key] = []
            seasons_dict[season_key].append({
                'number': ep['number'],
                'title': ep['title'],
                'url': ep['url']
            })
        
        for season in seasons_dict.values():
            season.sort(key=lambda x: int(x['number']) if x['number'].isdigit() else 0)
        
        return seasons_dict if seasons_dict else {'Season 01': []}
    
    def _extract_post_id(self, soup: BeautifulSoup) -> Optional[str]:
        input_tag = soup.select_one('input[name=post_id]')
        if input_tag:
            value = input_tag.get('value')
            if value:
                return value
        
        for selector in ['[data-post_id]', '[data-postid]', 'meta[name=post_id]']:
            tag = soup.select_one(selector)
            if tag:
                value = tag.get('data-post_id') or tag.get('data-postid') or tag.get('content')
                if value:
                    return value
        
        scripts = soup.find_all('script')
        for script in scripts:
            text = script.string or ''
            match = re.search(r"post_id['\"]?\s*[:=]\s*['\"]?(\d{3,})['\"]?", text)
            if match:
                return match.group(1)
            match = re.search(r"postid['\"]?\s*[:=]\s*['\"]?(\d{3,})['\"]?", text)
            if match:
                return match.group(1)
        return None
    
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
        base = self._get_base_url()
        
        for li in soup.select('ul#watch li[data-watch]'):
            url = li.get('data-watch')
            name = self._extract_server_name(li)
            if url:
                servers.append({
                    'name': name,
                    'type': 'streaming',
                    'embed_url': url
                })
        
        for li in soup.select('ul.List--Download--Wecima--Single li a[href]'):
            url = li.get('href')
            name_tag = li.select_one('quality')
            name = name_tag.get_text(strip=True) if name_tag else 'تحميل'
            if url:
                servers.append({
                    'name': name,
                    'type': 'download',
                    'embed_url': url
                })
        
        # فك تشفير الروابط المشفرة باستخدام unpacker
        for server in servers:
            embed_url = server['embed_url']
            if 'govid.site' in embed_url:
                try:
                    govid_soup = self._smart_get(embed_url, referer=episode_url)
                    if govid_soup:
                        iframe = govid_soup.select_one('iframe')
                        if iframe:
                            server['embed_url'] = iframe.get('src')
                except Exception:
                    pass
            elif 'mycima.page/go/' in embed_url:
                try:
                    # استخدام decode_base64_url من unpacker
                    decoded = decode_base64_url(embed_url.split('/')[-1])
                    if decoded:
                        server['embed_url'] = decoded
                except Exception:
                    pass
            elif 'data:text/html;base64' in embed_url:
                try:
                    # استخراج النص المشفر base64 داخل data URI
                    base64_part = embed_url.split(',')[-1]
                    decoded = decode_base64_url(base64_part)
                    if decoded:
                        # قد يكون النص يحتوي على رابط
                        match = re.search(r'https?://[^\s\'"]+', decoded)
                        if match:
                            server['embed_url'] = match.group(0)
                except Exception:
                    pass
        
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