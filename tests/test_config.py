import pytest

from myagent.config import Settings, validate_startup


def test_allowed_user_validation() -> None:
    with pytest.raises(ValueError):
        Settings(TELEGRAM_BOT_TOKEN="x", TELEGRAM_ALLOWED_USERS="abc")


def test_webhook_validation() -> None:
    settings = Settings(
        TELEGRAM_BOT_TOKEN="x",
        TELEGRAM_ALLOWED_USERS="1",
        TELEGRAM_USE_WEBHOOK=True,
        TELEGRAM_WEBHOOK_URL="",
    )
    issues = validate_startup(settings)
    assert issues
