# Release signing (secret-gated)

Platform signing for `capabilities` release binaries is **optional** and **secret-gated**.

| Condition | Release behaviour |
|---|---|
| Signing secrets **absent** | GoReleaser still publishes **unsigned** multi-arch assets + `checksums.txt`. Job logs include clear `signing skipped: …` lines. |
| Signing secrets **present** | Workflow imports/installs tooling as needed; GoReleaser post-build hook `scripts/sign-binary.sh` applies Windows Authenticode and/or macOS codesign + notarization when the runner OS allows it. |

**Never commit** `.p12`, `.pem`, private keys, or certificate bodies to this repository. Configure them only as GitHub Actions secrets on `rawphp/capabilities-cli`.

Checksums (`checksums.txt`) are always produced by [`.goreleaser.yml`](../.goreleaser.yml) regardless of signing.

---

## Soft path (default)

On every `v*` tag release (see [`.github/workflows/release.yml`](../.github/workflows/release.yml)):

1. A **Platform signing status** step checks whether Apple or Windows secrets are set.
2. If none are set, it logs  
   `signing skipped: no APPLE_* / WINDOWS_* secrets present; publishing unsigned multi-arch assets`  
   and continues.
3. GoReleaser runs unconditionally (unsigned soft path uses only `GITHUB_TOKEN` + `contents: write`).
4. Per-binary post hooks call `scripts/sign-binary.sh`, which again no-ops with `signing skipped: …` when secrets or host tooling are missing (exit 0 — does not fail the release).

This matches monorepo residual policy: signed assets require child-repo secrets; absence must not block ship.

---

## GitHub Actions secret names

Configure these under **Settings → Secrets and variables → Actions** on `rawphp/capabilities-cli` (not the monorepo, unless you only ever release from monorepo — the package-owned workflow runs on the child after split).

### Apple (macOS codesign + notarization)

| Secret | Purpose |
|---|---|
| `APPLE_CERTIFICATE_BASE64` | Base64-encoded Developer ID Application `.p12` (export from Keychain Access). |
| `APPLE_CERTIFICATE_PASSWORD` | Password for that `.p12`. |
| `APPLE_TEAM_ID` | 10-character Apple Team ID (Membership details). |
| `APPLE_SIGNING_IDENTITY` | Optional. Exact codesign identity string; if unset, the script prefers a `Developer ID Application` identity from the imported keychain. |

**Notary — choose one path:**

**API key (recommended for CI):**

| Secret | Purpose |
|---|---|
| `APPLE_API_KEY_ID` | App Store Connect API key ID. |
| `APPLE_API_ISSUER` | Issuer UUID for the API key. |
| `APPLE_API_KEY_P8` | Full contents of the `.p8` private key file (PEM text). Store as a secret only — never commit. |

**Apple ID (app-specific password):**

| Secret | Purpose |
|---|---|
| `APPLE_ID` | Apple ID email used for notarization. |
| `APPLE_ID_PASSWORD` | App-specific password (not the account login password). |
| `APPLE_TEAM_ID` | Same team ID as above (required for this path). |

### Windows (Authenticode)

| Secret | Purpose |
|---|---|
| `WINDOWS_CERT_BASE64` | Base64-encoded code-signing certificate `.p12` / `.pfx`. |
| `WINDOWS_CERT_PASSWORD` | Password for that PKCS#12 file. |

---

## Workflow behaviour when secrets are present

From [`.github/workflows/release.yml`](../.github/workflows/release.yml):

| Step | Gate | Effect |
|---|---|---|
| Platform signing status | always | Logs enabled vs `signing skipped` |
| Install osslsigncode | `steps.signing.outputs.windows_present == 'true'` | Installs `osslsigncode` for Authenticode on `ubuntu-latest` (presence detected via `env:` + step outputs — **not** `if: secrets.*`, which GitHub rejects) |
| Apple secrets notice | `steps.signing.outputs.apple_present == 'true'` | Logs that full Gatekeeper/notarization needs a **macOS** runner; secrets are still passed into GoReleaser env for hooks |
| Run GoReleaser | always | Passes Apple/Windows secret env vars when set (empty when unset) |

### Runner notes

| Platform | Default release job (`ubuntu-latest`) | Full fidelity |
|---|---|---|
| **Windows Authenticode** | Supported when `WINDOWS_*` secrets present (`osslsigncode`). | Same. |
| **macOS codesign / notary** | Hook **soft-skips** with a log that secrets are present but host is not Darwin. | Run GoReleaser (or a follow-up sign job) on `macos-latest` with the same secrets; see maintainer checklist below. |

The default job deliberately stays on Linux so **unsigned multi-arch release always works** without Apple hardware or Windows certs. macOS signing is scaffolded and secret-gated; operators who need Gatekeeper-clean darwin assets should add a `macos-latest` matrix/job that reuses the same secrets and `.goreleaser.yml` hooks (or signs only darwin artifacts).

---

## GoReleaser integration

[`.goreleaser.yml`](../.goreleaser.yml) builds `./cmd/capabilities` for darwin/linux/windows × amd64/arm64 and attaches a **post-build hook**:

```yaml
hooks:
  post:
    - cmd: bash scripts/sign-binary.sh "{{ .Path }}" "{{ .Os }}" "{{ .Arch }}"
      output: true
```

- The script never uses GoReleaser `{{ .Env.SECRET }}` expansions that would hard-fail when unset.
- Checksums are always emitted as `checksums.txt` (sha256).
- Cosign/GPG `signs:` blocks are intentionally not required for the soft path.

---

## Maintainer checklist (enable signing)

1. Obtain **Developer ID Application** cert (Apple) and/or Authenticode cert (Windows CA / internal PKI).
2. Export each as PKCS#12; base64-encode for GitHub secrets:
   ```bash
   base64 -i DeveloperID.p12 | pbcopy   # macOS
   base64 -w0 codesign.pfx > win.b64    # Linux
   ```
3. Create the secret names listed above on `rawphp/capabilities-cli`.
4. Push or re-push a `v*` tag (monorepo split mirrors the tag; package workflow replaces the release).
5. Inspect Actions logs for `signing:` success lines vs `signing skipped:`.
6. Verify artifacts:
   - Windows: `osslsigncode verify -in capabilities.exe` (or Explorer properties → Digital Signatures).
   - macOS (after Darwin signing): `codesign -dv --verbose=4 ./capabilities` and Gatekeeper assessment.

---

## Security

- Do **not** paste cert/key material into issues, PRs, or this doc.
- Rotate secrets if a `.p12` or app-specific password is exposed.
- `GITHUB_TOKEN` alone is enough for **unsigned** publish; signing secrets are additive only.
- Monorepo `SPLIT_GITHUB_TOKEN` is unrelated to CLI signing.

---

## Related

- Release workflow: [`.github/workflows/release.yml`](../.github/workflows/release.yml)
- GoReleaser config: [`.goreleaser.yml`](../.goreleaser.yml)
- Soft-gated hook: [`scripts/sign-binary.sh`](../scripts/sign-binary.sh)
- Cross-compile matrix: [`dist/README.md`](../dist/README.md)
