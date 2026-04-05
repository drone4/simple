from __future__ import annotations

import logging
import time

from telegram import Update
from telegram.ext import Application, CommandHandler, ContextTypes, MessageHandler, filters

from myagent.approvals import ApprovalStore
from myagent.config import Settings
from myagent.ollama_client import OllamaClient
from myagent.router import Router
from myagent.safety import SafetyGate
from myagent.skills.registry import SkillRegistry

logger = logging.getLogger(__name__)


class TelegramAgent:
    def __init__(
        self,
        settings: Settings,
        router: Router,
        ollama: OllamaClient,
        approvals: ApprovalStore,
        safety_gate: SafetyGate,
        skills: SkillRegistry,
    ) -> None:
        self.settings = settings
        self.router = router
        self.ollama = ollama
        self.approvals = approvals
        self.safety_gate = safety_gate
        self.skills = skills
        self.started = time.time()
        self.last_errors: list[str] = []

    def app(self) -> Application:
        app = Application.builder().token(self.settings.telegram_bot_token).build()
        app.add_handler(CommandHandler("help", self.help_cmd))
        app.add_handler(CommandHandler("status", self.status_cmd))
        app.add_handler(CommandHandler("models", self.models_cmd))
        app.add_handler(CommandHandler("mode", self.mode_cmd))
        app.add_handler(CommandHandler("skills", self.skills_cmd))
        app.add_handler(MessageHandler(filters.TEXT & ~filters.COMMAND, self.on_message))
        return app

    async def _authorized(self, update: Update) -> bool:
        uid = update.effective_user.id if update.effective_user else 0
        if uid not in self.settings.allowed_user_set():
            if update.message:
                await update.message.reply_text("Unauthorized user")
            return False
        return True

    async def help_cmd(self, update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
        if not await self._authorized(update):
            return
        text = (
            "Commands: /help /status /models /mode /memory /skills /browse /rag /email /crypto /ai /reload /logs\n"
            "Use /mode fast|deep to switch response depth."
        )
        await update.message.reply_text(text)

    async def status_cmd(self, update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
        if not await self._authorized(update):
            return
        uptime = int(time.time() - self.started)
        ok = await self.ollama.health()
        msg = (
            f"Uptime: {uptime}s\n"
            f"Ollama: {'ok' if ok else 'down'}\n"
            f"Mode: {self.settings.telegram_mode}\n"
            f"Models: general={self.settings.ollama_model_general}, coder={self.settings.ollama_model_coder}, fast={self.settings.ollama_model_fast}\n"
            f"Last errors: {self.last_errors[-3:] if self.last_errors else 'none'}"
        )
        await update.message.reply_text(msg)

    async def models_cmd(self, update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
        if not await self._authorized(update):
            return
        await update.message.reply_text(
            f"general={self.settings.ollama_model_general}\n"
            f"coder={self.settings.ollama_model_coder}\n"
            f"fast={self.settings.ollama_model_fast}\n"
            f"embed={self.settings.ollama_model_embed}"
        )

    async def mode_cmd(self, update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
        if not await self._authorized(update):
            return
        if context.args and context.args[0] in {"fast", "deep"}:
            self.settings.telegram_mode = context.args[0]
            await update.message.reply_text(f"Mode set to {self.settings.telegram_mode}")
        else:
            await update.message.reply_text(f"Current mode: {self.settings.telegram_mode}")

    async def skills_cmd(self, update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
        if not await self._authorized(update):
            return
        txt = "\n".join(f"- {s.name} v{s.version}: {s.description}" for s in self.skills.list_skills())
        await update.message.reply_text(txt or "No skills registered")

    async def on_message(self, update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
        if not await self._authorized(update):
            return
        prompt = (update.message.text or "").strip()
        decision = self.router.decide(prompt, mode=self.settings.telegram_mode)

        safety = self.safety_gate.evaluate(prompt)
        if safety.approval_required:
            req_id = await self.approvals.create(prompt, str(update.effective_user.id))
            await update.message.reply_text(f"Approval required ({req_id}): {safety.reason}")
            return

        try:
            system_prefix = "Reply concisely in 1-3 sentences. " if decision.minimize_reasoning else ""
            answer = await self.ollama.generate_with_fallback(
                decision.model,
                system_prefix + prompt,
                decision.timeout_seconds,
                decision.max_tokens,
            )
            await update.message.reply_text(answer)
        except Exception as exc:
            logger.exception("Message handling failed")
            self.last_errors.append(str(exc))
            await update.message.reply_text("I hit an error. Please try again.")
