from __future__ import annotations

import aiosqlite


class ApprovalStore:
    def __init__(self, db_path: str) -> None:
        self.db_path = db_path

    async def init(self) -> None:
        async with aiosqlite.connect(self.db_path) as db:
            await db.execute(
                """
                CREATE TABLE IF NOT EXISTS approvals (
                  id INTEGER PRIMARY KEY AUTOINCREMENT,
                  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                  action TEXT NOT NULL,
                  requester TEXT NOT NULL,
                  status TEXT NOT NULL
                )
                """
            )
            await db.commit()

    async def create(self, action: str, requester: str) -> int:
        async with aiosqlite.connect(self.db_path) as db:
            cur = await db.execute(
                "INSERT INTO approvals(action, requester, status) VALUES(?,?,?)",
                (action, requester, "pending"),
            )
            await db.commit()
            return int(cur.lastrowid)
