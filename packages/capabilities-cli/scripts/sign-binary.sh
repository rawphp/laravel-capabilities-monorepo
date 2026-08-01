#!/usr/bin/env bash
# Soft-gated platform signing for release binaries (REQ-063).
#
# Usage (GoReleaser post-build hook):
#   bash scripts/sign-binary.sh "{{ .Path }}" "{{ .Os }}" "{{ .Arch }}"
#
# Soft path (mandatory): when required secrets are absent, print a clear
# "signing skipped" line and exit 0 so the release still publishes unsigned assets.
# Never hard-fail the release solely because signing secrets are missing.
#
# No private keys or certificates are embedded in this repository.

set -euo pipefail

BIN_PATH="${1:-}"
GOOS="${2:-}"
GOARCH="${3:-}"

if [[ -z "${BIN_PATH}" ]]; then
  echo "usage: sign-binary.sh <path> [goos] [goarch]" >&2
  exit 2
fi

if [[ ! -f "${BIN_PATH}" ]]; then
  echo "signing skipped: binary not found at ${BIN_PATH}"
  exit 0
fi

log_skip() {
  echo "signing skipped: $*"
}

# Windows Authenticode via osslsigncode (works on Linux CI when secrets present).
sign_windows() {
  if [[ -z "${WINDOWS_CERT_BASE64:-}" || -z "${WINDOWS_CERT_PASSWORD:-}" ]]; then
    log_skip "Windows Authenticode secrets absent (WINDOWS_CERT_BASE64 / WINDOWS_CERT_PASSWORD)"
    return 0
  fi

  if ! command -v osslsigncode >/dev/null 2>&1; then
    log_skip "osslsigncode not installed (workflow installs it only when WINDOWS_CERT_BASE64 is set)"
    return 0
  fi

  local tmp
  tmp="$(mktemp -d)"
  # shellcheck disable=SC2064
  trap "rm -rf '${tmp}'" RETURN

  local p12="${tmp}/windows-code-sign.p12"
  local signed="${tmp}/signed.exe"

  # Decode base64 P12 from CI secret (never commit the secret value).
  if ! printf '%s' "${WINDOWS_CERT_BASE64}" | base64 --decode >"${p12}" 2>/dev/null; then
    # macOS base64 uses -D; try common variants without failing the release hard.
    if ! printf '%s' "${WINDOWS_CERT_BASE64}" | base64 -D >"${p12}" 2>/dev/null && \
       ! printf '%s' "${WINDOWS_CERT_BASE64}" | base64 -d >"${p12}" 2>/dev/null; then
      log_skip "failed to decode WINDOWS_CERT_BASE64 (check secret encoding)"
      return 0
    fi
  fi

  echo "signing: Windows Authenticode for ${BIN_PATH} (${GOOS}/${GOARCH})"
  if osslsigncode sign \
    -pkcs12 "${p12}" \
    -pass "${WINDOWS_CERT_PASSWORD}" \
    -n "capabilities CLI" \
    -i "https://github.com/rawphp/capabilities-cli" \
    -in "${BIN_PATH}" \
    -out "${signed}"; then
    mv "${signed}" "${BIN_PATH}"
    echo "signing: Windows Authenticode OK for ${BIN_PATH}"
  else
    # Soft path: do not fail the whole release if signing tools/certs misbehave.
    log_skip "osslsigncode failed for ${BIN_PATH}; publishing unsigned Windows binary"
  fi
}

# macOS codesign + optional notarytool (requires Darwin host + Apple secrets).
sign_darwin() {
  if [[ -z "${APPLE_CERTIFICATE_BASE64:-}" || -z "${APPLE_CERTIFICATE_PASSWORD:-}" ]]; then
    log_skip "Apple codesign secrets absent (APPLE_CERTIFICATE_BASE64 / APPLE_CERTIFICATE_PASSWORD / APPLE_TEAM_ID / notary creds)"
    return 0
  fi

  if [[ "$(uname -s)" != "Darwin" ]]; then
    log_skip "Apple codesign/notarization requires a macOS runner (secrets present but host is $(uname -s); see docs/release-signing.md)"
    return 0
  fi

  if ! command -v codesign >/dev/null 2>&1; then
    log_skip "codesign not available on this macOS image"
    return 0
  fi

  local tmp
  tmp="$(mktemp -d)"
  # shellcheck disable=SC2064
  trap "rm -rf '${tmp}'" RETURN

  local p12="${tmp}/apple-dev-id.p12"
  local keychain="${tmp}/capabilities-signing.keychain-db"
  local keychain_pass
  keychain_pass="$(openssl rand -hex 16 2>/dev/null || echo 'temp-capabilities-kc')"

  if ! printf '%s' "${APPLE_CERTIFICATE_BASE64}" | base64 --decode >"${p12}" 2>/dev/null; then
    if ! printf '%s' "${APPLE_CERTIFICATE_BASE64}" | base64 -D >"${p12}" 2>/dev/null && \
       ! printf '%s' "${APPLE_CERTIFICATE_BASE64}" | base64 -d >"${p12}" 2>/dev/null; then
      log_skip "failed to decode APPLE_CERTIFICATE_BASE64 (check secret encoding)"
      return 0
    fi
  fi

  echo "signing: importing Apple certificate for ${BIN_PATH} (${GOOS}/${GOARCH})"
  security create-keychain -p "${keychain_pass}" "${keychain}" >/dev/null
  security set-keychain-settings -lut 21600 "${keychain}" >/dev/null
  security unlock-keychain -p "${keychain_pass}" "${keychain}" >/dev/null
  security import "${p12}" -P "${APPLE_CERTIFICATE_PASSWORD}" -A -t cert -f pkcs12 -k "${keychain}" >/dev/null || {
    log_skip "security import failed for Apple certificate; publishing unsigned darwin binary"
    return 0
  }
  security list-keychain -d user -s "${keychain}" "$(security list-keychain -d user | tr -d '\"')" >/dev/null 2>&1 || true

  local identity="${APPLE_SIGNING_IDENTITY:-}"
  if [[ -z "${identity}" ]]; then
    # Prefer Developer ID Application identity when identity secret unset.
    identity="$(security find-identity -v -p codesigning "${keychain}" 2>/dev/null | awk -F'\"' '/Developer ID Application/{print $2; exit}')"
  fi
  if [[ -z "${identity}" ]]; then
    log_skip "no Apple signing identity found (set APPLE_SIGNING_IDENTITY or use a Developer ID Application cert)"
    return 0
  fi

  echo "signing: codesign ${BIN_PATH} as ${identity}"
  if ! codesign --force --options runtime --timestamp --sign "${identity}" "${BIN_PATH}"; then
    log_skip "codesign failed for ${BIN_PATH}; publishing unsigned darwin binary"
    return 0
  fi
  codesign --verify --verbose=2 "${BIN_PATH}" || true
  echo "signing: codesign OK for ${BIN_PATH}"

  # Notarization: notarytool rejects bare Mach-O — must submit zip/pkg/dmg.
  # Zip the codesigned binary, submit, leave binary in place. Stapling does not
  # apply to bare CLI binaries; Gatekeeper fetches the ticket online via CDHash.
  local submit_zip=""
  pack_notary_zip() {
    submit_zip="${tmp}/capabilities-notary-${GOARCH}.zip"
    local bin_base stage
    bin_base="$(basename "${BIN_PATH}")"
    stage="${tmp}/notary-stage-${GOARCH}"
    rm -rf "${stage}"
    mkdir -p "${stage}"
    cp "${BIN_PATH}" "${stage}/${bin_base}"
    if command -v ditto >/dev/null 2>&1; then
      ditto -c -k "${stage}" "${submit_zip}"
    else
      (cd "${stage}" && zip -q -r "${submit_zip}" "${bin_base}")
    fi
  }

  if [[ -n "${APPLE_API_KEY_ID:-}" && -n "${APPLE_API_ISSUER:-}" && -n "${APPLE_API_KEY_P8:-}" ]]; then
    if command -v xcrun >/dev/null 2>&1; then
      local key_path="${tmp}/AuthKey_${APPLE_API_KEY_ID}.p8"
      # GitHub secrets often store PEM with literal \n (single line). Expand those
      # to real newlines so notarytool does not fail with invalidPEMDocument.
      local p8_body="${APPLE_API_KEY_P8}"
      if [[ "${p8_body}" != *$'\n'* && "${p8_body}" == *'\n'* ]]; then
        p8_body="${p8_body//\\n/$'\n'}"
      fi
      # Strip accidental surrounding quotes from secret paste.
      p8_body="${p8_body#\"}"
      p8_body="${p8_body%\"}"
      p8_body="${p8_body#\'}"
      p8_body="${p8_body%\'}"
      # Ensure trailing newline; write without adding extra blank lines mid-PEM.
      printf '%s\n' "${p8_body}" >"${key_path}"
      if ! grep -q "BEGIN PRIVATE KEY\|BEGIN EC PRIVATE KEY" "${key_path}"; then
        log_skip "APPLE_API_KEY_P8 does not look like a PEM private key (missing BEGIN line); re-paste full .p8 including headers. Binary remains codesigned only"
        return 0
      fi
      pack_notary_zip
      echo "signing: notarytool submit (API key, zip) for ${BIN_PATH}"
      if xcrun notarytool submit "${submit_zip}" \
        --key "${key_path}" \
        --key-id "${APPLE_API_KEY_ID}" \
        --issuer "${APPLE_API_ISSUER}" \
        --wait; then
        echo "signing: notarization OK for ${BIN_PATH} (ticket online; bare binary not stapled)"
      else
        log_skip "notarytool submit failed for ${BIN_PATH} (check APPLE_API_KEY_P8 PEM / Key ID / Issuer); binary remains codesigned only"
      fi
    else
      log_skip "notarytool/xcrun unavailable; binary codesigned without notarization"
    fi
  elif [[ -n "${APPLE_ID:-}" && -n "${APPLE_ID_PASSWORD:-}" && -n "${APPLE_TEAM_ID:-}" ]]; then
    if command -v xcrun >/dev/null 2>&1; then
      pack_notary_zip
      echo "signing: notarytool submit (Apple ID, zip) for ${BIN_PATH}"
      if xcrun notarytool submit "${submit_zip}" \
        --apple-id "${APPLE_ID}" \
        --password "${APPLE_ID_PASSWORD}" \
        --team-id "${APPLE_TEAM_ID}" \
        --wait; then
        echo "signing: notarization OK for ${BIN_PATH} (ticket online; bare binary not stapled)"
      else
        log_skip "notarytool submit failed for ${BIN_PATH}; binary remains codesigned only"
      fi
    else
      log_skip "notarytool/xcrun unavailable; binary codesigned without notarization"
    fi
  else
    log_skip "notary credentials absent (APPLE_API_KEY_* or APPLE_ID + APPLE_ID_PASSWORD + APPLE_TEAM_ID); codesign only"
  fi
}

case "${GOOS}" in
  windows)
    sign_windows
    ;;
  darwin)
    sign_darwin
    ;;
  linux|"")
    # No platform Authenticode/codesign for linux CLI binaries in this scaffold.
    if [[ "${GOOS}" == "linux" ]]; then
      log_skip "no platform signing configured for goos=linux"
    else
      # Unknown goos — try path heuristic (rarely hit when hook passes Os).
      case "${BIN_PATH}" in
        *.exe) sign_windows ;;
        *) log_skip "unknown goos for ${BIN_PATH}; no signing applied" ;;
      esac
    fi
    ;;
  *)
    log_skip "no platform signing configured for goos=${GOOS}"
    ;;
esac

exit 0
