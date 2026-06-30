import re
from .base_provider import BaseProvider
from bs4 import BeautifulSoup
from urllib.parse import urljoin
import requests

class LarozaProvider(BaseProvider):
    name = "Laroza"
    base_url = "https://www.laroza.now"

    def __init__(self):
        super().__init__()
        self.base_url = self._get_base()

    def _get_base(self):
        for url in ["https://www.laroza.now", "https://laroza.now", "https://laroza.xyz"]:
            try:
                resp = self.session.get(url, timeout=10)
                if resp.status_code == 200:
                    return url
            except:
                continue
        return "https://www.laroza.now"

    def search(self, query):
        self.base_url = self._get_base()
        search_url = f"{self.base_url}/search.php?keywords={requests.utils.quote(query)}"
        resp = self.session.get(search_url, timeout=30)
        soup = BeautifulSoup(resp.text, 'lxml')
        results = []
        for li in soup.select("ul#pm-grid li"):
            a = li.find('a', href=True)
            if a:
                title = a.get('title') or a.get_text(strip=True)
                href = a['href']
                if href and 'video.php' in href:
                    full_url = urljoin(self.base_url, href)
                    results.append({
                        'title': title,
                        'url': full_url,
                        'type': 'مسلسل' if 'مسلسل' in title else 'فيلم'
                    })
        # إزالة المكررات
        seen = set()
        unique = []
        for r in results:
            if r['url'] not in seen:
                seen.add(r['url'])
                unique.append(r)
        return unique

    def get_details(self, url):
        resp = self.session.get(url, timeout=30)
        soup = BeautifulSoup(resp.text, 'lxml')
        details = {'name': '', 'story': '', 'poster': '', 'year': '', 'genres': []}
        h1 = soup.find('h1') or soup.find('title')
        if h1:
            details['name'] = h1.get_text(strip=True)
        poster_div = soup.select_one("#BiBplayer > div.video-bibplayer-poster")
        if poster_div:
            style = poster_div.get('style', '')
            m = re.search(r'url\((.*?)\)', style)
            if m:
                details['poster'] = urljoin(url, m.group(1).strip("'\""))
        desc = soup.select_one("div.pm-video-description div[itemprop='description']")
        if desc:
            details['story'] = desc.get_text(strip=True)
        dl = soup.select_one("div.dl-horizontal")
        if dl:
            for dt, dd in zip(dl.find_all('dt'), dl.find_all('dd')):
                key = dt.get_text(strip=True).lower()
                val = dd.get_text(strip=True)
                if 'سنة' in key or 'year' in key:
                    m = re.search(r'(\d{4})', val)
                    if m:
                        details['year'] = m.group(1)
                elif 'تصنيف' in key or 'genre' in key:
                    details['genres'] = [g.strip() for g in val.split(',') if g.strip()]
        return details

    def get_episodes(self, url):
        resp = self.session.get(url, timeout=30)
        soup = BeautifulSoup(resp.text, 'lxml')
        seasons = {}
        # محاولة استخراج المواسم
        season_items = soup.select("div.SeasonsBoxUL li[data-serie]")
        if season_items:
            for item in season_items:
                season_id = item.get('data-serie')
                container = soup.select_one(f"div.SeasonsEpisodes[data-serie='{season_id}']")
                if not container:
                    continue
                eps = []
                for a in container.find_all('a', href=True):
                    title = a.get('title') or a.get_text(strip=True)
                    href = a['href']
                    if href:
                        full_url = urljoin(url, href)
                        ep_num = ''
                        m = re.search(r'(\d+)', title)
                        if m:
                            ep_num = m.group(1).zfill(2)
                        eps.append({'number': ep_num, 'title': title, 'url': full_url})
                if eps:
                    seasons[f"Season {season_id.zfill(2)}"] = eps
        else:
            # لا يوجد مواسم -> فيلم أو صفحة حلقة واحدة
            title = soup.select_one("h1") or soup.select_one("title")
            title = title.get_text(strip=True) if title else "فيلم"
            seasons['Season 01'] = [{'number': '01', 'title': title, 'url': url}]
        return seasons

    def get_servers(self, episode_url):
        # تحويل video.php إلى play.php لجلب السيرفرات
        play_url = episode_url.replace('video.php', 'play.php')
        # إذا لم يكن هناك تحويل، نضيف ?play=1
        if 'play.php' not in play_url:
            play_url = episode_url + ('&' if '?' in episode_url else '?') + 'play=1'
        try:
            resp = self.session.get(play_url, timeout=30)
            soup = BeautifulSoup(resp.text, 'lxml')
            servers = []
            for li in soup.select("ul.WatchList li[data-embed-url]"):
                embed = li.get('data-embed-url')
                name = li.get_text(strip=True) or 'سيرفر'
                if embed:
                    servers.append({'name': name, 'embed_url': embed})
            return servers
        except:
            return []