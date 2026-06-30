# egydead_provider.py
import re
from .base_provider import BaseProvider
from bs4 import BeautifulSoup
from urllib.parse import urljoin

class EgyDeadProvider(BaseProvider):
    name = "EgyDead"
    base_url = "https://egydead.beer"

    def __init__(self):
        super().__init__()
        self.cf_clearance = None

    def _get_with_cf(self, url):
        resp = self.session.get(url, timeout=30)
        return resp

    def search(self, query):
        search_url = f"{self.base_url}/?s={query.replace(' ', '+')}"
        resp = self._get_with_cf(search_url)
        soup = BeautifulSoup(resp.text, 'lxml')
        results = []
        for li in soup.select("ul.posts-list li.movieItem"):
            a = li.select_one("a")
            if a:
                title = a.select_one("h1.BottomTitle")
                title = title.get_text(strip=True) if title else a.get('title', '')
                href = a.get('href')
                if href:
                    full_url = urljoin(self.base_url, href)
                    poster = li.select_one("img")
                    poster_url = poster.get('src') if poster else ''
                    results.append({
                        'title': title,
                        'url': full_url,
                        'poster': poster_url,
                        'type': 'مسلسل' if '/season/' in href or '/series/' in href else 'فيلم'
                    })
        return results

    def get_details(self, url):
        resp = self._get_with_cf(url)
        soup = BeautifulSoup(resp.text, 'lxml')
        details = {'name': '', 'story': '', 'poster': '', 'year': '', 'genres': []}
        og_title = soup.select_one("meta[property='og:title']")
        if og_title:
            details['name'] = og_title.get('content', '').strip()
        else:
            h1 = soup.select_one("h1.TitleMaster")
            if h1:
                details['name'] = h1.get_text(strip=True)
        story = soup.select_one("div.singleStory")
        if story:
            details['story'] = story.get_text(strip=True)
        og_image = soup.select_one("meta[property='og:image']")
        if og_image:
            details['poster'] = og_image.get('content')
        for li in soup.select("div.LeftBox li"):
            span = li.select_one("span")
            if span:
                key = span.get_text(strip=True)
                if 'السنه' in key:
                    a = li.select_one("a")
                    if a:
                        year_text = a.get_text(strip=True)
                        m = re.search(r'(\d{4})', year_text)
                        if m:
                            details['year'] = m.group(1)
                elif 'النوع' in key:
                    details['genres'] = [a.get_text(strip=True) for a in li.select("a")]
        return details

    def get_episodes(self, url):
        """
        Extract all seasons and episodes.
        If the page contains a seasons list, fetch each season's episodes.
        Otherwise, treat as single season (or movie) and extract episodes from current page.
        If no episodes found, treat as movie and return one pseudo-episode.
        """
        try:
            resp = self._get_with_cf(url)
            soup = BeautifulSoup(resp.text, 'lxml')
            
            # البحث عن قائمة المواسم
            seasons_container = soup.select_one("div.seasons-list")
            if seasons_container:
                # استخراج روابط المواسم
                season_links = []
                for li in seasons_container.select("li.movieItem a"):
                    href = li.get('href')
                    if href and '/season/' in href:
                        season_num_match = re.search(r's(\d+)', href)
                        if season_num_match:
                            season_num = int(season_num_match.group(1))
                        else:
                            title = li.get('title', '')
                            num_match = re.search(r'الموسم (\d+)', title)
                            if num_match:
                                season_num = int(num_match.group(1))
                            else:
                                season_num = 0
                        season_links.append((season_num, href))
                
                season_links.sort(key=lambda x: x[0])
                
                all_seasons = {}
                for season_num, season_url in season_links:
                    season_name = f"Season {str(season_num).zfill(2)}"
                    episodes = self._extract_episodes_from_season_page(season_url)
                    if episodes:
                        all_seasons[season_name] = episodes
                    else:
                        episodes = self._extract_episodes_with_post(season_url)
                        if episodes:
                            all_seasons[season_name] = episodes
                
                return all_seasons if all_seasons else {'Season 01': []}
            else:
                # لا توجد قائمة مواسم
                # محاولة استخراج حلقات من الصفحة الحالية (مسلسل عادي)
                episodes = self._extract_episodes_from_season_page(url)
                if not episodes:
                    episodes = self._extract_episodes_with_post(url)
                
                if episodes:
                    # وجدنا حلقات، نعيدها كموسم واحد
                    season_title = 'Season 01'
                    # محاولة استخراج اسم الموسم من الصفحة
                    season_link = soup.select_one("li a[href*='/season/']")
                    if season_link:
                        season_title = season_link.get_text(strip=True)
                    return {season_title: episodes}
                else:
                    # لا توجد حلقات ولا مواسم → نعتبره فيلماً
                    # نعيد موسماً واحداً يحتوي على حلقة واحدة (الرابط نفسه)
                    return {'Film': [{'number': '01', 'title': 'فيلم', 'url': url}]}
        except Exception as e:
            print(f"Error extracting episodes: {str(e)}")
            return {'Season 01': []}

    def _extract_episodes_from_season_page(self, url):
        try:
            resp = self._get_with_cf(url)
            soup = BeautifulSoup(resp.text, 'lxml')
            episodes = []
            for a in soup.select("div.EpsList li a"):
                href = a.get('href')
                title = a.get('title') or a.get_text(strip=True)
                if href and '/film/' not in href:
                    full_url = urljoin(self.base_url, href)
                    ep_num = ''
                    match = re.search(r'(\d+)', title)
                    if match:
                        ep_num = match.group(1).zfill(2)
                    episodes.append({
                        'number': ep_num,
                        'title': title,
                        'url': full_url
                    })
            episodes.reverse()
            seen = set()
            unique = []
            for ep in episodes:
                if ep['url'] not in seen:
                    seen.add(ep['url'])
                    unique.append(ep)
            return unique
        except Exception:
            return []

    def _extract_episodes_with_post(self, url):
        try:
            watch_url = url if '?view=watch' in url else url + '?view=watch'
            headers = self.headers.copy()
            headers.update({
                'Referer': url,
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            })
            try:
                form_resp = self.session.post(watch_url, data={'View': '1'}, headers=headers, timeout=30)
            except Exception:
                form_resp = self.session.get(watch_url, headers=headers, timeout=30)
            
            soup = BeautifulSoup(form_resp.text, 'lxml')
            episodes = []
            for a in soup.select("div.EpsList li a"):
                href = a.get('href')
                title = a.get('title') or a.get_text(strip=True)
                if href and '/film/' not in href:
                    full_url = urljoin(self.base_url, href)
                    ep_num = ''
                    match = re.search(r'(\d+)', title)
                    if match:
                        ep_num = match.group(1).zfill(2)
                    episodes.append({
                        'number': ep_num,
                        'title': title,
                        'url': full_url
                    })
            episodes.reverse()
            seen = set()
            unique = []
            for ep in episodes:
                if ep['url'] not in seen:
                    seen.add(ep['url'])
                    unique.append(ep)
            return unique
        except Exception:
            return []

    def get_servers(self, episode_url):
        """
        Extract streaming and download servers from any page (episode or movie).
        Returns servers with 'embed_url' key for compatibility with downloader.
        """
        try:
            headers = self.headers.copy()
            headers.update({
                'Referer': episode_url,
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            })
            
            resp = self.session.get(episode_url, headers=headers, timeout=30)
            soup = BeautifulSoup(resp.text, 'lxml')
            
            servers = []
            
            # ========== STREAMING SERVERS ==========
            # Method 1: ul.serversList li with data-link attribute
            for li in soup.select("ul.serversList li"):
                server_name_elem = li.select_one("span p") or li.select_one("span")
                name = server_name_elem.get_text(strip=True) if server_name_elem else None
                embed_url = li.get('data-link')
                
                if name and embed_url:
                    servers.append({
                        'name': name,
                        'type': 'streaming',
                        'embed_url': embed_url
                    })
            
            # Method 2: Fallback - check for a.ser-link inside serversList
            if not servers:
                for li in soup.select("ul.serversList li"):
                    server_name_elem = li.select_one("span p") or li.select_one("span")
                    name = server_name_elem.get_text(strip=True) if server_name_elem else None
                    link_elem = li.select_one("a")
                    embed_url = link_elem.get('href') if link_elem else None
                    
                    if name and embed_url:
                        servers.append({
                            'name': name,
                            'type': 'streaming',
                            'embed_url': embed_url
                        })
            
            # ========== DOWNLOAD SERVERS ==========
            for li in soup.select("ul.donwload-servers-list li"):
                server_name_elem = li.select_one("span.ser-name")
                name = server_name_elem.get_text(strip=True) if server_name_elem else None
                link_elem = li.select_one("a.ser-link")
                download_url = link_elem.get('href') if link_elem else None
                
                if name and download_url:
                    servers.append({
                        'name': name,
                        'type': 'download',
                        'embed_url': download_url
                    })
            
            # ========== Try POST for movies if no servers found ==========
            if not servers:
                watch_url = episode_url if '?view=watch' in episode_url else episode_url + '?view=watch'
                post_headers = headers.copy()
                post_headers.update({
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                })
                
                try:
                    post_resp = self.session.post(watch_url, data={'View': '1'}, headers=post_headers, timeout=30)
                    soup = BeautifulSoup(post_resp.text, 'lxml')
                    
                    for li in soup.select("ul.serversList li"):
                        server_name_elem = li.select_one("span p") or li.select_one("span")
                        name = server_name_elem.get_text(strip=True) if server_name_elem else None
                        embed_url = li.get('data-link')
                        
                        if name and embed_url:
                            servers.append({
                                'name': name,
                                'type': 'streaming',
                                'embed_url': embed_url
                            })
                    
                    for li in soup.select("ul.donwload-servers-list li"):
                        server_name_elem = li.select_one("span.ser-name")
                        name = server_name_elem.get_text(strip=True) if server_name_elem else None
                        link_elem = li.select_one("a.ser-link")
                        download_url = link_elem.get('href') if link_elem else None
                        
                        if name and download_url:
                            servers.append({
                                'name': name,
                                'type': 'download',
                                'embed_url': download_url
                            })
                except Exception as post_error:
                    print(f"[DEBUG] POST request failed: {str(post_error)}")
            
            return servers
            
        except Exception as e:
            print(f"Error extracting servers: {str(e)}")
            return []