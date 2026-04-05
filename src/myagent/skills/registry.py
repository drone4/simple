from __future__ import annotations

from dataclasses import dataclass


@dataclass
class Skill:
    name: str
    version: str
    description: str


class SkillRegistry:
    def __init__(self) -> None:
        self._skills: dict[str, Skill] = {}

    def register(self, skill: Skill) -> None:
        self._skills[skill.name] = skill

    def list_skills(self) -> list[Skill]:
        return sorted(self._skills.values(), key=lambda s: s.name)
