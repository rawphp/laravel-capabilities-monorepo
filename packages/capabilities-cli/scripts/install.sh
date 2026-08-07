#!/usr/bin/env bash
# Install the capabilities CLI into a user-writable bin dir (no sudo).
#
# Usage:
#   curl -fsSL https://raw.githubusercontent.com/rawphp/capabilities-cli/main/scripts/install.sh | bash
#   VERSION=0.4.0 bash scripts/install.sh
#   CAPABILITIES_INSTALL_DIR=~/bin bash scripts/install.sh
#
# Env:
#   VERSION                 Pin a release (e.g. 0.4.0). Default: latest GitHub release.
#   CAPABILITIES_INSTALL_DIR  Install directory (default: ~/.local/bin)
#   REPO                    owner/name (default: rawphp/capabilities-cli)
set -euo pipefail

REPO="${REPO:-rawphp/capabilities-cli}"
INSTALL_DIR="${CAPABILITIES_INSTALL_DIR:-${HOME}/.local/bin}"
BIN_NAME="capabilities"

die() { echo "install: $*" >&2; exit 1; }
info() { echo "install: $*"; }

need_cmd() {
  command -v "$1" >/dev/null 2>&1 || die "missing required command: $1"
}

need_cmd curl
need_cmd tar
need_cmd install
need_cmd uname
need_cmd mktemp

os="$(uname -s | tr '[:upper:]' '[:lower:]')"
arch_raw="$(uname -m)"
case "${arch_raw}" in
  x86_64|amd64) arch="amd64" ;;
  aarch64|arm64) arch="arm64" ;;
  *) die "unsupported architecture: ${arch_raw} (need amd64 or arm64)" ;;
esac

case "${os}" in
  darwin|linux) archive_ext="tar.gz" ;;
  mingw*|msys*|cygwin*|windows*)
    die "Windows: download the .zip from https://github.com/${REPO}/releases and place ${BIN_NAME}.exe on your user PATH (e.g. %USERPROFILE%\\bin)"
    ;;
  *) die "unsupported OS: ${os} (need darwin or linux)" ;;
esac

if [[ -z "${VERSION:-}" ]]; then
  need_cmd sed
  # Prefer redirect Location from /releases/latest (no API token needed).
  latest_url="$(curl -fsSLI -o /dev/null -w '%{url_effective}' "https://github.com/${REPO}/releases/latest" || true)"
  if [[ "${latest_url}" =~ /tag/v([0-9][^/[:space:]]*)$ ]]; then
    VERSION="${BASH_REMATCH[1]}"
  else
    # Fallback: GitHub API
    api_json="$(curl -fsSL "https://api.github.com/repos/${REPO}/releases/latest")" || die "could not resolve latest release"
    VERSION="$(printf '%s' "${api_json}" | sed -n 's/.*"tag_name":[[:space:]]*"v\([^"]*\)".*/\1/p' | head -1)"
  fi
  [[ -n "${VERSION}" ]] || die "could not resolve latest release version for ${REPO}"
fi

# Strip leading v if user passed v0.4.0
VERSION="${VERSION#v}"

asset="${BIN_NAME}_${VERSION}_${os}_${arch}.${archive_ext}"
url="https://github.com/${REPO}/releases/download/v${VERSION}/${asset}"

tmp="$(mktemp -d)"
cleanup() { rm -rf "${tmp}"; }
trap cleanup EXIT

info "downloading ${url}"
if ! curl -fsSL "${url}" -o "${tmp}/${asset}"; then
  die "download failed (check VERSION=${VERSION} exists for ${os}/${arch})"
fi

info "extracting ${asset}"
tar -xzf "${tmp}/${asset}" -C "${tmp}"
if [[ ! -f "${tmp}/${BIN_NAME}" ]]; then
  # some archives may nest; find binary
  found="$(find "${tmp}" -type f -name "${BIN_NAME}" | head -1 || true)"
  [[ -n "${found}" ]] || die "archive did not contain ${BIN_NAME}"
  mv "${found}" "${tmp}/${BIN_NAME}"
fi

mkdir -p "${INSTALL_DIR}"
install -m 755 "${tmp}/${BIN_NAME}" "${INSTALL_DIR}/${BIN_NAME}"
info "installed ${INSTALL_DIR}/${BIN_NAME}"

if ! command -v "${BIN_NAME}" >/dev/null 2>&1; then
  case ":${PATH}:" in
    *":${INSTALL_DIR}:"*) ;;
    *)
      info "add to PATH (zsh/bash):"
      echo "  export PATH=\"${INSTALL_DIR}:\$PATH\""
      info "or append that line to ~/.zshrc / ~/.bashrc"
      ;;
  esac
fi

if "${INSTALL_DIR}/${BIN_NAME}" version >/dev/null 2>&1; then
  ver="$("${INSTALL_DIR}/${BIN_NAME}" version 2>/dev/null || "${INSTALL_DIR}/${BIN_NAME}" --version 2>/dev/null || true)"
  info "ok — ${ver}"
elif "${INSTALL_DIR}/${BIN_NAME}" --version >/dev/null 2>&1; then
  info "ok — $("${INSTALL_DIR}/${BIN_NAME}" --version)"
else
  info "ok — binary installed (run: ${INSTALL_DIR}/${BIN_NAME} version)"
fi
