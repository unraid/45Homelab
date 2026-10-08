# Releasing

This repo ships a thin `45d-drivemap.plg` that downloads a versioned plugin
tarball from GitHub Releases and extracts it on Unraid.

The repository is `unraid/45Homelab`. The plugin keeps the existing
`45d-drivemap` ID and paths. User-facing metadata changes the displayed name
to `45HomeLab`, so existing installations update normally.

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
  - build `packages/45d-drivemap-X.Y.Z.txz`
  - render `45d-drivemap.plg` with matching checksum and release URLs
  - publish the descriptor and package to that release

Because `plugin_url` points to:

`https://github.com/<owner>/<repo>/releases/latest/download/45d-drivemap.plg`

the install URL remains stable while payloads stay versioned.

## Local build helpers

- Build full plugin package:

`scripts/build-plugin-txz 0.5.0`

- Render release plg from template:

`scripts/render-plg 0.5.0 <package-sha256> unraid/45Homelab`
