import re
import json
from .base_provider import BaseProvider
from bs4 import BeautifulSoup
from urllib.parse import urljoin

class Shahid4uProvider(BaseProvider):
    name = "Shahid4u"
    domains = [
            #   "https://shahieid4u.com",
            #   "https://shaahed4u.net",
                "https://shaahed4u.net"]

    def search(self, query):
        search_url = f"{self.base_url}/search?s={query.replace(' ', '+')}"
        html = self._fetch(search_url)
        if not html:
            return []
        soup = BeautifulSoup(html, 'lxml')
        results = []
        for card in soup.select(".shows-container a.show-card, .shows-container > div a.show-card"):
            href = card.get('href')
            if not href:
                continue
            style = card.get('style', '')
            poster = ''
            m = re.search(r'url\([\'"]?(.*?)[\'"]?\)', style)
            if m:
                poster = m.group(1)
            title = card.select_one("p.title")
            title = title.get_text(strip=True) if title else card.get('title', '')
            if not title:
                continue
            full_url = urljoin(self.base_url, href)
            ep_span = card.select_one("span.ep")
            ep_num = ep_span.get_text(strip=True) if ep_span else ''
            item_type = 'مسلسل'
            if '/film/' in href:
                item_type = 'فيلم'
            results.append({
                'title': title,
                'url': full_url,
                'poster': poster,
                'episode': ep_num,
                'type': item_type
            })
        seen = set()
        unique = []
        for r in results:
            if r['url'] not in seen:
                seen.add(r['url'])
                unique.append(r)
        return unique

    def get_details(self, url):
        html = self._fetch(url)
        if not html:
            return {'name': '', 'story': '', 'poster': '', 'year': '', 'genres': []}
        soup = BeautifulSoup(html, 'lxml')
        details = {'name': '', 'story': '', 'poster': '', 'year': '', 'genres': []}
        title = soup.select_one("span.title")
        if title:
            details['name'] = title.get_text(strip=True)
        story = soup.select_one("span.description")
        if story:
            details['story'] = story.get_text(strip=True)
        poster_div = soup.select_one(".poster")
        if poster_div:
            style = poster_div.get('style', '')
            m = re.search(r'--background-image-url:\s*url\([\'"]?(.*?)[\'"]?\)', style)
            if m:
                details['poster'] = m.group(1)
        for a in soup.select(".info-side .ch a"):
            text = a.get_text(strip=True)
            if re.match(r'^\d{4}$', text) and not details['year']:
                details['year'] = text
            elif text and text not in details['genres']:
                details['genres'].append(text)
        return details

    def get_episodes(self, url):
        html = self._fetch(url)
        if not html:
            return {}
        soup = BeautifulSoup(html, 'lxml')
        episodes = []
        # تجربة المواسم أولاً - إذا وجدنا مواسم متعددة
        season_links = soup.select(".items a.epss[href*='/season/']")
        if season_links:
            seasons = {}
            for a in season_links:
                href = a.get('href')
                if not href:
                    continue
                season_num = a.select_one("span.fs-2")
                season_name = season_num.get_text(strip=True) if season_num else 'موسم'
                season_url = urljoin(self.base_url, href)
                season_html = self._fetch(season_url)
                if not season_html:
                    continue
                season_soup = BeautifulSoup(season_html, 'lxml')
                season_eps = []
                for ep_a in season_soup.select(".items a.epss"):
                    ep_href = ep_a.get('href')
                    if not ep_href or '/season/' in ep_href:
                        continue
                    ep_num = ep_a.select_one("span.fs-2")
                    num = ep_num.get_text(strip=True) if ep_num else ''
                    ep_title = ep_a.get('title') or ep_a.get_text(strip=True)
                    season_eps.append({
                        'number': num.zfill(2) if num else '?',
                        'title': ep_title,
                        'url': urljoin(self.base_url, ep_href)
                    })
                if season_eps:
                    season_eps.sort(key=lambda x: int(x['number']) if x['number'].isdigit() else 0)
                seasons[season_name] = season_eps
            return seasons
        # لا يوجد مواسم متعددة - استخراج الحلقات أو الفيلم
        for a in soup.select(".items a.epss"):
            href = a.get('href')
            if not href:
                continue
            ep_num = a.select_one("span.fs-2")
            num = ep_num.get_text(strip=True) if ep_num else ''
            ep_title = a.get('title') or a.get_text(strip=True)
            full_url = urljoin(self.base_url, href)
            episodes.append({
                'number': num.zfill(2) if num else '?',
                'title': ep_title,
                'url': full_url
            })
        if not episodes:
            title = soup.select_one("span.title")
            title = title.get_text(strip=True) if title else "فيلم"
            episodes = [{'number': '01', 'title': title, 'url': url}]
        else:
            episodes.sort(key=lambda x: int(x['number']) if x['number'].isdigit() else 0)
        return {'Season 01': episodes}

    def get_servers(self, episode_url):
        servers = []
        # تحويل رابط الحلقة إلى /watch/
        watch_url = episode_url.replace('/episode/', '/watch/').replace('/film/', '/watch/')
        if '/watch/' not in watch_url:
            watch_url = episode_url.rstrip('/') + '/watch/'
        html = self._fetch(watch_url)
        if not html:
            return servers
        # استخراج بيانات السيرفرات من متغير JavaScript
        m = re.search(r'let\s+servers\s*=\s*(\[[\s\S]*?\])\s*;', html)
        if m:
            try:
                servers_data = json.loads(m.group(1))
                for srv in servers_data:
                    name = srv.get('name', 'سيرفر')
                    url = srv.get('url', '')
                    if url:
                        servers.append({'name': name, 'embed_url': url})
            except:
                pass
        # محاولة ثانية: البحث عن JSON.parse
        if not servers:
            m2 = re.search(r"JSON\.parse\s*\(\s*'([^']+)'\s*\)", html)
            if m2:
                try:
                    json_str = m2.group(1).replace('\\/', '/')
                    servers_data = json.loads(json_str)
                    for srv in servers_data:
                        name = srv.get('name', 'سيرفر')
                        url = srv.get('url', '')
                        if url:
                            servers.append({'name': name, 'embed_url': url})
                except:
                    pass
        return servers
