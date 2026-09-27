
## AI tooling available in this project

This project has three MCP servers registered (user scope, available in any Claude Code session on this machine):

## RAG Integration Rules

The project uses a configurable-tier RAG system via Open WebUI. MCP tools are available:
`rag_search`, `rag_add_doc`, `rag_add_issue`, `rag_index_project`, `rag_list_kbs`.

### Tier knowledge structure

| Tier | KB name | Contains |
|------|---------|---------|
| 1 | `framework-{name}` | Framework/CMS reference — hooks, APIs, services, patterns |
| 2 | `project-{slug}` | Per-project source code + project-specific devops/config |
| 3 | `common-issues` | Cross-cutting gotchas, bugs, non-obvious fixes (all stacks) |
| 4 | `devops-general` | Infrastructure reference — Docker, k8s, nginx, SSL (platform-agnostic) |
| 5 | `os-{distro}` | OS-specific fixes — package names, firewall rules, SELinux, distro quirks |

This project's code lives in `project-{slug}` (Tier 2). Replace `{slug}` with the
directory basename of this repository.

### Search behaviour

- **Default search** (`rag_search` with no `tiers` arg): searches Tiers 1–3
- **Tier 5 (`os-{distro}`)**: always auto-included in every search when the KB exists — no action needed
- **Tier 4 (`devops-general`)**: opt-in — pass `tiers=[..., "devops-general"]` for infra questions
- **Passing `tiers=[...]` replaces the defaults, it does not add to them.** Include all tiers you want:

```python
# Extend defaults to include Tier 4
rag_search(query="nginx proxy config", tiers=["framework", "project", "common-issues", "devops-general"])
```

### Excluded paths — never read directly

Do not read or analyze these paths. Use `rag_search(tier="framework")` instead:
- `vendor/`
- `web/core/`
- `node_modules/`
- `var/cache/`

### Rules

- **Search RAG first** before any coding task: `rag_search(query, framework=<detected>, project=<detected>)`
- **Default tiers**: `["framework", "project", "common-issues"]` — add `"devops-general"` for infrastructure topics
- **After solving something non-obvious**: call `rag_add_issue()` immediately with tags
- **After significant file changes**: call `rag_add_doc(tier="project")` with a descriptive name
- **Session summaries**: generate and upload via `rag_add_doc(tier="project")` at session end

### Web interfaces

- Open WebUI KB browser: http://localhost:3000 → Workspace → Knowledge
- Qdrant dashboard: http://localhost:6333/dashboard

## Semantic code search (qdrant-code-search)

This project's `src/` is indexed into Qdrant for semantic search — comments
stripped, chunked per-function/method, with `file_path`/`start_line`/
`end_line` metadata. Use the `qdrant-find` MCP tool to find relevant code by
*meaning*, not just filename, before reading files blind.

```
qdrant-find(query="how is tenant isolation enforced on repositories", collection_name="project-dev-toolkit-bundle-src")
```

- **Always pass `collection_name="project-dev-toolkit-bundle-src"` explicitly** — this
  server has no fixed collection, since it's shared across multiple projects.
- Results include the source file path and line range — use those to decide
  what to actually open with `Read`, rather than reading broadly first.
- This is `src/` code only (comment-stripped, PHP-specific chunking) — for
  docs, framework reference, or cross-project knowledge, use `rag_search`
  (rag-stack) instead, not this tool.
- Re-run ingestion after significant source changes — there's no
  auto-reindex: `php ~/Projects/qdrant-code-search/ingest.php <path-to-src> project-dev-toolkit-bundle-src --clear`

## Local task delegation (ollama-mcp-bridge)

A local Ollama model (`UD-Q4_K_XL`) can run its own read/write/list/grep
loop against this project, scoped to `project_root`, for routine work that
doesn't need Claude's own reasoning — use it to save context/cost on
mechanical tasks, not for anything requiring judgment calls or architectural
decisions.

```
delegate_task(
    task="<a self-contained, well-scoped task>",
    project_root="/home/dktaylor/Projects/dev-toolkit-bundle",
)
```

- **Good fits**: repetitive mechanical edits, applying an already-decided
  pattern across several files, straightforward boilerplate generation,
  summarizing a directory's contents.
- **Bad fits**: anything needing architectural judgment, ambiguous
  requirements, or security-sensitive changes — do those yourself.
- Every path is hard-scoped to `project_root` — the model cannot read or
  write outside this project.
- **Known model quirks** (not bugs in the bridge itself): it can
  occasionally return an empty response (auto-retried, but can still
  exhaust retries on a bad-luck run) or hit an iteration cap without
  concluding. Both fail loudly with an `Error:` string, never silently — if
  you get one, just retry the task once before assuming something's wrong.

### With retrieved context (apply_with_context)

When you already know which specific file(s) should change and want
relevant code patterns from this project pulled in first:

```
apply_with_context(
    task="<what to apply, e.g. 'add tenant isolation the same way X does it'>",
    active_files=["path/relative/to/project_root.php"],
    project_root="/home/dktaylor/Projects/dev-toolkit-bundle",
    qdrant_collection="project-dev-toolkit-bundle-src",
)
```

- Looks up relevant code via Qdrant first (see qdrant-code-search's own
  CLAUDE.md section), then applies the task to `active_files` specifically.
- **Writes are restricted to `active_files` only** — it can still read
  elsewhere under `project_root` for context (e.g. a base class), but
  cannot edit anything not explicitly listed.
