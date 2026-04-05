from __future__ import annotations

import httpx


class HeliusClient:
    def __init__(self, api_key: str) -> None:
        self.api_key = api_key

    async def token_metadata(self, mint: str) -> dict:
        if not self.api_key:
            return {"warning": "HELIUS_API_KEY not configured"}
        url = f"https://api.helius.xyz/v0/token-metadata?api-key={self.api_key}"
        async with httpx.AsyncClient(timeout=20) as client:
            resp = await client.post(url, json={"mintAccounts": [mint]})
            resp.raise_for_status()
            return resp.json()
