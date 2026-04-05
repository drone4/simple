from __future__ import annotations

from dataclasses import dataclass

from myagent.tools.dexscreener import DexScreenerClient
from myagent.tools.helius import HeliusClient


@dataclass
class TokenScore:
    symbol: str
    score: float
    confidence: float
    reasons: list[str]


class CryptoMonitor:
    def __init__(self, helius: HeliusClient, dex: DexScreenerClient) -> None:
        self.helius = helius
        self.dex = dex

    async def scan(self) -> list[TokenScore]:
        data = await self.dex.latest_boosted()
        results: list[TokenScore] = []
        for item in data[:20] if isinstance(data, list) else []:
            symbol = item.get("tokenSymbol", "UNK")
            liquidity = float(item.get("totalAmount", 0.0) or 0.0)
            score = min(100.0, liquidity / 1000)
            reasons = ["Dex boosted feed"]
            if liquidity < 500:
                reasons.append("Low liquidity red flag")
                score -= 20
            results.append(TokenScore(symbol=symbol, score=max(score, 0), confidence=0.45, reasons=reasons))
        return results
