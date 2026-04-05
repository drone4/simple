from __future__ import annotations

import httpx


class ResendEmailTool:
    def __init__(self, api_key: str, sender: str, allowlist: set[str], dry_run: bool = False) -> None:
        self.api_key = api_key
        self.sender = sender
        self.allowlist = allowlist
        self.dry_run = dry_run

    async def send(self, to_email: str, subject: str, body: str) -> str:
        email = to_email.lower().strip()
        if email not in self.allowlist:
            raise PermissionError(f"Recipient not allowlisted: {email}")
        if self.dry_run or not self.api_key:
            return "dry-run"

        payload = {"from": self.sender, "to": [email], "subject": subject, "text": body}
        headers = {"Authorization": f"Bearer {self.api_key}", "Content-Type": "application/json"}
        async with httpx.AsyncClient(timeout=20) as client:
            resp = await client.post("https://api.resend.com/emails", json=payload, headers=headers)
            resp.raise_for_status()
            return resp.json().get("id", "sent")
