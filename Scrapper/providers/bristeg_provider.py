import re
from .base_provider import BaseProvider
from bs4 import BeautifulSoup
from urllib.parse import urljoin

class BristegProvider(BaseProvider):
    name = "Bristeg"
    domains = ["https://hd1.brstej.com", "https://amd.brstej.com"]

    def search(self, query):
        search_url = f"{self.base_url}/search.php?keywords={query.replace(' ', '+')}"
        html = self._fetch(search_url)
        if not html:
            return []
        soup = BeautifulSoup(html, 'lxml')
        results = []
        for li in soup.select("ul.pm-ul-browse-videos > li"):
            caption = li.select_one("div.caption h3 a")
            if not caption:
                continue
            href = caption.get('href')
            if not href or '#modal-login-form' in href:
                continue
            full_url = urljoin(self.base_url, href)
            title = caption.get('title') or caption.get_text(strip=True)
            if not title:
                continue
            thumb = li.select_one("div.pm-video-thumb a")
            img = thumb.select_one("img") if thumb else None
            poster = img.get('data-echo') or img.get('data-original') or img.get('src') if img else ''
            duration = li.select_one("span.pm-label-duration")
            duration_text = duration.get_text(strip=True) if duration else ''
            results.append({
                'title': title,
                'url': full_url,
                'poster': poster,
                'duration': duration_text,
                'type': 'مسلسل' if 'series1.php' in href or 'view-serie.php' in href else 'فيلم'
            })
        return results

    def get_details(self, url):
        html = self._fetch(url)
        if not html:
            return {'name': '', 'story': '', 'poster': '', 'year': '', 'genres': []}
        soup = BeautifulSoup(html, 'lxml')
        details = {'name': '', 'story': '', 'poster': '', 'year': '', 'genres': []}
        h1 = soup.select_one("div.pm-video-heading h1")
        if h1:
            details['name'] = h1.get_text(strip=True)
        story = soup.select_one("div.pm-video-description div.txtv, div.pm-video-description")
        if story:
            details['story'] = story.get_text(strip=True)
        poster = soup.select_one("meta[property='og:image']")
        if poster:
            details['poster'] = poster.get('content')
        for dd in soup.select("dl.dl-horizontal"):
            for dt in dd.select("dt"):
                key = dt.get_text(strip=True)
                if 'سنة' in key or 'year' in key:
                    val_dd = dt.find_next_sibling("dd")
                    if val_dd:
                        m = re.search(r'(\d{4})', val_dd.get_text(strip=True))
                        if m:
                            details['year'] = m.group(1)
                if 'قسم' in key or 'tag' in key or 'genre' in key or 'تصنيف' in key:
                    val_dd = dt.find_next_sibling("dd")
                    if val_dd:
                        for a in val_dd.select("a"):
                            text = a.get_text(strip=True)
                            if text and text not in details['genres']:
                                details['genres'].append(text)
        return details

    def get_episodes(self, url):
        html = self._fetch(url)
        if not html:
            return {}
        soup = BeautifulSoup(html, 'lxml')
        seasons = {}
        seasons_box = soup.select_one("div.SeasonsBox")
        if seasons_box:
            for li in seasons_box.select("div.SeasonsBoxUL ul li"):
                season_id = li.get('data-serie')
                if not season_id:
                    continue
                season_title = li.get_text(strip=True) or f"Season {season_id.zfill(2)}"
                container = soup.select_one(f"div.SeasonsEpisodes[data-serie='{season_id}']")
                if not container:
                    continue
                eps = []
                for a in container.select("a[href]"):
                    href = a.get('href')
                    if not href:
                        continue
                    full_url = urljoin(self.base_url, href)
                    title = a.get('title') or a.get_text(strip=True)
                    em = a.select_one("em")
                    ep_num = em.get_text(strip=True) if em else ''
                    if not ep_num:
                        m = re.search(r'(\d+)', title)
                        if m:
                            ep_num = m.group(1).zfill(2)
                    eps.append({'number': ep_num.zfill(2) if ep_num else '?', 'title': title, 'url': full_url})
                if eps:
                    eps.sort(key=lambda x: int(x['number']) if x['number'].isdigit() else 0)
                    seasons[season_title] = eps
        if not seasons:
            title = soup.select_one("div.pm-video-heading h1")
            title = title.get_text(strip=True) if title else "فيلم"
            seasons['Season 01'] = [{'number': '01', 'title': title, 'url': url}]
        return seasons

    def get_servers(self, episode_url):
        servers = []
        urls_to_try = [episode_url]
        if 'play.php' not in episode_url:
            play_url = episode_url.replace('watch.php', 'play.php')
            if play_url != episode_url:
                urls_to_try.append(play_url)
            sep = '&' if '?' in episode_url else '?'
            urls_to_try.append(f"{episode_url}{sep}play=1")
        for url in urls_to_try:
            html = self._fetch(url)
            if not html:
                continue
            soup = BeautifulSoup(html, 'lxml')
            for btn in soup.select("div#WatchServers button.watchButton, div#WatchServers button.watchbutton"):
                embed = btn.get('data-embed-url') or btn.get('data-embed')
                if embed:
                    name = btn.get_text(strip=True) or 'سيرفر'
                    if embed not in [s['embed_url'] for s in servers]:
                        servers.append({'name': name, 'embed_url': embed})
            if not servers:
                for iframe in soup.find_all('iframe'):
                    src = iframe.get('src')
                    if src and src.startswith('http') and src not in [s['embed_url'] for s in servers]:
                        servers.append({'name': 'Iframe', 'embed_url': src})
            if servers:
                break
        return servers
