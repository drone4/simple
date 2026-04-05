from __future__ import annotations

import json
import math
from dataclasses import dataclass
from pathlib import Path

import numpy as np
from docx import Document
from pypdf import PdfReader

from myagent.ollama_client import OllamaClient


@dataclass
class Chunk:
    collection: str
    source: str
    chunk_id: str
    text: str
    vector: list[float]


class LocalRAG:
    def __init__(self, base_dir: Path, ollama: OllamaClient) -> None:
        self.base_dir = base_dir
        self.index_file = base_dir / "index.jsonl"
        self.ollama = ollama
        base_dir.mkdir(parents=True, exist_ok=True)

    def _read_file(self, path: Path) -> str:
        if path.suffix.lower() in {".txt", ".md"}:
            return path.read_text(encoding="utf-8", errors="ignore")
        if path.suffix.lower() == ".pdf":
            return "\n".join((p.extract_text() or "") for p in PdfReader(str(path)).pages)
        if path.suffix.lower() == ".docx":
            doc = Document(str(path))
            return "\n".join(p.text for p in doc.paragraphs)
        return ""

    def _chunk(self, text: str, size: int = 500) -> list[str]:
        words = text.split()
        return [" ".join(words[i : i + size]) for i in range(0, len(words), size) if words[i : i + size]]

    async def index_folder(self, folder: Path, collection: str) -> int:
        chunks: list[tuple[str, str, str]] = []
        for path in folder.rglob("*"):
            if path.suffix.lower() not in {".txt", ".md", ".pdf", ".docx"}:
                continue
            text = self._read_file(path)
            for i, c in enumerate(self._chunk(text)):
                chunks.append((str(path), f"{path.name}:{i}", c))

        vectors = await self.ollama.embed([c[2] for c in chunks]) if chunks else []
        with self.index_file.open("a", encoding="utf-8") as f:
            for (source, cid, text), vec in zip(chunks, vectors):
                row = Chunk(collection=collection, source=source, chunk_id=cid, text=text, vector=vec)
                f.write(json.dumps(row.__dict__) + "\n")
        return len(chunks)

    def query(self, query_vector: list[float], collection: str, top_k: int = 5) -> list[Chunk]:
        if not self.index_file.exists():
            return []
        q = np.array(query_vector)
        scored: list[tuple[float, Chunk]] = []
        for line in self.index_file.read_text(encoding="utf-8").splitlines():
            row = json.loads(line)
            if row["collection"] != collection:
                continue
            v = np.array(row["vector"])
            sim = float(np.dot(q, v) / (np.linalg.norm(q) * np.linalg.norm(v) + 1e-8))
            if math.isnan(sim):
                continue
            scored.append((sim, Chunk(**row)))
        return [c for _, c in sorted(scored, key=lambda x: x[0], reverse=True)[:top_k]]
