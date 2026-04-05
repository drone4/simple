from __future__ import annotations

import httpx


class DexScreenerClient:
    async def latest_boosted(self) -> dict:
        async with httpx.AsyncClient(timeout=15) as client:
            resp = await client.get("https://api.dexscreener.com/token-boosts/latest/v1")
            resp.raise_for_status()
            return resp.json()
