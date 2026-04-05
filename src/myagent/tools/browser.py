from __future__ import annotations

import logging
from dataclasses import dataclass

import httpx
from bs4 import BeautifulSoup

logger = logging.getLogger(__name__)


@dataclass
class BrowserResult:
    url: str
    title: str
    text: str


class BrowserTool:
    def __init__(self, enabled: bool) -> None:
        self.enabled = enabled

    async def fetch_lightweight(self, url: str) -> BrowserResult:
        async with httpx.AsyncClient(timeout=20, follow_redirects=True) as client:
            resp = await client.get(url)
            resp.raise_for_status()
        soup = BeautifulSoup(resp.text, "html.parser")
        title = (soup.title.string or "") if soup.title else ""
        text = " ".join(soup.get_text(" ", strip=True).split()[:1200])
        return BrowserResult(url=url, title=title, text=text)

    async def playwright_extract(self, url: str) -> BrowserResult:
        if not self.enabled:
            raise RuntimeError("Browser automation disabled")
        try:
            from playwright.async_api import async_playwright
        except Exception as exc:
            raise RuntimeError("Playwright not installed") from exc

        async with async_playwright() as pw:
            browser = await pw.chromium.launch(headless=True)
            page = await browser.new_page()
            await page.goto(url, wait_until="domcontentloaded", timeout=30_000)
            title = await page.title()
            text = (await page.inner_text("body"))[:5000]
            await browser.close()
        return BrowserResult(url=url, title=title, text=text)
