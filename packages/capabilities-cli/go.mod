module github.com/rawphp/capabilities-cli

// 1.24+: Go 1.22.x on GitHub macos-26-arm64 emits test/release binaries
// without LC_UUID; dyld aborts with "missing LC_UUID load command".
go 1.24

require github.com/google/uuid v1.6.0
