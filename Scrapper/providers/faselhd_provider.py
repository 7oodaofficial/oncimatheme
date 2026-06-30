import re
from .base_provider import BaseProvider
from bs4 import BeautifulSoup
from urllib.parse import urljoin
from utils.cloudflare import solve_cloudflare_with_selenium

class FaselhdProvider(BaseProvider):
    name = "Faselhd"
    base_url = "https://web31312x.faselhdx.bid"

    def __init__(self):
        super().__init__()
        self.cookies = {}
        self.base_url = self._get_real_base()

    def _get_real_base(self):
        for url in ["https://web31312x.faselhdx.bid", "https://faselhd.io"]:
            try:
                resp = self.session.get(url, timeout=10, allow_redirects=True)
                if resp.status_code == 200:
                    return resp.url.split('/')[0] + '//' + resp.url.split('/')[2]
            except:
                continue
        return "https://web31312x.faselhdx.bid"

    def _get_with_selenium(self, url):
        html, cookies = solve_cloudflare_with_selenium(url)
        self.cookies.update(cookies)
        return html

    def search(self, query):
        search_url = f"{self.base_url}/?s={query.replace(' ', '+')}"
        html = self._get_with_selenium(search_url)
        soup = BeautifulSoup(html, 'lxml')
        results = []
        for div in soup.select("div.postDiv, div.blockMovie"):
            a = div.select_one("a")
            if a:
                title = a.select_one(".h1, .h4, .h5")
                title = title.get_text(strip=True) if title else a.get('title', '')
                href = a.get('href')
                if href:
                    full_url = urljoin(self.base_url, href)
                    poster = a.select_one("img")
                    poster_url = poster.get('src') if poster else ''
                    results.append({
                        'title': title,
                        'url': full_url,
                        'poster': poster_url,
                        'type': 'مسلسل' if '/series/' in href else 'فيلم'
                    })
        return results

    def get_details(self, url):
        html = self._get_with_selenium(url)
        soup = BeautifulSoup(html, 'lxml')
        details = {'name': '', 'story': '', 'poster': '', 'year': '', 'genres': []}
        title = soup.select_one(".singleInfo .title.h1")
        if title:
            details['name'] = title.get_text(strip=True)
        story = soup.select_one(".singleDesc p, .story p")
        if story:
            details['story'] = story.get_text(strip=True)
        poster = soup.select_one("meta[itemprop='image']")
        if poster:
            details['poster'] = poster.get('content')
        return details

    def get_episodes(self, url):
        html = self._get_with_selenium(url)
        soup = BeautifulSoup(html, 'lxml')
        episodes = []
        for a in soup.select("div#epAll a"):
            href = a.get('href')
            if href:
                full_url = urljoin(self.base_url, href)
                title = a.get_text(strip=True)
                ep_num = ''
                m = re.search(r'(\d+)', title)
                if m:
                    ep_num = m.group(1).zfill(2)
                episodes.append({'number': ep_num, 'title': title, 'url': full_url})
        if not episodes:
            # فيلم
            title = soup.select_one(".singleInfo .title.h1")
            title = title.get_text(strip=True) if title else "فيلم"
            episodes = [{'number': '01', 'title': title, 'url': url}]
        else:
            episodes.sort(key=lambda x: int(x['number']) if x['number'].isdigit() else 0)
        return {'Season 01': episodes}

    def get_servers(self, episode_url):
        html = self._get_with_selenium(episode_url)
        soup = BeautifulSoup(html, 'lxml')
        servers = []
        iframes = soup.find_all('iframe')
        for iframe in iframes:
            src = iframe.get('src')
            if src and src.startswith('http'):
                servers.append({'name': 'Iframe', 'embed_url': src})
        for tag in soup.select('[onclick]'):
            onclick = tag.get('onclick', '')
            m = re.search(r"player_iframe\.location\.href\s*=\s*['\"]([^'\"]+)['\"]", onclick)
            if m:
                servers.append({'name': 'Player', 'embed_url': m.group(1)})
        return servers