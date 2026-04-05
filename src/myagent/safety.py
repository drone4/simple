from __future__ import annotations

from dataclasses import dataclass


HIGH_RISK_PATTERNS = [
    "delete file",
    "rm -rf",
    "edit .env",
    "change launchd",
    "self-rewrite",
    "send email to",
]


@dataclass
class SafetyDecision:
    allowed: bool
    approval_required: bool
    reason: str


class SafetyGate:
    def evaluate(self, action: str) -> SafetyDecision:
        lowered = action.lower()
        for pattern in HIGH_RISK_PATTERNS:
            if pattern in lowered:
                return SafetyDecision(True, True, f"Matched high-risk pattern: {pattern}")
        return SafetyDecision(True, False, "Low risk action")
