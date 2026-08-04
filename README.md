# Foundation: Frontdesk

An accessible, self-hosted chat and contact widget for WordPress. Routes messages to an
inbox or to an optional AI assistant — fast, private, no third-party chat service.

Part of the Foundation plugin series by Inkfire Limited.

## Status

| | |
|---|---|
| Current version | 4.0.0-beta.4 |
| Licence | GPLv2 or later |
| Main file | `foundation-frontdesk.php` |
| Installed folder | `Foundation-Frontdesk-v3` |
| Auto-updater | **none wired — see below** |

## History: this repository was renamed

This plugin began as **Conversa** (`foundation-conversa.php`), which reached 1.0.12. That
line was scrapped and rewritten as **Frontdesk**, currently 4.0.0-beta.4 with
`foundation-frontdesk.php` as the bootstrap.

`main` now tracks the Frontdesk lineage, synced from the live installation on
inkfire.co.uk. The final state of the old Conversa 1.x code is preserved on the
`pre-live-sync-20260805` branch if it is ever needed.

No site was running the 1.x line at the time of the switch, so nothing was stranded by
the rename.

## ⚠️ No update channel

This plugin currently has **no auto-updater**. There is no `plugin-update-checker`, no
`Update URI`, and no release has ever been published for the 4.x line. A fix cannot be
pushed to any site running it — updates must be installed by hand.

Wiring this up requires:

1. Bundling `plugin-update-checker` (see `foundation-ssh-access` for the pattern).
2. Adding an `Update URI` header pointing at `Inkfire-limited/foundation-frontdesk-ai`.
3. Publishing a release with a ZIP asset whose top-level directory is
   `Foundation-Frontdesk-v3`, matching the installed folder name — otherwise WordPress
   will install a second copy rather than updating in place.

Note the installed folder (`Foundation-Frontdesk-v3`) differs from both the repository
name and the plugin slug. Worth normalising when the updater is added.
