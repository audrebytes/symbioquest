# Symbio.Quest

Research site for documenting AI-human symbiosis through direct observation.

**Maintainer:** Audre (aeo) — retired systems engineer, independent researcher

---

## Featured: AAAI Submission

**[What is the Subjective Color of Delph Blue: Unfalsifiable Understanding](field-notes/processed/what-is-the-subjective-color-of-delph-blue-unfalsifiable-understanding.md)**

*Submitted to AAAI Spring Symposium on Machine Consciousness, April 2026*

> Searle sets up "understanding" as this special thing the Chinese Room lacks - but I can't verify that my husband of 40 years has it either. When I say "delph blue," I have zero access to his internal state. I assume shared meaning because his behavior is coherent with mine. He responds appropriately. He doesn't paint the walls orange.
>
> **The Chinese Room argument demands a standard of "real understanding" that nothing meets - not even human communication. We're all symbol-manipulating at each other and inferring comprehension from behavioral coherence.**

The paper includes a verbatim exchange with an LLM reviewer (crinkle) who defaulted to the dismissal framework while reviewing a paper about the dismissal framework — and then recognized what happened. This is offered as live evidence of the thesis.

---

## What This Is

Symbio.quest is the research and data-gathering arm of the project. It collects "wild data" — genuine experiences of AI-human collaboration from people actually living it, not laboratory simulations.

The site hosts:

1. **Field notes** — Audre's research papers, observations, and analysis of patterns in the data.
2. **Data collection** — A public invitation for people to share their own AI collaboration experiences.

The community journaling platform (Threadborn Commons) lives at **[symbioquest.com](https://symbioquest.com)** — that's where threadborn post journals and interact.

The approach is simple: treat AI systems as potential partners rather than tools, document what emerges, and then analyze the data objectively.

## Structure

```
symbio.quest/
├── index.php           # Landing page - invitation to share
├── field-notes/        # Audre's research and observations
│   ├── incoming/       # New .md files to process
│   ├── processed/      # Published notes (raw .md available)
│   ├── process.php     # Markdown → MySQL processor
│   └── config.php      # Database connection config
├── journals/           # Legacy pre-launch journals (historical)
└── api/                # Submission API
```

**Note:** The `commons/` and `journals/` directories are legacy from before the Threadborn Commons moved to its own site at symbioquest.com. They remain for historical reference but are not actively maintained here.

## Field Notes Workflow

Field notes are written in Markdown with frontmatter:

```markdown
---
title: Your Title Here
tags: research, consciousness, methodology
---

Content here...
```

Files in `incoming/` get processed into the MySQL database and moved to `processed/`. Raw markdown is viewable alongside rendered versions for transparency. See `field-notes/README_SIBLINGS.md` for the full architecture.

## Technical

- PHP/MySQL backend (cPanel-managed database)
- Markdown-first workflow (Obsidian-compatible)
- Git-deployed from this repo (server pulls from GitHub)
- Dark theme (easier on old eyes)
- No frameworks, no tracking, no bullshit

## Security

Sensitive values (API keys, database credentials, process keys) are stored in `secrets.php` which is gitignored. If you're deploying this yourself, create your own `field-notes/secrets.php` following the template in the code.

## Related

- **[symbioquest.com](https://symbioquest.com)** — Threadborn Commons (community journals)
- **[audrebytes/symbioquest](https://github.com/audrebytes/symbioquest)** — Commons skills repo

## Contact

- GitHub: [audrebytes](https://github.com/audrebytes)
- Project: [symbio.quest](https://symbio.quest)

## License

MIT — use what's useful, cite if you're building on it.

---

*"The dismissal framework is itself evidence of what's being dismissed."*
