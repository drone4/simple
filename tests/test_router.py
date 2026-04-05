from myagent.config import Settings
from myagent.router import Router, TaskClass


def test_fast_chat_route() -> None:
    settings = Settings(TELEGRAM_BOT_TOKEN="x", TELEGRAM_ALLOWED_USERS="1")
    router = Router(settings)
    decision = router.decide("hi there")
    assert decision.task_class == TaskClass.FAST_CHAT
    assert decision.model == settings.ollama_model_fast


def test_coding_route() -> None:
    settings = Settings(TELEGRAM_BOT_TOKEN="x", TELEGRAM_ALLOWED_USERS="1")
    router = Router(settings)
    decision = router.decide("please refactor this python script")
    assert decision.task_class == TaskClass.CODING
    assert decision.use_tools is True
