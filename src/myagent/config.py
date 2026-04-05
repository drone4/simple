from __future__ import annotations

from pathlib import Path
from typing import Literal

from pydantic import Field, field_validator
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", env_file_encoding="utf-8", extra="ignore")

    telegram_bot_token: str = Field(..., alias="TELEGRAM_BOT_TOKEN")
    telegram_allowed_users: str = Field(..., alias="TELEGRAM_ALLOWED_USERS")
    telegram_use_webhook: bool = Field(False, alias="TELEGRAM_USE_WEBHOOK")
    telegram_webhook_url: str = Field("", alias="TELEGRAM_WEBHOOK_URL")
    telegram_mode: Literal["fast", "deep"] = Field("fast", alias="TELEGRAM_MODE")

    ollama_base_url: str = Field("http://127.0.0.1:11434", alias="OLLAMA_BASE_URL")
    ollama_model_general: str = Field("qwen3.5:9b", alias="OLLAMA_MODEL_GENERAL")
    ollama_model_coder: str = Field("qwen2.5-coder:7b", alias="OLLAMA_MODEL_CODER")
    ollama_model_fast: str = Field("qwen3.5:4b", alias="OLLAMA_MODEL_FAST")
    ollama_model_embed: str = Field("bge-m3", alias="OLLAMA_MODEL_EMBED")
    ollama_model_uncensored: str = Field("dolphin-mistral:7b", alias="OLLAMA_MODEL_UNCENSORED")

    resend_api_key: str = Field("", alias="RESEND_API_KEY")
    resend_from: str = Field("", alias="RESEND_FROM")
    alert_email_to: str = Field("", alias="ALERT_EMAIL_TO")
    alert_email_allowlist: str = Field("", alias="ALERT_EMAIL_ALLOWLIST")

    helius_api_key: str = Field("", alias="HELIUS_API_KEY")

    data_dir: Path = Field(Path("./data"), alias="DATA_DIR")
    log_level: str = Field("INFO", alias="LOG_LEVEL")
    enable_browser: bool = Field(False, alias="ENABLE_BROWSER")
    enable_uncensored_fallback: bool = Field(False, alias="ENABLE_UNCENSORED_FALLBACK")
    enable_self_improve: bool = Field(True, alias="ENABLE_SELF_IMPROVE")
    enable_crypto: bool = Field(True, alias="ENABLE_CRYPTO")
    enable_ai_digest: bool = Field(True, alias="ENABLE_AI_DIGEST")

    max_tool_calls_per_task: int = Field(8, alias="MAX_TOOL_CALLS_PER_TASK")
    default_timeout_seconds: int = Field(60, alias="DEFAULT_TIMEOUT_SECONDS")

    @field_validator("telegram_allowed_users")
    @classmethod
    def validate_user_ids(cls, value: str) -> str:
        ids = [x.strip() for x in value.split(",") if x.strip()]
        if not ids:
            raise ValueError("TELEGRAM_ALLOWED_USERS cannot be empty")
        for uid in ids:
            if not uid.isdigit():
                raise ValueError(f"Invalid Telegram user id: {uid}")
        return value

    def allowed_user_set(self) -> set[int]:
        return {int(x.strip()) for x in self.telegram_allowed_users.split(",") if x.strip()}

    def alert_allowlist_set(self) -> set[str]:
        return {x.strip().lower() for x in self.alert_email_allowlist.split(",") if x.strip()}


def ensure_dirs(settings: Settings) -> None:
    for sub in ["", "rag", "state", "cache"]:
        (settings.data_dir / sub).mkdir(parents=True, exist_ok=True)


def validate_startup(settings: Settings) -> list[str]:
    issues: list[str] = []
    if settings.telegram_use_webhook and not settings.telegram_webhook_url:
        issues.append("TELEGRAM_WEBHOOK_URL is required when TELEGRAM_USE_WEBHOOK=true")
    if settings.resend_api_key and not settings.resend_from:
        issues.append("RESEND_FROM must be set when RESEND_API_KEY is provided")
    return issues
