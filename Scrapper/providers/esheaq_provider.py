import re
import json
import base64
from .base_provider import BaseProvider
from bs4 import BeautifulSoup
from urllib.parse import urljoin, urlparse, parse_qs


class EsheaqProvider(BaseProvider):
    name = "Esheaq"
    domains = ["https://qeseh.net", "https://qesset.net", "https://qesen.net"]

    def search(self, query):
        search_url = f"{self.base_url}/search/{query.replace(' ', '+')}/"
        html = self._fetch(search_url)
        if not html:
            return []
        soup = BeautifulSoup(html, 'lxml')
        results = []
        for article in soup.select("article.post"):
            block = article.select_one("div.block-post")
            if not block:
                continue
            a = block.select_one("a")
            if not a:
                continue
            title = a.select_one("div.title")
            title = title.get_text(strip=True) if title else a.get('title', '')
            href = a.get('href')
            if not title or not href:
                continue
            full_url = urljoin(self.base_url, href)
            img_div = a.select_one(".imgBg")
            poster = ''
            if img_div:
                style = img_div.get('style', '')
                m = re.search(r'url\([\'"]?(.*?)[\'"]?\)', style)
                if m:
                    poster = m.group(1)
            results.append({
                'title': title,
                'url': full_url,
                'poster': poster,
                'type': 'مسلسل' if '/series/' in href else 'فيلم'
            })
        return results

    def get_details(self, url):
        html = self._fetch(url)
        if not html:
            return {'name': '', 'story': '', 'poster': '', 'year': '', 'genres': []}
        soup = BeautifulSoup(html, 'lxml')
        details = {'name': '', 'story': '', 'poster': '', 'year': '', 'genres': []}
        h1 = soup.select_one(".info h1")
        if h1:
            details['name'] = h1.get_text(strip=True)
        story = soup.select_one(".info .story")
        if story:
            details['story'] = story.get_text(strip=True)
        cover = soup.select_one(".cover .img")
        if cover:
            style = cover.get('style', '')
            m = re.search(r'url\([\'"]?(.*?)[\'"]?\)', style)
            if m:
                details['poster'] = m.group(1)
        tax = soup.select_one(".info .tax")
        if tax:
            for a in tax.select("a"):
                text = a.get_text(strip=True)
                if text and text not in details['genres']:
                    details['genres'].append(text)
        return details

    def get_episodes(self, url):
        html = self._fetch(url)
        if not html:
            return {'Season 01': []}
        soup = BeautifulSoup(html, 'lxml')
        episodes = []
        for article in soup.select("article.postEp"):
            block = article.select_one("div.block-post")
            if not block:
                continue
            a = block.select_one("a")
            if not a:
                continue
            href = a.get('href')
            if not href:
                continue
            full_url = urljoin(self.base_url, href)
            title_div = a.select_one("div.title")
            title = title_div.get_text(strip=True) if title_div else ''
            ep_num_div = article.select_one(".episodeNum")
            num = ''
            if ep_num_div:
                ep_span = ep_num_div.select_one("span:last-child")
                if ep_span:
                    num = ep_span.get_text(strip=True)
            episodes.append({
                'number': num.zfill(2) if num else '?',
                'title': title,
                'url': full_url
            })
        if not episodes:
            h1 = soup.select_one(".info h1")
            title = h1.get_text(strip=True) if h1 else "فيلم"
            episodes = [{'number': '01', 'title': title, 'url': url}]
        else:
            episodes.sort(key=lambda x: int(x['number']) if x['number'].isdigit() else 0)
        return {'Season 01': episodes}

    def _decode_post_param(self, encoded):
        try:
            decoded = base64.b64decode(encoded).decode('utf-8')
            return decoded
        except:
            return None

    def _build_embed_url(self, server_name, server_id):
        name = server_name.lower()
        if 'youtube' in name:
            return f"https://www.youtube.com/embed/{server_id}"
        elif 'dailymotion' in name or 'daily' in name:
            return f"https://www.dailymotion.com/embed/video/{server_id}"
        elif 'estream' in name:
            return f"https://arabveturk.com/embed-{server_id}.html"
        elif 'arab' in name and 'hd' in name:
            return f"https://v.turkvearab.com/embed-{server_id}.html"
        elif 'red' in name and 'hd' in name:
            return f"https://iplayerhls.com/e/{server_id}"
        elif 'pro' in name and 'hd' in name:
            return f"https://w.larhu.com/play.php?id={server_id}"
        elif 'box' in name:
            return f"https://youdboox.com/embed-{server_id}.html"
        elif 'now' in name:
            return f"https://extreamnow.org/embed-{server_id}.html"
        elif 'ok' in name:
            return f"https://ok.ru/videoembed/{server_id}"
        elif 'express' in name:
            return server_id
        else:
            return f"https://player.any/{server_id}"

    def get_servers(self, episode_url):
        servers = []
        html = self._fetch(episode_url)
        if not html:
            return servers
        soup = BeautifulSoup(html, 'lxml')
        watch_link = None
        container = soup.select_one(".getEmbed .modern-player-container a")
        if container:
            watch_link = container.get('href')
        if not watch_link:
            a_full = soup.select_one("a.fullscreen-clickable")
            if a_full:
                watch_link = a_full.get('href')
        if not watch_link:
            return servers
        if '?post=' in watch_link:
            parsed = urlparse(watch_link)
            qs = parse_qs(parsed.query)
            encoded = qs.get('post', [None])[0]
            if encoded:
                post_json = self._decode_post_param(encoded)
                if post_json:
                    try:
                        data = json.loads(post_json)
                        for srv in data.get('servers', []):
                            name = srv.get('name', 'سيرفر')
                            srv_id = srv.get('id', '')
                            if srv_id:
                                embed_url = self._build_embed_url(name, srv_id)
                                servers.append({'name': name, 'embed_url': embed_url})
                    except:
                        pass
        elif '?url=' in watch_link:
            parsed = urlparse(watch_link)
            qs = parse_qs(parsed.query)
            encoded = qs.get('url', [None])[0]
            if encoded:
                decoded_url = self._decode_post_param(encoded)
                if decoded_url and decoded_url.startswith('http'):
                    servers.append({'name': 'رابط مباشر', 'embed_url': decoded_url})
        return servers
