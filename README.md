# Foundation: Frontdesk AI

A minimal, accessible chat and contact widget for WordPress. Self-hosted, fast, and
private — route messages to an inbox or to an optional AI assistant.

Part of the Foundation plugin series by Inkfire Limited.

> ## ⚠️ This repository does not match production
>
> As of 2026-08-05 there is a divergence that needs resolving before this repository
> should be treated as the source of truth.
>
> | | This repository | Live on inkfire.co.uk |
> |---|---|---|
> | Plugin folder | `foundation-frontdesk-ai` | `Foundation-Frontdesk-v3` |
> | Main file | `foundation-conversa.php` | `foundation-frontdesk.php` |
> | Version | 1.0.12 (latest release) | **4.0.0-beta.4** |
> | Auto-updater | Plugin Update Checker, wired | **None** |
>
> Production is running a v3/v4 rewrite that has never been committed here, and that
> rewrite has **no update channel at all** — there is no way to push a fix to it
> remotely. Meanwhile `readme.txt` in this repository advertises `Stable tag: 1.0.13`
> while the newest published release is `1.0.12`, so even the old lineage cannot update.
>
> **Decide one of:**
> 1. This repository is retired and Frontdesk v3/v4 gets its own repository; or
> 2. Frontdesk v3/v4 is committed here as a major version bump, superseding the 1.x line.
>
> Either way the live plugin needs an updater wired in before it can be maintained.

## What the 1.x line in this repository does

- Accessible chat and contact widget with a self-hosted message store
- Routes conversations to an inbox, or to an optional AI assistant with RAG actions
- Flow builder for scripted conversational paths
- Registers with Foundation Core for diagnostics, health checks, and safe-mode isolation
- Admin UI built on the shared Foundation admin shell

## Status of this repository

| | |
|---|---|
| Version here | 1.0.12 (`readme.txt` claims 1.0.13) |
| Requires WordPress | 5.5+ |
| Tested up to | 6.9 |
| Licence | GPLv2 or later |
| Main file | `foundation-conversa.php` |

`foundation-conversa.php` is the original filename from when the plugin was called
Conversa. Renaming it changes the plugin's identity in WordPress and breaks updates for
every existing 1.x install, so it must not be renamed casually.

## Updates

The 1.x line bundles [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker)
and tracks GitHub releases here.

### Release process

1. Bump the version header in the main file **and** the `Stable tag` in `readme.txt`.
2. Commit and push.
3. Create and push a tag matching that version.
4. Publish a GitHub release with the plugin ZIP attached.

**The `Stable tag` and the published release tag must match.** They currently do not —
`readme.txt` says 1.0.13, newest release is 1.0.12 — which is why no 1.x site can update.

## Licence

GPLv2 or later. See [LICENSE](LICENSE).
