from __future__ import annotations

import logging

from apscheduler.schedulers.asyncio import AsyncIOScheduler

logger = logging.getLogger(__name__)


class AgentScheduler:
    def __init__(self) -> None:
        self.scheduler = AsyncIOScheduler()

    def add_job(self, func, trigger: str, **kwargs) -> None:  # type: ignore[no-untyped-def]
        self.scheduler.add_job(func, trigger, **kwargs)

    def start(self) -> None:
        self.scheduler.start()
        logger.info("Scheduler started")

    def shutdown(self) -> None:
        self.scheduler.shutdown(wait=False)
        logger.info("Scheduler stopped")
