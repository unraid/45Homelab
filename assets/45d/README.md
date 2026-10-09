This directory contains vendored 45drives-disks frontend assets.

The **base** UI is built by 45Drives (a `cockpit-45drives-hardware` release).
We do not build the base from scratch here — but the shipped bundle is **not a
clean upstream snapshot**: it carries Unraid-specific patches that upstream does
not have. So this is *not* purely vendored, unmaintained output — treat it as
"45Drives base + Unraid patches."

Unraid-specific patches layered on the shipped bundle:

- `index.html` — Cockpit API shim routing process calls to
  `/plugins/45d-drivemap/php/api.php` (see `dev/45drives-disks-cockpit-shim.html`).
- Drive standby / power-mode display (orange overlay on spun-down bays).
- Unraid storage context (`storageRoleLabel` / `hasStorageDetails`).

Because of those patches, **updating is not a drop-in pull.** A newer upstream
build lacks the Unraid patches and would regress them (verified: upstream v2.8.2
has zero occurrences of the standby/storage markers). `dev/update-45drives-disks.sh`
fetches an upstream build as a *starting point* only — the Unraid patches must be
re-applied and the result QA'd on real hardware before shipping.

Also note: upstream rewrote its build architecture right after our base version
(v2.5.8 used `@45drives/cockpit-helpers`; v2.5.11+ switched to the
`@45drives/houston-common-*` Yarn workspace monorepo), so a version bump is a
re-port, not a merge. See https://github.com/unraid/45d-drivemap/pull/21.

- `45drives-disks/` holds a prebuilt Cockpit module bundle (upstream base
  v2.5.4-2) plus the Unraid patches above.
- The matching Vue source lives under
  `vendor/45drives/cockpit-hardware/45drives-disks/` (upstream v2.5.8 + the
  Unraid feature source); it builds with `npm install && npm run build` thanks to
  the vendored dep shim in `src/lib/`.
- `drivemap.js` and `drivemap.css` remain as a lightweight fallback renderer.
