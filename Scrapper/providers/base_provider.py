from abc import ABC, abstractmethod
from typing import List, Dict, Optional
import cloudscraper
import logging
import requests
from utils.cloudflare import create_cloudscraper_session, solve_cloudflare_with_selenium, is_cloudflare_blocked

class BaseProvider(ABC):
    name: str = "Base"
    base_url: str = ""
    domains: List[str] = []
    
    def __init__(self):
        self.session = create_cloudscraper_session()
        self.cookies = {}
        self.headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            'Accept-Language': 'ar,en-US;q=0.9,en;q=0.8',
        }
        if self.domains:
            self.base_url = self._resolve_domain()
    
    def _resolve_domain(self) -> str:
        if not self.domains:
            return self.base_url
        for domain in self.domains:
            try:
                resp = self.session.get(domain, timeout=15, allow_redirects=True)
                if is_cloudflare_blocked(resp):
                    html, cookies = solve_cloudflare_with_selenium(domain)
                    self.cookies.update(cookies)
                    return resp.url.rstrip('/') if resp.url else domain
                if resp.status_code == 200:
                    from urllib.parse import urlparse
                    parsed = urlparse(resp.url)
                    return f"{parsed.scheme}://{parsed.netloc}"
            except Exception as e:
                logging.warning(f"فشل الاتصال بـ {domain}: {e}")
                continue
        return self.domains[0]
    
    def _fetch(self, url: str, timeout: int = 30, **kwargs) -> Optional[str]:
        try:
            resp = self.session.get(
                url, timeout=timeout, allow_redirects=True,
                headers=self.headers, cookies=self.cookies, **kwargs
            )
            if is_cloudflare_blocked(resp):
                html, new_cookies = solve_cloudflare_with_selenium(url)
                self.cookies.update(new_cookies)
                return html
            if resp.status_code == 200:
                return resp.text
        except requests.exceptions.RequestException:
            try:
                html, new_cookies = solve_cloudflare_with_selenium(url)
                self.cookies.update(new_cookies)
                return html
            except Exception:
                return None
        except Exception:
            pass
        try:
            html, new_cookies = solve_cloudflare_with_selenium(url)
            self.cookies.update(new_cookies)
            return html
        except Exception:
            return None
    
    @abstractmethod
    def search(self, query: str) -> List[Dict]:
        pass
    
    @abstractmethod
    def get_details(self, url: str) -> Dict:
        pass
    
    @abstractmethod
    def get_episodes(self, url: str) -> Dict[str, List[Dict]]:
        pass
    
    @abstractmethod
    def get_servers(self, episode_url: str) -> List[Dict]:
        pass
    
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