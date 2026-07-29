<h1 align="center">
  Elementor AI Connector
</h1>

<div align="center">

[![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/imhocien/elementor-ai-connector/releases)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.1-8892BF.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-%3E%3D6.9-21759B.svg)](https://wordpress.org)

**Author: [Shahid Hussain](https://hocien.me)**

</div>

Turn your WordPress site into something an AI agent can actually operate.

Elementor AI Connector is a WordPress plugin that exposes your site as **MCP tools**, so Claude, Cursor, and any other MCP client can build Elementor pages, write content, manage plugins and users, audit performance and security, and drive the plugins you already run.

Forked from [EMCP Tools](https://github.com/msrbuilds/elementor-mcp) with all Pro/pricing/upgrade references removed.

## What it does

**Build pages.** The full Elementor workflow: containers, widgets, templates, global styles, and atomic elements for Elementor 4.0+. Also Gutenberg blocks, and a builder-agnostic theme builder for headers, footers, and archives.

**Run the site.** Content and taxonomies, media, users, settings, plugins and themes, nav menus, the filesystem, and the database, all over MCP.

**Understand and undo.** One-call page snapshots, content search across your own pages and templates, a change ledger with rollback, and read-only performance and security scans that return a scored report.

**Speak your plugins.** Integrations for ACF, Meta Box, WooCommerce, 8 form builders, 7 SEO plugins, and Elementor addon packs.

Elementor is **optional**. Every WordPress domain works without it; installing Elementor unlocks the page-building family.

## Install

1. Download the latest release from [Releases](https://github.com/imhocien/elementor-ai-connector/releases).
2. In WordPress: **Plugins → Add New → Upload Plugin**, then activate.
3. Open the **EMCP Tools** menu in the admin sidebar.

**Requires** WordPress 6.9+ and PHP 8.1+. Elementor 3.20+ is optional (4.0+ for atomic elements).

## Connect your AI client

The **Connection** tab in the admin generates a ready-to-paste config for your client, including a one-click `.mcpb` bundle for Claude Desktop.

## Safe by default

Every tool runs a real WordPress capability check before it does anything. Anything that writes, deletes, or renders site-wide **ships disabled** and is opt-in. Destructive operations require `confirm: true`. Administrators cannot be edited over MCP and there is no delete-user tool.

## Sample prompts

The [`prompts/`](prompts/) directory has five complete landing-page blueprints: [Local Business](prompts/LOCAL_BUSINESS.md), [Dental Clinic](prompts/DENTAL_CLINIC.md), [Developer Portfolio](prompts/WEB_DEVELOPER_PORTFOLIO.md), [Hair Salon](prompts/HAIR_SALON.md), [Car Wash](prompts/CAR_WASH.md).

## License

[GNU General Public License v2.0 or later](LICENSE).
