# Bulk Links

Tick multiple short links on [YOURLS](https://yourls.org)' own admin list and delete them all at once, plus a **Bulk Add Links** page to create many links from a pasted list. Pure additions to YOURLS' existing admin pages via its own plugin hooks — no settings, no external service, no separate process.

## Install

1. Drop this folder into `user/plugins/` in your YOURLS install, so you end up with `user/plugins/bulk-links/plugin.php`.
2. In YOURLS admin → **Manage Plugins**, **Activate** "Bulk Links".
3. That's it — no configuration needed.

## Bulk delete

On the main admin page (your list of links), every row now has a checkbox, plus a "select all" checkbox in the header and a **Delete selected (N)** button above the table. Tick the ones you want gone, click the button, confirm — they're deleted in one request and the page reloads.

## Bulk Add Links

A new "Bulk Add Links" page appears in the admin menu (from Manage Plugins → Bulk Links too). Paste one URL per line:

```
https://example.com
https://example.org mykeyword
```

A second word on a line (space-separated) sets a custom keyword for that link; otherwise YOURLS generates one. Submitting shows a result per line — green for created (with its resulting short URL), red for anything that failed (invalid URL, duplicate keyword, etc.).

## How it works

- The checkbox column and "select all"/delete toolbar are added via YOURLS' own `table_head_cells`, `table_add_row_cell_array` and `table_head_start` filters — the exact same mechanism third-party plugins have always used to extend that table, nothing undocumented or fragile.
- Deleting is one AJAX call to `admin-ajax.php`, which YOURLS core already dispatches unknown `action` values to a plugin-defined `yourls_ajax_<action>` hook — already behind YOURLS' own admin authentication, no custom auth code needed.
- Bulk Add uses `yourls_register_plugin_page()`, the same mechanism any settings-style plugin page uses (nonce-protected form, same as YOURLS' own sample plugin).

## Requirements

YOURLS 1.8+ (tested on 1.10.6).

## License

MIT.
