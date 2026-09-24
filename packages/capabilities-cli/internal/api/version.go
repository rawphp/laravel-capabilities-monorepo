package api

import (
	"context"
	"encoding/json"
	"fmt"
)

// APIVersion is the capability HTTP API wire version this CLI speaks.
// The server reports its own as data.api_version on GET /capabilities/health
// (RouteTable::API_VERSION in rawphp/laravel-capabilities).
const APIVersion = 1

// APIVersionError means the server speaks a different wire version than this CLI.
type APIVersionError struct {
	Server int
}

func (e *APIVersionError) Error() string {
	if e.Server > APIVersion {
		return fmt.Sprintf("server speaks capability API v%d but this CLI speaks v%d; run `capabilities self-update`", e.Server, APIVersion)
	}
	return fmt.Sprintf("server speaks capability API v%d but this CLI speaks v%d; upgrade rawphp/laravel-capabilities on the server or install an older CLI", e.Server, APIVersion)
}

// CheckAPIVersion probes GET /capabilities/health and returns *APIVersionError
// only on a definite mismatch. Surface health (agent/mcp down) is ignored, and an
// unknown version (transport error, gated or missing health, older server without
// api_version) is not a mismatch: the invoke itself reports those failures.
func (c *Client) CheckAPIVersion(ctx context.Context) error {
	res, err := c.Health(ctx)
	if err != nil || res.StatusCode >= 400 {
		return nil
	}
	var env struct {
		Data struct {
			APIVersion *int `json:"api_version"`
		} `json:"data"`
	}
	if json.Unmarshal(res.Body, &env) != nil || env.Data.APIVersion == nil {
		return nil
	}
	if *env.Data.APIVersion != APIVersion {
		return &APIVersionError{Server: *env.Data.APIVersion}
	}
	return nil
}
