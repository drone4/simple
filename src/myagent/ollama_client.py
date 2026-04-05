from __future__ import annotations

import logging
from typing import Any

import httpx
from tenacity import retry, stop_after_attempt, wait_exponential

from myagent.config import Settings

logger = logging.getLogger(__name__)


class OllamaClient:
    def __init__(self, settings: Settings) -> None:
        self.settings = settings
        self.base_url = settings.ollama_base_url.rstrip("/")

    @retry(stop=stop_after_attempt(3), wait=wait_exponential(multiplier=0.5, min=0.5, max=4))
    async def generate(self, model: str, prompt: str, timeout: int, max_tokens: int) -> str:
        payload: dict[str, Any] = {
            "model": model,
            "prompt": prompt,
            "stream": False,
            "options": {"num_predict": max_tokens},
        }
        async with httpx.AsyncClient(timeout=timeout) as client:
            resp = await client.post(f"{self.base_url}/api/generate", json=payload)
            resp.raise_for_status()
            return resp.json().get("response", "").strip()

    async def embed(self, texts: list[str]) -> list[list[float]]:
        vectors: list[list[float]] = []
        async with httpx.AsyncClient(timeout=90) as client:
            for text in texts:
                resp = await client.post(
                    f"{self.base_url}/api/embeddings",
                    json={"model": self.settings.ollama_model_embed, "prompt": text},
                )
                resp.raise_for_status()
                vectors.append(resp.json().get("embedding", []))
        return vectors

    async def health(self) -> bool:
        try:
            async with httpx.AsyncClient(timeout=5) as client:
                resp = await client.get(f"{self.base_url}/api/tags")
                return resp.status_code == 200
        except Exception:
            return False

    async def generate_with_fallback(self, primary_model: str, prompt: str, timeout: int, max_tokens: int) -> str:
        try:
            return await self.generate(primary_model, prompt, timeout, max_tokens)
        except Exception as exc:
            logger.warning("Primary model failed (%s): %s", primary_model, exc)

        fallback_models = [self.settings.ollama_model_fast]
        if self.settings.enable_uncensored_fallback:
            fallback_models.append(self.settings.ollama_model_uncensored)

        for model in fallback_models:
            try:
                return await self.generate(model, prompt, min(30, timeout), min(300, max_tokens))
            except Exception as exc:
                logger.warning("Fallback model failed (%s): %s", model, exc)

        raise RuntimeError("All Ollama model attempts failed")
