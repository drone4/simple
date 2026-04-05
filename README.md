# MyAgent (Local-first Telegram Agent)

Production-style Python agent designed for Apple Silicon macOS with local Ollama routing, safe tooling, local retrieval, and Telegram-first UX.

## Project tree

```text
myagent/
  .env.example
  pyproject.toml
  README.md
  launchd/com.ujuj889.myagent.plist
  prompts/
  src/myagent/
  tests/
  data/  logs/
  improvement_suggestions/ postmortems/ runtime_diagnostics/
```

## Architecture (brief)

- **Telegram-first control plane** (`telegram_bot.py`) with allowlisted users and concise response defaults.
- **Task router** (`router.py`) classifies prompts into task classes and sets model/tools/style/token budget/timeouts.
- **Ollama client** (`ollama_client.py`) does retries + graceful fallback.
- **Safety + approvals** (`safety.py`, `approvals.py`) gates risky actions with `approval_required=True` behavior.
- **State + jobs** (`state.py`, `scheduler.py`) use SQLite + APScheduler.
- **Tools** (`tools/`) for browser, RAG, email, crypto intelligence.
- **Skills + subagents** (`skills/`, `subagents/`) for modular capability registration.

## Setup

1. Create project in `~/agents/myagent` and copy files.
2. Create venv using Python 3.14:
   ```bash
   cd ~/agents/myagent
   python3.14 -m venv .venv
   source .venv/bin/activate
   pip install -U pip
   pip install -e .
   ```
3. Copy env file and edit secrets:
   ```bash
   cp .env.example .env
   ```
4. Optional browser support:
   ```bash
   pip install -e .[browser]
   playwright install chromium
   ```
5. Start locally (polling default):
   ```bash
   python -m myagent.main
   ```

## Telegram commands

`/help /status /models /mode /memory /skills /browse /rag /email /crypto /ai /reload /logs`

## Launchd install (macOS)

```bash
cp launchd/com.ujuj889.myagent.plist ~/Library/LaunchAgents/
# edit YOUR_USER placeholders first
launchctl unload ~/Library/LaunchAgents/com.ujuj889.myagent.plist 2>/dev/null || true
launchctl load ~/Library/LaunchAgents/com.ujuj889.myagent.plist
launchctl start com.ujuj889.myagent
```

## Ollama persistence recommendation

Use Ollama as a separate service/app and keep `OLLAMA_BASE_URL=http://127.0.0.1:11434` in `.env`.
Do not rely on `OLLAMA_HOST` inside Python code.

## Safety boundaries

- No autonomous trading or wallet signing.
- No destructive self-modification.
- High-risk actions require approval records in SQLite.

## Manual TODO

- Fill all secrets in `.env`.
- Replace `YOUR_USER` in launchd plist.
- Set `TELEGRAM_BOT_TOKEN` and `TELEGRAM_ALLOWED_USERS=1249515105`.
- Validate Ollama models are pulled.

