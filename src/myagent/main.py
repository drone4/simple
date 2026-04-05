from __future__ import annotations

import asyncio
import logging
from pathlib import Path

from myagent.approvals import ApprovalStore
from myagent.config import Settings, ensure_dirs, validate_startup
from myagent.logging_setup import setup_logging
from myagent.ollama_client import OllamaClient
from myagent.router import Router
from myagent.safety import SafetyGate
from myagent.scheduler import AgentScheduler
from myagent.skills import ai_digest_skill, browser_skill, crypto_skill, rag_skill, telegram_skill
from myagent.skills.registry import SkillRegistry
from myagent.state import StateStore
from myagent.telegram_bot import TelegramAgent

logger = logging.getLogger(__name__)


async def run() -> None:
    settings = Settings()
    ensure_dirs(settings)
    setup_logging(Path("logs"), settings.log_level)

    issues = validate_startup(settings)
    if issues:
        for issue in issues:
            logger.error("Config issue: %s", issue)
        raise SystemExit(1)

    state = StateStore(str(settings.data_dir / "state" / "agent.sqlite"))
    approvals = ApprovalStore(str(settings.data_dir / "state" / "agent.sqlite"))
    await state.init()
    await approvals.init()

    scheduler = AgentScheduler()
    scheduler.start()

    skills = SkillRegistry()
    for item in [telegram_skill.SKILL, browser_skill.SKILL, rag_skill.SKILL, crypto_skill.SKILL, ai_digest_skill.SKILL]:
        skills.register(item)

    ollama = OllamaClient(settings)
    router = Router(settings)
    gate = SafetyGate()

    bot = TelegramAgent(settings, router, ollama, approvals, gate, skills)
    app = bot.app()

    if settings.telegram_use_webhook:
        await app.initialize()
        await app.start()
        await app.updater.start_webhook(listen="127.0.0.1", port=8080, webhook_url=settings.telegram_webhook_url)
    else:
        await app.initialize()
        await app.start()
        await app.updater.start_polling(drop_pending_updates=True)

    logger.info("Agent started")
    try:
        while True:
            await asyncio.sleep(1)
    finally:
        scheduler.shutdown()
        await app.stop()
        await app.shutdown()


def main() -> None:
    asyncio.run(run())


if __name__ == "__main__":
    main()
