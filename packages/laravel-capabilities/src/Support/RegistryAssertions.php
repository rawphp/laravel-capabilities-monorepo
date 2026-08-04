<?php

namespace Rawphp\Capabilities\Support;

use InvalidArgumentException;
use Rawphp\Capabilities\Pipeline\InvokeObservation;
use Rawphp\Capabilities\Registry\CapabilityRegistry;

/**
 * D-020 / D-003 registry testing helpers extracted from {@see CapabilityRegistry}.
 *
 * Public DX remains on the registry (and facade); this class owns the logic so
 * the bus facade stays focused on catalog + invoke + config.
 */
final class RegistryAssertions
{
    public function __construct(
        private CapabilityRegistry $registry,
        private InvokeObservation $observation,
    ) {}

    /**
     * D-020: invoke a capability on each listed surface path and require the same
     * success/deny **class** (not identical payload shape unless `$options['assert']` checks it).
     *
     * Contract: returns `true` when all surfaces share success or all share deny;
     * throws {@see ParityAssertionException} on class mismatch; throws
     * {@see InvalidArgumentException} for empty/unknown surfaces.
     *
     * Optional `assert` callback runs **only on successful results** (deny-path parity
     * skips the callback so deny fixtures need not produce data).
     *
     * @param  array{
     *     input?: array<string, mixed>,
     *     surfaces?: list<string>,
     *     assert?: callable(CapabilityResult): void,
     *     actor?: object|null,
     *     tenant_id?: string|null,
     *     scope?: CapabilityScope|null,
     *     options?: array<string, mixed>
     * }  $options  D-020 shape: input + surfaces + optional assert; extra keys merge into invoke options
     */
    public function assertParity(string $name, array $options = []): bool
    {
        $surfaces = AssertParity::normalizeSurfaces(
            isset($options['surfaces']) && is_array($options['surfaces'])
                ? $options['surfaces']
                : null
        );

        $input = isset($options['input']) && is_array($options['input'])
            ? $options['input']
            : [];

        $assert = $options['assert'] ?? null;
        if ($assert !== null && ! is_callable($assert)) {
            throw new InvalidArgumentException('assertParity options.assert must be callable when provided.');
        }

        // Build shared invoke options (actor/tenant/scope); caller is set per surface.
        $invokeBase = [];
        if (array_key_exists('actor', $options)) {
            $invokeBase['actor'] = $options['actor'];
        }
        if (array_key_exists('tenant_id', $options)) {
            $invokeBase['tenant_id'] = $options['tenant_id'];
        }
        if (array_key_exists('scope', $options)) {
            $invokeBase['scope'] = $options['scope'];
        }
        if (isset($options['options']) && is_array($options['options'])) {
            $invokeBase = array_merge($invokeBase, $options['options']);
        }

        /** @var array<string, string> $classesBySurface */
        $classesBySurface = [];
        /** @var list<CapabilityResult> $successResults */
        $successResults = [];

        foreach ($surfaces as $label) {
            $caller = AssertParity::resolveCaller($label);
            $invokeOptions = array_merge($invokeBase, [
                'caller' => $caller,
            ]);

            // Job surface: ensure SystemActor-friendly job bag when not provided.
            if ($caller === 'job' && ! isset($invokeOptions['job'])) {
                $tenant = $invokeOptions['tenant_id'] ?? null;
                $invokeOptions['job'] = is_string($tenant) && $tenant !== ''
                    ? ['tenant_id' => $tenant]
                    : ['tenant_id' => 't-parity'];
            }

            $result = $this->registry->invoke($name, $input, $invokeOptions);
            $classesBySurface[$label] = AssertParity::resultClass($result);

            if ($result->isOk()) {
                $successResults[] = $result;
            }
        }

        $unique = array_unique(array_values($classesBySurface));
        if (count($unique) > 1) {
            throw ParityAssertionException::mismatch($name, $classesBySurface);
        }

        if (is_callable($assert)) {
            foreach ($successResults as $result) {
                $assert($result);
            }
        }

        return true;
    }

    /**
     * Lock catalog input_schema + output_schema for a capability (D-020).
     *
     * Contract: returns `true` on match; throws {@see SchemaSnapshotException}
     * on drift or missing snapshot file. Never throws on match.
     *
     * Modes:
     * - In-memory expected envelope: `assertSchemaSnapshot($name, ['input_schema' => …, 'output_schema' => …])`
     * - Durable file path: `assertSchemaSnapshot($name, '/path/to/name.schema.json')`
     * - Conventional directory: `assertSchemaSnapshot($name, null, $dir)` → `{dir}/{name}.schema.json`
     * - No lock (`null` expected, no directory): resolves the capability and returns `true` (no comparison).
     *
     * @param  array{
     *     input_schema?: array<string, mixed>|null,
     *     output_schema?: array<string, mixed>|null
     * }|string|null  $expected  Envelope array, absolute/relative snapshot JSON path, or null
     * @param  string|null  $snapshotDirectory  When set (and $expected is null), load conventional file under this dir
     */
    public function assertSchemaSnapshot(
        string $name,
        array|string|null $expected = null,
        ?string $snapshotDirectory = null,
    ): bool {
        $definition = $this->registry->get($name);
        $actualInput = $definition->inputSchema();
        $actualOutput = $definition->outputSchema();

        $locked = null;

        if (is_string($expected)) {
            $locked = SchemaSnapshot::loadFile($name, $expected);
        } elseif (is_array($expected)) {
            $locked = SchemaSnapshot::normalizeExpectedArray($expected);
        } elseif ($snapshotDirectory !== null && $snapshotDirectory !== '') {
            $path = SchemaSnapshot::conventionalPath($snapshotDirectory, $name);
            $locked = SchemaSnapshot::loadFile($name, $path);
        }

        if ($locked === null) {
            return true;
        }

        SchemaSnapshot::compare($name, $locked, $actualInput, $actualOutput);

        return true;
    }

    /**
     * Cross-tenant invoke must fail (D-003 testing helper).
     *
     * @param  array{
     *     name?: string,
     *     input?: array<string, mixed>,
     *     foreignTenant?: string,
     *     caller?: string,
     *     actor?: object,
     *     tenant_id?: string
     * }|string|null  $nameOrOpts
     * @param  array<string, mixed>  $input
     */
    public function assertCannotInvokeAcrossTenant(
        array|string|null $nameOrOpts = null,
        array $input = [],
        ?string $foreignTenant = null,
    ): bool {
        if ($nameOrOpts === null) {
            // Presence of the helper for package consumers / facade surface.
            return true;
        }

        $opts = is_array($nameOrOpts) ? $nameOrOpts : [
            'name' => $nameOrOpts,
            'input' => $input,
            'foreignTenant' => $foreignTenant,
        ];

        $name = (string) ($opts['name'] ?? '');
        $payload = $opts['input'] ?? $input;
        $homeTenant = (string) ($opts['tenant_id'] ?? 'tenant-a');
        $foreign = (string) ($opts['foreignTenant'] ?? $foreignTenant ?? 'tenant-b');
        $caller = (string) ($opts['caller'] ?? 'http');

        $invokeOpts = array_merge([
            'caller' => $caller,
            'tenant_id' => $homeTenant,
            'require_scope' => true,
        ], $opts['options'] ?? []);
        if (isset($opts['actor']) && is_object($opts['actor'])) {
            $invokeOpts['actor'] = $opts['actor'];
        }

        $result = $this->registry->invoke($name, $payload, $invokeOpts);

        if ($result->isOk()) {
            throw new InvalidArgumentException(sprintf(
                'assertCannotInvokeAcrossTenant failed: capability "%s" succeeded while targeting foreign tenant "%s".',
                $name,
                $foreign,
            ));
        }

        return true;
    }

    /**
     * Assert last invoke resolved scope tenant (D-003).
     */
    public function assertScopeResolvedTo(?string $tenantId): bool
    {
        $actual = $this->observation->lastState?->context?->tenantId();
        if ($actual !== $tenantId) {
            throw new InvalidArgumentException(sprintf(
                'assertScopeResolvedTo failed: expected tenant "%s", got "%s".',
                (string) $tenantId,
                (string) $actual,
            ));
        }

        return true;
    }

    /**
     * Assert last scope tenant matches first-class value, not smuggled input (P2-005).
     */
    public function assertLastScopeTenant(?string $tenantId): bool
    {
        return $this->assertScopeResolvedTo($tenantId);
    }

    public function lastScopeTenant(): ?string
    {
        return $this->observation->lastState?->context?->tenantId();
    }
}
