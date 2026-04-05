from __future__ import annotations

import aiosqlite


class StateStore:
    def __init__(self, db_path: str) -> None:
        self.db_path = db_path

    async def init(self) -> None:
        async with aiosqlite.connect(self.db_path) as db:
            await db.executescript(
                """
                CREATE TABLE IF NOT EXISTS events (
                  id INTEGER PRIMARY KEY AUTOINCREMENT,
                  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                  event_type TEXT NOT NULL,
                  payload TEXT NOT NULL
                );
                CREATE TABLE IF NOT EXISTS jobs (
                  id INTEGER PRIMARY KEY AUTOINCREMENT,
                  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                  job_name TEXT NOT NULL,
                  state TEXT NOT NULL,
                  attempts INTEGER DEFAULT 0,
                  next_run_at TEXT
                );
                CREATE TABLE IF NOT EXISTS memory (
                  key TEXT PRIMARY KEY,
                  value TEXT NOT NULL,
                  updated_at TEXT DEFAULT CURRENT_TIMESTAMP
                );
                """
            )
            await db.commit()
