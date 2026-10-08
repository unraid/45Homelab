# Releasing

This repo ships a thin `45homelab.plg` that downloads a versioned plugin
tarball from GitHub Releases and extracts it on Unraid. Each release also
ships a temporary `45d-drivemap.plg` compatibility asset for existing
installations.

The repository is `unraid/45Homelab`. The legacy asset keeps the old plugin
URL and ID long enough to migrate existing installs. It installs the new
plugin, preserves old config files, and removes the old descriptor and runtime
without invoking the old remove hook.

`scripts/render-plg` renders `<CHANGES>` from `CHANGELOG.md`.

## Changelog flow (KNope)

1. Create a change file:
   - `knope document-change`
2. Prepare a release locally:
   - `knope release --dry-run`
3. Run the real release workflow locally (commits changelog + tags + pushes):
   - `knope release`

KNope configuration lives in `knope.toml` and writes release notes to
`CHANGELOG.md`.

## Automatic release assets

1. Push a tag in the format `vX.Y.Z`.
2. GitHub Actions workflow `.github/workflows/release.yml` will:
  - build `packages/45homelab-X.Y.Z.txz`
  - render `45homelab.plg` with matching checksum and release URLs
  - render `45d-drivemap.plg` with the checksum of the new descriptor
  - publish both descriptors and the package to that release

Because `plugin_url` points to:

`https://github.com/<owner>/<repo>/releases/latest/download/45homelab.plg`

the install URL remains stable while payloads stay versioned.

## Local build helpers

- Build full plugin package:

`scripts/build-plugin-txz 0.5.0`

- Render release plg from template:

`scripts/render-plg 0.5.0 <package-sha256> unraid/45Homelab`

`scripts/render-legacy-plg 0.5.0 <45homelab-plg-sha256> unraid/45Homelab`
