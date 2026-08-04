=== Foundation: Frontdesk AI ===
Contributors: inkfire
Tags: chat, contact form, helpdesk, inbox, accessibility, ai, widget
Requires at least: 5.5
Tested up to: 6.8.2
Stable tag: 4.0.0-beta.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A visitor-facing WordPress chatbot with floating and embedded widgets, OpenAI responses, offline fallback, site knowledge, and contact capture.

== Description ==
Foundation: Frontdesk AI is part of the Foundation plugin series by Inkfire Limited — a suite of modular, minimal tools for clean, performant WordPress sites.

This beta keeps Frontdesk AI focused on the customer-facing chatbot experience: a front-end widget, shortcode embeds, a setup wizard, OpenAI-first replies, offline fallback, FAQ/site knowledge, and contact capture.

== Shortcodes ==

Inline chatbox:
`[foundation_frontdesk]`

Header/menu launcher button:
`[foundation_frontdesk launcher="1" label="Ask us"]`

Legacy shortcode:
`[foundation_conversa]`

== Changelog ==

= 4.0.0-beta.4 =
* Polished the existing setup wizard instead of replacing it, with a clearer visitor-chatbot setup flow and preview/test actions.
* Simplified the main settings screen into a friendlier day-to-day view with an Advanced section for deeper controls.
* Added avatar/logo URL support to the guided setup and settings flow.
* Tightened the live chat shell with better accessibility semantics, disabled send state while replying, and safer DOM building.
* Added a reset wizard action and expanded uninstall cleanup for wizard and site knowledge status options.

= 4.0.0-beta.3 =
* Refocused the build around the visitor-facing chatbot/widget product instead of a wp-admin operations copilot.
* Preserved the existing Frontdesk setup wizard and settings architecture.
* Added header/menu launcher shortcode support: `[foundation_frontdesk launcher="1" label="Ask us"]`.
* Added placement controls to the setup wizard.
* Clarified admin copy around floating widgets, inline embeds, header launchers, OpenAI, and offline fallback.
* Kept OpenAI API key server-side and preserved offline fallback behaviour.

= 4.0.0-beta.2 =
* OpenAI-first runtime with offline fallback, site knowledge, and contact capture.
