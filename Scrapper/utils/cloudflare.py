import cloudscraper
from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
import time
import logging

def create_cloudscraper_session():
    """إنشاء جلسة cloudscraper لتجاوز Cloudflare."""
    return cloudscraper.create_scraper(
        browser={'browser': 'chrome', 'platform': 'windows', 'mobile': False},
        delay=5,
        debug=False
    )

def create_cloudscraper_session_node():
    """إنشاء جلسة cloudscraper مع Node.js interpreter للتحديات الأقوى."""
    try:
        return cloudscraper.create_scraper(
            browser={'browser': 'chrome', 'platform': 'windows', 'mobile': False},
            delay=10,
            interpreter='node',
            debug=False
        )
    except:
        return create_cloudscraper_session()

def is_cloudflare_blocked(resp):
    """التحقق إذا كان الرد محجوباً من Cloudflare."""
    if resp is None:
        return True
    if resp.status_code == 403 and 'Just a moment' in resp.text[:500]:
        return True
    if resp.status_code == 503 and 'cf-content' in resp.text[:500]:
        return True
    return False

def solve_cloudflare_with_selenium(url: str, timeout: int = 60) -> str:
    """
    استخدام Selenium لحل تحدي Cloudflare (بما في ذلك Managed Challenge).
    يعيد (HTML الصفحة, dict الكوكيز) بعد الحل.
    """
    options = Options()
    options.add_argument("--headless=new")
    options.add_argument("--no-sandbox")
    options.add_argument("--disable-dev-shm-usage")
    options.add_argument("--disable-blink-features=AutomationControlled")
    options.add_experimental_option("excludeSwitches", ["enable-automation"])
    options.add_experimental_option('useAutomationExtension', False)
    options.add_argument("--window-size=1920,1080")
    options.add_argument("--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36")
    
    driver = webdriver.Chrome(options=options)
    try:
        # إخفاء webdriver
        driver.execute_cdp_cmd("Page.addScriptToEvaluateOnNewDocument", {
            "source": """
                Object.defineProperty(navigator, 'webdriver', {
                    get: () => undefined
                });
                Object.defineProperty(navigator, 'plugins', {
                    get: () => [1, 2, 3, 4, 5]
                });
                Object.defineProperty(navigator, 'languages', {
                    get: () => ['ar-EG', 'ar', 'en-US', 'en']
                });
            """
        })
        
        driver.get(url)
        
        # انتظار حتى تختفي صفحة Cloudflare أو ظهور محتوى حقيقي
        deadline = time.time() + timeout
        while time.time() < deadline:
            html = driver.page_source
            if 'Just a moment' not in html and 'cf-content' not in html:
                break
            time.sleep(2)
        
        cookies = {c['name']: c['value'] for c in driver.get_cookies()}
        html = driver.page_source
        driver.quit()
        return html, cookies
    except Exception as e:
        try:
            driver.quit()
        except:
            pass
        raise e