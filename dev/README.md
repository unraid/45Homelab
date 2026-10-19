# Dev Harnesses (Not Packaged)

Everything in `dev/` is development-only and is not included in the plugin
`.txz` payload.

## Update Vendored 45drives-disks Snapshot

The **base** disk-map UI is built by **45Drives**; the shipped bundle under
`assets/45d/45drives-disks/` is that base **plus Unraid-specific patches**
(Cockpit API shim, drive standby display, storage context) that upstream does
not have — see `assets/45d/README.md`. `dev/update-45drives-disks.sh` fetches an
upstream build as a **starting point**; it does not build from source and does
not re-apply the Unraid patches.

> ⚠️ **A straight pull regresses Unraid features.** Upstream builds have none of
> the Unraid patches (verified: v2.8.2 has zero standby/storage markers), so the
> output of this script is *not shippable on its own* — the patches must be
> re-applied first. Treat it as the first step of a port, then QA on real
> hardware. (A version bump is also a re-port, not a merge: upstream switched to
> the `houston-common` monorepo after v2.5.8.)

45Drives publishes built modules as `.deb`/`.rpm` assets on their GitHub
releases (`45Drives/cockpit-hardware`); the disk-map module lives inside at
`usr/share/cockpit/45drives-disks/`. Releases before ~v2.6 ship no built
artifacts.

```bash
# Pull straight from a GitHub release (downloads the .deb, extracts the module).
# Requires the `gh` CLI (authenticated) and `ar`.
dev/update-45drives-disks.sh --release v2.8.2
dev/update-45drives-disks.sh --release latest --dry-run

# Or point at an already-built module: a directory or a tarball of one.
dev/update-45drives-disks.sh --source /usr/share/cockpit/45drives-disks
dev/update-45drives-disks.sh ~/Downloads/45drives-disks.txz --dry-run
```

The script syncs the module into place, drops stale hashed assets, and
regenerates `index.html` by **transforming upstream's own `index.html`**: it
strips the Cockpit-internal loader scripts (`../base1/cockpit.js`,
`../manifests.js`, `../*/po.js`) that 404 outside Cockpit, and injects the
Unraid shim (`dev/45drives-disks-cockpit-shim.html`, which routes Cockpit API
calls to `php/api.php`). Upstream's own asset references — including the entry
bundle and `p5.min.js` — are preserved as-is, so the tool survives upstream
hash-scheme and bundling changes.

### Options

- `--release <tag|latest>` pull a build from the 45Drives GitHub releases
- `--source <dir|tarball>` use a local module directory or tarball instead
- `--version <ver>` record the release in `assets/45d/README.md`'s provenance
  note (defaults to the release tag when using `--release`)
- `--dry-run` print the planned changes without modifying the repo

45Drives also restructures the module across versions (e.g. v2.8.x externalized
p5 as a root `p5.min.js` and changed the asset hash scheme), so a pull can differ
substantially from the previous snapshot. Always review the diff and **QA on a
real Unraid host before shipping** (e.g. `dev/live-deploy.sh --host root@<ip>
--include-assets`).

## Live Deploy (Direct to Unraid Plugin Dir)

Use `dev/live-deploy.sh` to copy local files directly into the live plugin
directory on a remote Unraid host:

- default target: `/usr/local/emhttp/plugins/45d-drivemap`
- default paths: `DriveMap.page`, `plugin.cfg`, `php/`, `scripts/`
- optional extras: `assets/`, dev pages, or additional repo-relative paths

### Usage

```bash
dev/live-deploy.sh --host root@192.168.1.201
dev/live-deploy.sh --host 192.168.1.201 --include-assets
dev/live-deploy.sh --host root@192.168.1.201 --include-dev-pages
dev/live-deploy.sh --host root@192.168.1.201 --extra-path DriveMapDevToolsSimulator.page
```

### Options

- `--method auto|rsync|scp` transfer mode (default `auto`)
- `--host` accepts either `user@host` or bare `host`/IP (bare defaults to `root@`)
- `--plugin-dir <path>` override remote plugin directory
- `--include-assets` include `assets/` in deploy
- `--include-dev-pages` include `DriveMapDevTools.page` and `DriveMapDevToolsSimulator.page`
- `--extra-path <path>` add repo-relative path(s) to deploy
- `--dry-run` print deploy plan without changing remote files
- `--no-verify` skip remote post-deploy checks

## Remote Non-45d Harness

Use `dev/remote-test-harness.sh` to copy this repo to a remote host over SSH
and run tests there.

Default behavior runs a pure-PHP smoke suite (`tests/remote_smoke.php`) so it
works on non-45Drives systems that do not have Python installed.

You can also run the smoke suite against a custom alias fixture to simulate
non-45d layouts.

### Usage

```bash
dev/remote-test-harness.sh --host root@192.168.1.201
dev/remote-test-harness.sh --host 192.168.1.201
dev/remote-test-harness.sh --host root@192.168.1.201 --simulate-fixture tests/fixtures/vdev_id_h16_q30.conf
```

### Options

- `--method auto|rsync|scp` transfer mode (default `auto`)
- `--host` accepts either `user@host` or bare `host`/IP (bare defaults to `root@`)
- `--remote-dir /tmp/...` fixed remote workspace path
- `--full` run `tests/run.php` when Python is available remotely (falls back to
  smoke suite when Python is missing)
- `--simulate-fixture <path>` run smoke tests with a custom alias fixture
  (absolute path or repo-relative path)
- `--keep-remote` keep remote workspace after success

## Dev Tools Page (Live Simulation Mode)

To simulate a 45d layout directly on a non-45d Unraid host via the web UI:

1. Copy the dev-only pages:

```bash
scp DriveMapDevTools.page DriveMapDevToolsSimulator.page root@192.168.1.201:/usr/local/emhttp/plugins/45d-drivemap/
```

2. Open **Tools -> 45HomeLab Drive Map Dev -> Simulator** in Unraid.
3. Pick a profile (for example `H16 Q30`).
4. Choose an occupancy source:
   - `Use detected by-path devices only` (real occupancy cap)
   - `Repeat detected by-path devices...` (fills more bays on non-45d hosts)
5. Choose disk data mode:
   - `Use generator output only` (real data only)
   - `Overlay healthy/mixed/failing synthetic disk metadata`
6. Click **Apply Simulation** and reload **Main** to inspect the Drive Map.
7. Use **Clear Simulation** to restore backups/remove simulated state.

By default this page is not included in stable plugin packages.
Set `INCLUDE_DEV_TOOLS_PAGE=1` when running `scripts/build-plugin-txz` to include it in a dev package.
