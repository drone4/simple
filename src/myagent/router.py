from __future__ import annotations

from dataclasses import dataclass
from enum import Enum

from myagent.config import Settings


class TaskClass(str, Enum):
    GENERAL = "general"
    CODING = "coding"
    RESEARCH = "research"
    ACADEMIC = "academic"
    RETRIEVAL = "retrieval"
    BROWSER = "browser"
    CRYPTO = "crypto"
    ADMIN = "admin"
    FAST_CHAT = "fast-chat"


@dataclass
class RouteDecision:
    task_class: TaskClass
    model: str
    use_tools: bool
    style: str
    max_tokens: int
    minimize_reasoning: bool
    timeout_seconds: int


class Router:
    def __init__(self, settings: Settings) -> None:
        self.settings = settings

    def decide(self, prompt: str, mode: str = "fast") -> RouteDecision:
        p = prompt.lower().strip()

        if len(p) < 50 and any(k in p for k in ["hi", "hello", "joke", "thanks", "ping"]):
            return RouteDecision(TaskClass.FAST_CHAT, self.settings.ollama_model_fast, False, "concise", 180, True, 15)

        categories = {
            TaskClass.CODING: ["python", "code", "sql", "notebook", "script", "bug", "refactor"],
            TaskClass.RETRIEVAL: ["document", "file", "rag", "search notes", "citation"],
            TaskClass.BROWSER: ["browse", "website", "playwright", "screenshot", "click"],
            TaskClass.CRYPTO: ["solana", "token", "dex", "helius", "crypto"],
            TaskClass.RESEARCH: ["research", "compare", "analyze", "survey"],
            TaskClass.ACADEMIC: ["paper", "citation", "methodology", "literature"],
            TaskClass.ADMIN: ["status", "reload", "logs", "config", "launchd"],
        }

        scored: list[tuple[TaskClass, int]] = []
        for task, keywords in categories.items():
            score = sum(2 for kw in keywords if kw in p)
            if score:
                scored.append((task, score))

        if not scored:
            task_class = TaskClass.GENERAL
        else:
            task_class = sorted(scored, key=lambda x: x[1], reverse=True)[0][0]

        return self._build_decision(task_class, mode)

    def _build_decision(self, task_class: TaskClass, mode: str) -> RouteDecision:
        deep = mode == "deep"
        timeout = self.settings.default_timeout_seconds + (30 if deep else 0)

        if task_class == TaskClass.CODING:
            return RouteDecision(task_class, self.settings.ollama_model_coder, True, "technical", 1200 if deep else 700, False, timeout)
        if task_class in {TaskClass.RESEARCH, TaskClass.ACADEMIC}:
            return RouteDecision(task_class, self.settings.ollama_model_general, True, "analytical", 1300 if deep else 800, False, timeout)
        if task_class == TaskClass.RETRIEVAL:
            return RouteDecision(task_class, self.settings.ollama_model_general, True, "cited", 900 if deep else 500, True, timeout)
        if task_class in {TaskClass.BROWSER, TaskClass.CRYPTO, TaskClass.ADMIN}:
            return RouteDecision(task_class, self.settings.ollama_model_general, True, "summary", 900 if deep else 600, True, timeout)
        return RouteDecision(task_class, self.settings.ollama_model_general, False, "concise", 400 if deep else 220, True, timeout)
