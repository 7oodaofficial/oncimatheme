from .base_provider import BaseProvider
from bs4 import BeautifulSoup
from urllib.parse import urljoin
import requests
import re

class EskProvider(BaseProvider):
    name = "Esk"
    base_url = "https://esk.onl"
    
    def search(self, query):
        search_url = f"{self.base_url}/?s={requests.utils.quote(query)}"
        resp = self.session.get(search_url, timeout=30)
        soup = BeautifulSoup(resp.text, 'lxml')
        results = []
        for article in soup.select("article.post"):
            a = article.select_one("a[href]")
            if a:
                title = a.get('title') or a.get_text(strip=True)
                href = a['href']
                if href:
                    full_url = urljoin(self.base_url, href)
                    ep_num = article.select_one("div.episodeNum span:last-child")
                    ep = ep_num.get_text(strip=True) if ep_num else ''
                    results.append({
                        'title': title,
                        'url': full_url,
                        'episode': ep,
                        'type': 'مسلسل'
                    })
        return results

    def get_details(self, url):
        resp = self.session.get(url, timeout=30)
        soup = BeautifulSoup(resp.text, 'lxml')
        details = {'name': '', 'story': '', 'poster': '', 'year': '', 'genres': []}
        h1 = soup.select_one("h1 a") or soup.select_one("h1")
        if h1:
            details['name'] = h1.get_text(strip=True)
        story = soup.select_one("div.story")
        if story:
            details['story'] = story.get_text(strip=True)
        cover = soup.select_one("div.cover div.img")
        if cover:
            style = cover.get('style', '')
            m = re.search(r'url\((.*?)\)', style)
            if m:
                details['poster'] = urljoin(url, m.group(1).strip("'\""))
        # التصنيفات
        for a in soup.select("div.tax a"):
            text = a.get_text(strip=True)
            if text and text not in details['genres']:
                details['genres'].append(text)
        return details

    def get_episodes(self, url):
        resp = self.session.get(url, timeout=30)
        soup = BeautifulSoup(resp.text, 'lxml')
        episodes = []
        for a in soup.select("#epiList article.postEp a[href]"):
            title = a.get('title') or a.get_text(strip=True)
            href = a['href']
            if href:
                full_url = urljoin(self.base_url, href)
                ep_num = a.select_one("div.episodeNum span:last-child")
                num = ep_num.get_text(strip=True) if ep_num else ''
                episodes.append({'number': num.zfill(2) if num else '?', 'title': title, 'url': full_url})
        episodes.sort(key=lambda x: int(x['number']) if x['number'].isdigit() else 0)
        return {'Season 01': episodes}

    def get_servers(self, episode_url):
        see_url = episode_url.rstrip('/') + '/see/'
        resp = self.session.get(see_url, timeout=30)
        soup = BeautifulSoup(resp.text, 'lxml')
        servers = []
        for li in soup.select("ul.serversList li"):
            name_span = li.select_one("span")
            name = name_span.get_text(strip=True) if name_span else 'سيرفر'
            embed_noscript = li.select_one("noscript iframe")
            if embed_noscript:
                embed_url = embed_noscript.get('src')
                if embed_url:
                    servers.append({'name': name, 'embed_url': embed_url})
            else:
                # قد يكون هناك data-src
                data_src = li.get('data-src')
                if data_src:
                    servers.append({'name': name, 'embed_url': data_src})
        return servers