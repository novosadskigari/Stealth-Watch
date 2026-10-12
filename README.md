# Stealth Watch for Joomla

**Audit and protect plugin changes in the Joomla administrator.**

Stealth Watch records who disables, enables or uninstalls plugins, can stop selected plugins (such as the JCH Optimize page cache) from being switched off, and restricts its own settings to a list of trusted users.

---

## Why?

On sites with several administrators, a plugin sometimes gets switched off and nobody knows who did it or when. Joomla's built-in User Actions Log doesn't catch every route (some extensions write to the database directly), can't block the change, and can't restrict who manages the log.

Stealth Watch closes those gaps:

- **See** every plugin change, by whom, from where.
- **Prevent** critical plugins from being disabled.
- **Control** who can change the rules.

---

## Features

### Audit log
- Logs every plugin **disable**, **enable** and **uninstall**, plus changes to JCH Optimize cache-related settings.
- Each entry records date, action, plugin, username, email, IP address, client and request URL.
- Detects changes from the plugin list, plugin edit form, Extensions → Manage, AJAX requests and third-party dashboards that write to the database directly.
- Deleting, purging or clearing log entries is itself logged, so the log can't be wiped silently.
- Optional copy of every event to Joomla's **User Actions Log**.

### Conditional filtering
- Filter by **Action**, then **Plugin**, then **User**. Each dropdown only lists values that exist for the filters above it.
- Extra filters for **Type** (JCH page cache, JCH, other) and **Period** (24 hours, 7, 30 or 90 days).
- Free-text search across plugin, username, email and IP (`id:123` searches by entry ID).

### Retention
- Configurable **maximum number of entries** and **retention period**.
- Expired entries are purged automatically, with a manual **Purge Expired** button as well.

### Plugin protection
- Attempts to disable the **JCH Optimize page cache**, or to disable/uninstall the JCH Optimize component or package, are **blocked** before Joomla runs them.
- If a protected plugin is switched off by a route that can't be intercepted, it is **re-enabled** at the end of the same request.
- Any other plugin can be added to the protected list.

### Settings Managers
- An explicit list of users who can:
  - open and save Stealth Watch options and permissions,
  - disable or uninstall Stealth Watch itself,
  - delete log entries (optional),
  - deliberately disable protected plugins (optional, logged as **Manager override**).
- Everyone else, including Super Users not on the list, is refused and the attempt is logged.
- **Lockout safety:** if the list is empty or none of the listed accounts are active, Super Users regain access automatically.
- The user who installs the extension becomes the first Settings Manager.

### Works everywhere in the backend
- Supports Atum light and dark mode.
- Supports MySQL/MariaDB and PostgreSQL.

---

## Requirements

| | Minimum |
|---|---|
| Joomla | 5.0 (6.x supported) |
| PHP | 8.1 |
| Database | MySQL 5.7+ / MariaDB 10.4+ / PostgreSQL 12+ |

JCH Optimize is **not** required. Without it, Stealth Watch still logs and protects any plugins you choose.

---

## Installation

1. Download the latest version from Github
2. In the Joomla administrator, go to **System → Install → Extensions** and upload the zip.
3. Open **Components → Stealth Watch**.

The package installs a component and a system plugin, enables the plugin, and adds you as the first Settings Manager. After installing, it checks that everything was registered and shows a clear message if anything is missing.

### Uninstalling

Uninstall the **Stealth Watch** package from **System → Manage → Extensions**. This removes the component, the plugin and the log table. With *Protect Stealth Watch Itself* enabled, only a Settings Manager can do this.

---

## Configuration

Open **Components → Stealth Watch → Options**. The plugin's own settings screen is intentionally empty; everything lives here so it can be restricted to Settings Managers.

| Tab | Option | Default | Description |
|---|---|---|---|
| Settings Managers | Settings Managers | Installing user | Users who may change Stealth Watch settings. Add with **+**, remove with **−**. |
| Settings Managers | Only Managers Can Delete Log Entries | Yes | Deleting, purging or clearing the log also requires being a manager. |
| Settings Managers | Protect Stealth Watch Itself | Yes | Only managers can disable or uninstall Stealth Watch. |
| Protection | Protect JCH Page Cache | Yes | Block and revert attempts to disable the JCH Optimize page cache. |
| Protection | Settings Managers Can Disable Protected Plugins | Yes | Listed managers may disable protected plugins; logged as Manager override. |
| Protection | Other Protected Plugins | None | Additional plugins nobody may disable or uninstall. |
| Log Retention | Maximum Log Entries | 1000 | Keep only the newest N entries. `0` = unlimited. |
| Log Retention | Keep Entries For (days) | 90 | Remove older entries. `0` = keep forever. |
| Logging | Log Enables Too | Yes | Also log plugins being switched on. |
| Logging | Monitor Frontend Requests | No | Also watch frontend requests (one extra query per page view). |
| Logging | Also Write to User Actions Log | No | Mirror events to Users → User Actions Log. |
| Permissions | Joomla ACL | Joomla defaults | Who can view the log (`core.manage`), delete entries (`core.delete`) and access Options. |

> Settings Managers also need normal Joomla permission to access Options. Super Users have it by default.

---

## Log actions

| Action | Meaning |
|---|---|
| **Disabled** | A plugin was switched off. |
| **Enabled** | A plugin was switched on (if "Log Enables Too" is on). |
| **Uninstalled** | A plugin was removed. |
| **Blocked** | Stealth Watch refused an action before it ran. |
| **Disabled & re-enabled** | A protected plugin was switched off by a route that couldn't be blocked up front, and was switched back on. |
| **Manager override** | A Settings Manager deliberately disabled a protected plugin. |
| **Setting changed** | A cache-related JCH Optimize setting changed (old → new value shown). |
| **Log deleted / cleared / purged** | Someone removed log entries manually. |

Rows are marked with a coloured left edge: **red** for blocked/reverted, **blue** for manager overrides, **yellow** for other JCH-related events.

---

## How it works

The system plugin hooks into three points of each administrator request:

1. **`onAfterInitialise` – snapshot.** Records the enabled state of every plugin and the JCH Optimize cache-related settings.
2. **`onAfterRoute` – guard.** Before the controller runs, inspects requests to `com_plugins`, `com_installer` and `com_config`:
   - removes protected extension IDs from disable/uninstall requests,
   - keeps protected plugins enabled when saved from the edit form,
   - refuses non-managers access to Stealth Watch's options and permissions.
3. **Shutdown – diff and revert.** Compares the current state with the snapshot, writes log entries for each change, and re-enables protected plugins that were switched off. A shutdown function is used because Joomla redirects call `exit()`, which would skip `onAfterRespond`.

Because detection compares database state instead of relying on specific events, it also catches extensions that update `#__extensions` directly.

---

## Building from source

```
.
├── com_stealthwatch/             # Component: log screen, options, helpers
│   ├── stealthwatch.xml
│   └── administrator/
│       ├── access.xml
│       ├── config.xml
│       ├── forms/
│       ├── language/
│       ├── services/
│       ├── sql/
│       ├── src/
│       │   ├── Controller/
│       │   ├── Field/
│       │   ├── Helper/          # LogHelper, AccessHelper, ProtectionHelper
│       │   ├── Model/
│       │   └── View/
│       └── tmpl/
├── plg_system_stealthwatch/      # System plugin: watch, guard, revert
│   ├── stealthwatch.xml
│   ├── language/
│   ├── services/
│   └── src/Extension/StealthWatch.php
└── pkg/
    ├── pkg_stealthwatch.xml      # Package manifest
    └── script.php                # Install checks and first manager setup
```

Build the installable package from the repository root:

```bash
VERSION=1.0.1
cd com_stealthwatch && zip -qr ../pkg/com_stealthwatch.zip . && cd ..
cd plg_system_stealthwatch && zip -qr ../pkg/plg_system_stealthwatch.zip . && cd ..
cd pkg && zip -q "../pkg_stealthwatch_${VERSION}.zip" \
    pkg_stealthwatch.xml script.php com_stealthwatch.zip plg_system_stealthwatch.zip && cd ..
```

When releasing, bump `<version>` in all three manifests. If the database schema changes, add matching files to `sql/updates/mysql/` and `sql/updates/postgresql/`.

**Technical details**

- Component namespace: `Joomla\Component\StealthWatch`
- Plugin namespace: `Joomla\Plugin\System\StealthWatch`
- Database table: `#__stealthwatch_log`

---

## Troubleshooting

**The component doesn't appear under Components.**
Read the messages after installing; the package reports if the component or its menu item was not registered. You can always open the log directly at `administrator/index.php?option=com_stealthwatch`.

**I'm locked out of the options.**
Only active, unblocked users on the Settings Managers list count. If none of them are active, Super Users regain access automatically. As a last resort, edit the `params` column of the `com_stealthwatch` row in `#__extensions` and remove the `managers` key.

**The JCH dashboard still shows the page cache as off.**
It was re-enabled at the end of that request. Reload the page to see the current state.

---

## Security notes and limitations

Stealth Watch protects against changes made **through the Joomla administrator**. It is an audit and deterrence tool, not a replacement for proper access control.

- Changes made directly in the database, via FTP/SSH or the CLI are not attributed to a Joomla user and are not blocked.
- If two administrators change plugins at exactly the same moment, a change may be attributed to the wrong user.
- Uninstalls cannot be reverted, so they are blocked up front instead.
- Retention is enforced when entries are written or the log is opened, not on a timer.
- The log stores usernames, email addresses and IP addresses. Set a retention period that fits your privacy policy and local data-protection rules (e.g. GDPR).

To report a security issue, please open a private security advisory on this repository rather than a public issue.

---

## Contributing

Issues and pull requests are welcome.

1. Fork the repository and create a feature branch.
2. Follow the [Joomla coding standards](https://developer.joomla.org/coding-standards.html).
3. Test on Joomla 5 and, if possible, Joomla 6.
4. Describe what you changed and how you tested it in the pull request.

Translations are welcome too: copy the `en-GB` language files and translate them.

---

## Changelog

### 1.0.1
- Fixed: log row highlighting was unreadable in Atum dark mode.
- Added: **Settings Managers Can Disable Protected Plugins** option, logged as **Manager override**.
- Added: **Manager override** action filter.

### 1.0.0
- Initial public release.

---

## Disclaimer

Stealth Watch is an independent project. It is not affiliated with or endorsed by Open Source Matters, Inc. or the Joomla! Project, nor by the developers of JCH Optimize. Joomla!® is a trademark of Open Source Matters, Inc. All other trademarks belong to their respective owners.

The software is provided "as is", without warranty of any kind. Test on a staging site before using it in production.

---

## License

Copyright © 2026 Damir B

This program is free software: you can redistribute it and/or modify it under the terms of the [GNU General Public License](https://www.gnu.org/licenses/gpl-2.0.html) as published by the Free Software Foundation, either version 2 of the License, or (at your option) any later version.
