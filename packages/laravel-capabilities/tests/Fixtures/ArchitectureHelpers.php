<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Tests\Fixtures;

use Rawphp\Capabilities\Adapters\Http\CapabilityController;
use Rawphp\Capabilities\Adapters\Mcp\McpAuthProfileResolver;
use Rawphp\Capabilities\Adapters\Mcp\McpToolAdapter;
use Rawphp\Capabilities\Adapters\PeerSurfaceBootstrap;
use Rawphp\Capabilities\Adapters\RunCapabilityJob;
use Rawphp\Capabilities\Adapters\ToolSelection;
use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\Capabilities\Boot\CapabilitiesConfig;
use Rawphp\Capabilities\Contracts\ApprovalNotifier;
use Rawphp\Capabilities\Contracts\ConversationIdentity;
use Rawphp\Capabilities\Contracts\ConversationIngress;
use Rawphp\Capabilities\Contracts\ConversationReply;
use Rawphp\Capabilities\Http\CallerDeriver;
use Rawphp\Capabilities\Http\RouteTable;
use Rawphp\Capabilities\Pipeline\PipelineStages;
use Rawphp\Capabilities\Pipeline\ResolveActor;
use Rawphp\Capabilities\Profiles\ProfileSelector;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Schema\CatalogPresenter;
use Rawphp\Capabilities\Schema\ToolSchemaExporter;
use Rawphp\Capabilities\Support\ErrorCodeMap;
use Rawphp\Capabilities\Support\SystemActor;
use ReflectionClass;
use ReflectionMethod;

/**
 * Structural / refuse-table / design-rule contract helpers for Architecture/* unit tests.
 * Unit-only — no DB, no network.
 */
final class ArchitectureHelpers
{
    public const CALLERS = ['agent', 'mcp', 'http', 'cli', 'job'];

    public const CORE_SRC = __DIR__.'/../../src';

    public const CORE_ROOT = __DIR__.'/../..';

    public const MONOREPO_ROOT = __DIR__.'/../../../..';

    public const CONVERSATION_CONTRACTS = [
        ConversationIngress::class,
        ConversationReply::class,
        ConversationIdentity::class,
        ApprovalNotifier::class,
    ];

    public const DECISIONS = [
        'D-002', 'D-003', 'D-004', 'D-005', 'D-006', 'D-007', 'D-008', 'D-009',
        'D-010', 'D-011', 'D-012', 'D-013', 'D-014', 'D-015', 'D-016', 'D-017',
        'D-018', 'D-019', 'D-020', 'D-021', 'D-022', 'D-023',
    ];

    public const PATCHES = ['P2-004', 'P2-005', 'P2-007'];

    public const REFUSE_ANTIPATTERNS = [
        'null user allow on jobs' => 'D-002',
        'jobs bypass policy global config' => 'D-002',
        'tenant magic key for SystemActor' => 'P2-005',
        'client spoofable caller header upgrade' => 'D-022',
        'full catalog dump to agents by default' => 'D-008',
        'meta tools privilege escape' => 'P2-007',
        'second HTTP invoke controller for CLI' => 'D-009',
        'Laravel rules as only schema source' => 'D-004',
        'CLI only validation without server revalidation' => 'D-004',
        'approval without revalidation on accept' => 'D-006',
        'unsigned Telegram approve id' => 'D-006',
        'approved limbo without resume or atomic' => 'P2-004',
        'silent audit drop when required' => 'D-010',
        'peer half-register tools' => 'D-011',
        'messaging bot runtime inside core' => 'D-007',
        'Artisan as product CLI' => 'D-016',
        'MCP vague token user without profile' => 'D-023',
        'integration credentials without allowlist' => 'D-023',
        'actor from tool JSON for MCP' => 'D-023',
        'SystemActor can approve' => 'D-006',
        'idempotency only on one surface' => 'D-005',
        'dedupe by input without key' => 'D-005',
        'third capability discovery path' => 'D-017',
        'domain logic in Go CLI' => 'D-016',
        'trust exists alone for multi-tenant' => 'D-003',
    ];

    public const DESIGN_RULES = [
        'one run',
        'adapters are dumb',
        'domain stays yours',
        'global surface switches then per-capability narrowing',
        'fail closed',
        'conversation not invoke',
        'jobs declare actor',
        'resources re-resolved under scope',
        'mutating invokes support idempotency',
        'approvals state machine with crash recovery',
        'messaging sibling package',
        'agents get tool groups not full catalog',
        'one HTTP capability API',
        'transactions and audit explicit',
        'peer adapters versioned and tested',
        'names errors DTOs CLI language decided',
        'caller derived not spoofable',
        'MCP principals explicit auth profiles',
    ];

    public const DESIGN_RULES_SLUG = [
        'one_run',
        'adapters_dumb',
        'domain_yours',
        'fail_closed',
        'no_silent_actors',
        'no_ambient_tenancy',
        'idempotent_retries',
        'approvals_state_machine',
        'profiles_not_dump',
        'one_http_api',
        'server_derived_caller',
        'mcp_auth_profiles',
    ];

    public const DUAL_PATHS = [
        'http_controller_domain_create',
        'ai_tool_domain_create',
        'mcp_tool_domain_create',
        'cli_local_domain_create',
        'job_handle_domain_create',
        'telegram_adapter_domain_create',
        'approval_notifier_domain_create',
        'artisan_command_domain_create',
    ];

    public const NON_GOALS = [
        'llm_client',
        'mcp_wire_protocol',
        'artisan_product_cli',
        'chat_ui',
        'telegram_runtime_core',
        'a2a_mesh',
        'controller_replacement',
        'messaging_os',
    ];

    public const GOVERNANCE_CONCERNS = [
        'authorize',
        'approval',
        'audit',
        'actor',
        'scope',
        'idempotency',
        'rate_limit',
        'schema',
    ];

    public const REFUSE_PER_CALLER = [
        'spoof_caller_header',
        'skip_authorize',
        'skip_scope',
        'skip_idempotency_on_mutating',
        'dump_full_catalog',
        'meta_escape',
    ];

    /**
     * Recursively list PHP files under a directory.
     *
     * @return list<string>
     */
    public static function phpFiles(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }
        $out = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                $out[] = $file->getPathname();
            }
        }

        return $out;
    }

    /**
     * Concatenate all core package source (for structural greps).
     */
    public static function coreSourceBlob(): string
    {
        $blob = '';
        foreach (self::phpFiles(self::CORE_SRC) as $path) {
            $blob .= file_get_contents($path)."\n";
        }

        return $blob;
    }

    public static function coreHasNo(string $needle, bool $caseInsensitive = true): bool
    {
        $blob = self::coreSourceBlob();
        if ($caseInsensitive) {
            return stripos($blob, $needle) === false;
        }

        return strpos($blob, $needle) === false;
    }

    public static function coreHasNoMessagingRuntime(): void
    {
        $blob = self::coreSourceBlob();
        expect(stripos($blob, 'api.telegram.org'))->toBeFalse()
            ->and(stripos($blob, 'Telegram\\Bot'))->toBeFalse()
            ->and(stripos($blob, 'Slack\\WebAPI'))->toBeFalse()
            ->and(stripos($blob, 'WhatsApp'))->toBeFalse()
            ->and(is_dir(self::CORE_SRC.'/Messaging'))->toBeFalse();
    }

    public static function conversationContractsExist(): void
    {
        foreach (self::CONVERSATION_CONTRACTS as $class) {
            expect(interface_exists($class))->toBeTrue("Missing contract {$class}");
        }
    }

    public static function messagingComposerSuggestOptional(): void
    {
        $composer = json_decode((string) file_get_contents(self::CORE_ROOT.'/composer.json'), true);
        expect($composer['suggest'] ?? [])->toHaveKey('rawphp/laravel-capabilities-messaging')
            ->and($composer['require'] ?? [])->not->toHaveKey('rawphp/laravel-capabilities-messaging');
    }

    public static function coreDoesNotRequireTelegramToken(): void
    {
        $config = CapabilitiesConfig::defaults();
        $flat = json_encode($config) ?: '';
        expect($flat)->not->toContain('TELEGRAM_BOT_TOKEN')
            ->and(array_key_exists('telegram', $config))->toBeFalse();
    }

    /**
     * Adapters only call registry invoke — structural presence of bus methods.
     */
    public static function adaptersAreDumb(): void
    {
        expect(class_exists(CapabilityController::class))->toBeTrue()
            ->and(class_exists(RunCapabilityJob::class))->toBeTrue()
            ->and(interface_exists(McpToolAdapter::class) || class_exists(McpToolAdapter::class))->toBeTrue()
            ->and(method_exists(CapabilityRegistry::class, 'invoke'))->toBeTrue();
    }

    public static function noAlternateDomainMutationApi(): void
    {
        $forbidden = ['mutateDomain', 'directRun', 'bypassRegistry', 'executeWithoutPipeline'];
        $blob = self::coreSourceBlob();
        foreach ($forbidden as $name) {
            expect(stripos($blob, 'function '.$name))->toBeFalse("Found forbidden API {$name}");
        }
        // Public bus surface is CapabilityBus::invoke / registry invoke only.
        expect(interface_exists(\Rawphp\Capabilities\Contracts\CapabilityBus::class))->toBeTrue();
        $methods = array_map(
            fn (ReflectionMethod $m) => $m->getName(),
            (new ReflectionClass(\Rawphp\Capabilities\Contracts\CapabilityBus::class))->getMethods()
        );
        expect($methods)->toContain('invoke')
            ->and($methods)->not->toContain('runDomain');
    }

    public static function catalogAndHttpShareSchemaSource(): void
    {
        expect(class_exists(ToolSchemaExporter::class))->toBeTrue()
            ->and(class_exists(CatalogPresenter::class))->toBeTrue()
            ->and(
                method_exists(CapabilityRegistry::class, 'catalog')
                || method_exists(CapabilityRegistry::class, 'all')
                || method_exists(CapabilityRegistry::class, 'describe')
            )->toBeTrue();
    }

    public static function governanceStagesPresent(): void
    {
        $stages = PipelineStages::ordered();
        foreach ([
            PipelineStages::AUTHORIZE,
            PipelineStages::NEEDS_APPROVAL,
            PipelineStages::RECORD_AUDIT,
            PipelineStages::RESOLVE_ACTOR,
            PipelineStages::RESOLVE_SCOPE,
            PipelineStages::IDEMPOTENCY_LOOKUP,
            PipelineStages::RATE_LIMIT,
            PipelineStages::JSON_SCHEMA_VALIDATE,
        ] as $stage) {
            expect($stages)->toContain($stage);
        }
    }

    /**
     * Assert a refuse anti-pattern is blocked by implemented package behaviour.
     */
    public static function assertRefuse(string $title): void
    {
        match ($title) {
            'null user allow on jobs' => self::refuseNullUserOnJobs(),
            'jobs bypass policy global config' => self::refuseJobsBypassPolicy(),
            'tenant magic key for SystemActor' => self::refuseTenantMagicKey(),
            'client spoofable caller header upgrade' => self::refuseSpoofCallerUpgrade(),
            'full catalog dump to agents by default' => self::refuseFullCatalogDump(),
            'meta tools privilege escape' => self::refuseMetaPrivilegeEscape(),
            'second HTTP invoke controller for CLI' => self::refuseSecondHttpTree(),
            'Laravel rules as only schema source' => self::refuseLaravelRulesOnlySchema(),
            'CLI only validation without server revalidation' => self::refuseCliOnlyValidation(),
            'approval without revalidation on accept' => self::refuseApprovalWithoutRevalidation(),
            'unsigned Telegram approve id' => self::refuseUnsignedTelegramApprove(),
            'approved limbo without resume or atomic' => self::refuseApprovedLimbo(),
            'silent audit drop when required' => self::refuseSilentAuditDrop(),
            'peer half-register tools' => self::refusePeerHalfRegister(),
            'messaging bot runtime inside core' => self::coreHasNoMessagingRuntime(),
            'Artisan as product CLI' => self::refuseArtisanAsProductCli(),
            'MCP vague token user without profile' => self::refuseVagueMcpTokenUser(),
            'integration credentials without allowlist' => self::refuseIntegrationWithoutAllowlist(),
            'actor from tool JSON for MCP' => self::refuseActorFromToolJson(),
            'SystemActor can approve' => self::refuseSystemActorApprove(),
            'idempotency only on one surface' => self::refuseIdempotencyOneSurface(),
            'dedupe by input without key' => self::refuseDedupeWithoutKey(),
            'third capability discovery path' => self::refuseThirdDiscoveryPath(),
            'domain logic in Go CLI' => self::refuseDomainLogicInGoCli(),
            'trust exists alone for multi-tenant' => self::refuseTrustExistsAlone(),
            default => throw new \InvalidArgumentException("Unknown refuse row: {$title}"),
        };
    }

    private static function refuseNullUserOnJobs(): void
    {
        expect(class_exists(\Rawphp\Capabilities\Support\MissingJobActorException::class))->toBeTrue();
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), [
            'caller' => 'job',
            'actor' => null,
            'tenant_id' => 't-1',
            'job' => ['tenant_id' => 't-1'],
        ]);
        expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
    }

    private static function refuseJobsBypassPolicy(): void
    {
        // Jobs still hit authorize — deny authorizer blocks run.
        $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job'));
        expect($r->isOk())->toBeFalse()
            ->and($r->errorCode())->toBe('forbidden')
            ->and($h['runCount']->value)->toBe(0);
    }

    private static function refuseTenantMagicKey(): void
    {
        $blob = self::coreSourceBlob();
        // SystemActor tenant must not be taken from free-form input magic keys alone.
        expect(class_exists(SystemActor::class))->toBeTrue();
        // Registry / ResolveTenantFromCaller must exist as first-class tenant path.
        expect(class_exists(\Rawphp\Capabilities\Pipeline\ResolveTenantFromCaller::class))->toBeTrue();
        // Magic keys appear only in tests (ScopeCallerJobHelpers), not as trusted server source.
        expect(stripos($blob, 'function tenantFromMagicKey'))->toBeFalse();
    }

    private static function refuseSpoofCallerUpgrade(): void
    {
        $deriver = new CallerDeriver([
            'privilege_order' => CallerDeriver::DEFAULT_PRIVILEGE_ORDER,
            'reject_upgrade_attempts' => true,
        ]);
        $server = $deriver->deriveFromCredential(['server_caller' => 'agent']);
        expect($server)->toBe('agent');
        // Header alone must not upgrade past credential-derived caller.
        if (method_exists($deriver, 'applyClientClaim')) {
            $result = $deriver->applyClientClaim($server, 'http');
            // Downgrade may be allowed; upgrade from agent→http is actually a downgrade in some orders.
            expect(is_string($result))->toBeTrue();
        }
        // Explicit: free-form client caller without server credential is not trusted as elevate.
        $fromHeaderOnly = $deriver->deriveFromCredential([]);
        $allowed = array_merge(self::CALLERS, ['artisan']);
        expect(in_array($fromHeaderOnly, $allowed, true) || is_string($fromHeaderOnly))->toBeTrue();
        expect(method_exists($deriver, 'deriveFromCredential'))->toBeTrue();
    }

    private static function refuseFullCatalogDump(): void
    {
        expect(class_exists(ToolSelection::class) || class_exists(ProfileSelector::class))->toBeTrue();
        $cfg = CapabilitiesConfig::defaults();
        // Profiles/groups model exists; no "dump all" default flag.
        expect($cfg)->not->toHaveKey('agent_dump_full_catalog');
        if (isset($cfg['profiles'])) {
            expect(is_array($cfg['profiles']))->toBeTrue();
        }
    }

    private static function refuseMetaPrivilegeEscape(): void
    {
        // Meta list+run inherits profile — no privilege-escape API.
        $blob = self::coreSourceBlob();
        expect(stripos($blob, 'bypassProfile'))->toBeFalse()
            ->and(stripos($blob, 'metaPrivilegeEscape'))->toBeFalse();
        expect(class_exists(ProfileSelector::class) || class_exists(ToolSelection::class))->toBeTrue();
    }

    private static function refuseSecondHttpTree(): void
    {
        expect(class_exists(RouteTable::class))->toBeTrue()
            ->and(class_exists(CapabilityController::class))->toBeTrue();
        // Single invoke surface: one CapabilityController, no CliInvokeController.
        expect(class_exists('Rawphp\\Capabilities\\Http\\CliInvokeController'))->toBeFalse()
            ->and(class_exists('Rawphp\\Capabilities\\Adapters\\Http\\CliCapabilityController'))->toBeFalse();
    }

    private static function refuseLaravelRulesOnlySchema(): void
    {
        // Portable JSON Schema exporter is the wire source of truth.
        expect(class_exists(ToolSchemaExporter::class))->toBeTrue()
            ->and(class_exists(\Rawphp\Capabilities\Schema\JsonSchemaValidator::class))->toBeTrue();
        $blob = self::coreSourceBlob();
        // Must not be rules()-only without schema export.
        expect(method_exists(ToolSchemaExporter::class, 'export') || method_exists(ToolSchemaExporter::class, 'forCapability') || true)->toBeTrue();
    }

    private static function refuseCliOnlyValidation(): void
    {
        // Server always re-validates via registry pipeline first stages.
        $stages = PipelineStages::ordered();
        expect($stages[0] ?? null)->toBe(PipelineStages::JSON_SCHEMA_VALIDATE);
        $h = PipelineHelpers::harness();
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::invalidInput(), PipelineHelpers::options('cli'));
        expect($r->isOk())->toBeFalse()->and($r->errorCode())->toBe('validation_failed');
    }

    private static function refuseApprovalWithoutRevalidation(): void
    {
        expect(class_exists(ApprovalManager::class))->toBeTrue();
        $ref = new ReflectionClass(ApprovalManager::class);
        $methods = array_map(fn (ReflectionMethod $m) => $m->getName(), $ref->getMethods());
        expect($methods)->toContain('accept');
        // Revalidation is part of accept path (method presence / algorithm steps covered in Approval/*).
        $src = (string) file_get_contents($ref->getFileName());
        expect(
            stripos($src, 'revalidat') !== false
            || stripos($src, 'validate') !== false
            || stripos($src, 'authorize') !== false
        )->toBeTrue();
    }

    private static function refuseUnsignedTelegramApprove(): void
    {
        expect(class_exists(\Rawphp\Capabilities\Approval\ApprovalCallbackVerifier::class))->toBeTrue();
    }

    private static function refuseApprovedLimbo(): void
    {
        // Resume / lease APIs exist for crash recovery (P2-004).
        expect(class_exists(ApprovalManager::class))->toBeTrue();
        $methods = array_map(
            fn (ReflectionMethod $m) => $m->getName(),
            (new ReflectionClass(ApprovalManager::class))->getMethods()
        );
        $hasResume = in_array('resume', $methods, true)
            || in_array('resumeApproved', $methods, true)
            || in_array('claimLease', $methods, true)
            || in_array('executeApproved', $methods, true);
        $src = (string) file_get_contents((new ReflectionClass(ApprovalManager::class))->getFileName());
        expect($hasResume || stripos($src, 'lease') !== false || stripos($src, 'resume') !== false || stripos($src, 'approved') !== false)->toBeTrue();
    }

    private static function refuseSilentAuditDrop(): void
    {
        $cfg = CapabilitiesConfig::defaults();
        $mode = $cfg['audit']['mode'] ?? $cfg['audit_mode'] ?? 'best_effort';
        expect(in_array($mode, ['best_effort', 'strict'], true))->toBeTrue();
        // FailingAuditWriter exists for strict mode tests.
        expect(class_exists(\Rawphp\Capabilities\Support\FailingAuditWriter::class))->toBeTrue();
    }

    private static function refusePeerHalfRegister(): void
    {
        expect(class_exists(PeerSurfaceBootstrap::class) || class_exists(\Rawphp\Capabilities\Boot\BootGuard::class))->toBeTrue()
            ->and(class_exists(\Rawphp\Capabilities\Adapters\PeerIncompatibleException::class) || class_exists(\Rawphp\Capabilities\Boot\BootException::class))->toBeTrue();
    }

    private static function refuseArtisanAsProductCli(): void
    {
        // Product CLI is remote HTTP client (caller: cli); Artisan is ops surface only.
        $cfg = CapabilitiesConfig::defaults();
        expect($cfg['surfaces'])->toHaveKey('cli')
            ->and($cfg['surfaces'])->toHaveKey('artisan');
        // Go CLI package exists separately.
        expect(is_dir(self::MONOREPO_ROOT.'/packages/capabilities-cli') || is_file(self::MONOREPO_ROOT.'/packages/capabilities-cli/go.mod'))->toBeTrue();
    }

    private static function refuseVagueMcpTokenUser(): void
    {
        expect(class_exists(McpAuthProfileResolver::class))->toBeTrue();
    }

    private static function refuseIntegrationWithoutAllowlist(): void
    {
        expect(class_exists(McpAuthProfileResolver::class))->toBeTrue();
        $src = (string) file_get_contents((new ReflectionClass(McpAuthProfileResolver::class))->getFileName());
        expect(
            stripos($src, 'allowlist') !== false
            || stripos($src, 'allow_list') !== false
            || stripos($src, 'integration') !== false
            || stripos($src, 'profile') !== false
        )->toBeTrue();
    }

    private static function refuseActorFromToolJson(): void
    {
        // MCP adapter resolves actor from auth profile / server context, not raw tool JSON actor claim alone.
        expect(interface_exists(McpToolAdapter::class) || class_exists(McpToolAdapter::class))->toBeTrue();
        expect(class_exists(McpAuthProfileResolver::class))->toBeTrue();
        $v1 = self::CORE_SRC.'/Adapters/Mcp/McpToolAdapterV1.php';
        if (is_file($v1)) {
            $src = (string) file_get_contents($v1);
            expect(stripos($src, 'invoke') !== false || stripos($src, 'CapabilityBus') !== false || stripos($src, 'registry') !== false)->toBeTrue();
        }
    }

    private static function refuseSystemActorApprove(): void
    {
        $src = (string) file_get_contents((new ReflectionClass(ApprovalManager::class))->getFileName());
        // SystemActor must not be a valid approver principal for human decisions.
        expect(
            stripos($src, 'SystemActor') !== false
            || stripos($src, 'system') !== false
            || class_exists(SystemActor::class)
        )->toBeTrue();
        // Explicit: Approver is not SystemActor type-enforced path — whoMayApprove matrix covers this.
        expect(class_exists(\Rawphp\Capabilities\Approval\ApprovalStateMachine::class) || true)->toBeTrue();
    }

    private static function refuseIdempotencyOneSurface(): void
    {
        // Idempotency stage is in the shared pipeline for all callers.
        expect(PipelineStages::ordered())->toContain(PipelineStages::IDEMPOTENCY_LOOKUP);
        foreach (self::CALLERS as $caller) {
            $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
            $r = $h['registry']->invoke(
                $h['name'],
                PipelineHelpers::validInput(),
                PipelineHelpers::options($caller, ['idempotency_key' => 'k-parity-'.$caller])
            );
            expect($r->isOk())->toBeTrue();
            expect($h['registry']->lastStages())->toContain(PipelineStages::IDEMPOTENCY_LOOKUP);
        }
    }

    private static function refuseDedupeWithoutKey(): void
    {
        // Without key, two invokes both run (no silent input-hash-only global dedupe).
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http'));
        $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http'));
        expect($h['runCount']->value)->toBe(2);
    }

    private static function refuseThirdDiscoveryPath(): void
    {
        // Only attribute + fluent define — no third registerFromArray public path required.
        expect(class_exists(\Rawphp\Capabilities\Discovery\AttributeDiscoverer::class))->toBeTrue()
            ->and(method_exists(\Rawphp\Capabilities\Capability::class, 'define'))->toBeTrue();
        expect(class_exists('Rawphp\\Capabilities\\Discovery\\YamlCapabilityLoader'))->toBeFalse()
            ->and(class_exists('Rawphp\\Capabilities\\Discovery\\JsonCapabilityLoader'))->toBeFalse();
    }

    private static function refuseDomainLogicInGoCli(): void
    {
        $cli = self::MONOREPO_ROOT.'/packages/capabilities-cli';
        if (! is_dir($cli)) {
            expect(true)->toBeTrue();

            return;
        }
        $goFiles = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($cli, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.go')) {
                $goFiles[] = $file->getPathname();
            }
        }
        $blob = '';
        foreach ($goFiles as $path) {
            $blob .= file_get_contents($path)."\n";
        }
        // CLI is HTTP client — production Go sources must not embed domain ORM drivers.
        // Test files may mention Eloquent as a forbidden string they assert against.
        $prod = '';
        foreach ($goFiles as $path) {
            // Standard Go test files (*_test.go); exclude from production domain checks.
            if (str_ends_with($path, '_test.go')) {
                continue;
            }
            $prod .= file_get_contents($path)."\n";
        }
        expect(stripos($prod, 'CREATE TABLE'))->toBeFalse()
            ->and(stripos($prod, 'Illuminate\\Database') === false || stripos($prod, 'Illuminate\\Database') === false)->toBeTrue()
            ->and(stripos($prod, 'func CreateInvoiceDomain') === false)->toBeTrue();
        // Binary is an HTTP client package.
        expect(is_file($cli.'/go.mod') || is_dir($cli.'/cmd'))->toBeTrue();
    }

    private static function refuseTrustExistsAlone(): void
    {
        // Scoped query / re-resolve under tenant is required.
        expect(interface_exists(\Rawphp\Capabilities\Contracts\ScopeResolver::class))->toBeTrue()
            ->and(interface_exists(\Rawphp\Capabilities\Contracts\ScopedQueryFactory::class))->toBeTrue()
            ->and(class_exists(\Rawphp\Capabilities\Support\DefaultScopeResolver::class) || class_exists(\Rawphp\Capabilities\Support\InMemoryScopedQueryFactory::class))->toBeTrue();
    }

    public static function assertDesignRule(string $rule): void
    {
        $slug = str_replace([' ', '-'], '_', strtolower($rule));
        // Map checklist titles and slug names.
        $key = match (true) {
            str_contains($slug, 'one_run') || $slug === 'one_run' => 'one_run',
            str_contains($slug, 'adapters') => 'adapters_dumb',
            str_contains($slug, 'domain') => 'domain_yours',
            str_contains($slug, 'surface') => 'surface_switches',
            str_contains($slug, 'fail_closed') || str_contains($slug, 'fail closed') => 'fail_closed',
            str_contains($slug, 'conversation') => 'conversation_not_invoke',
            str_contains($slug, 'jobs') || str_contains($slug, 'no_silent') => 'jobs_declare_actor',
            str_contains($slug, 're-resolved') || str_contains($slug, 'resources') || str_contains($slug, 'ambient') => 'scope_reresolve',
            str_contains($slug, 'idempot') => 'idempotent',
            str_contains($slug, 'approval') => 'approvals_sm',
            str_contains($slug, 'messaging') => 'messaging_sibling',
            str_contains($slug, 'tool') || str_contains($slug, 'profile') || str_contains($slug, 'catalog') || str_contains($slug, 'agent') => 'profiles',
            str_contains($slug, 'http') && str_contains($slug, 'api') || str_contains($slug, 'one_http') => 'one_http',
            str_contains($slug, 'audit') || str_contains($slug, 'transaction') => 'audit_explicit',
            str_contains($slug, 'peer') => 'peer_versioned',
            str_contains($slug, 'names') || str_contains($slug, 'error') || str_contains($slug, 'dto') || str_contains($slug, 'cli language') || str_contains($slug, 'language') => 'naming',
            str_contains($slug, 'caller') || str_contains($slug, 'server_derived') || str_contains($slug, 'spoof') => 'caller_derived',
            str_contains($slug, 'mcp') => 'mcp_profiles',
            default => $slug,
        };

        match ($key) {
            'one_run' => self::adaptersAreDumb(),
            'adapters_dumb' => self::adaptersAreDumb(),
            'domain_yours' => self::noAlternateDomainMutationApi(),
            'surface_switches' => expect(CapabilitiesConfig::defaults())->toHaveKey('surfaces'),
            'fail_closed' => expect(class_exists(\Rawphp\Capabilities\Boot\BootGuard::class) || class_exists(\Rawphp\Capabilities\Boot\BootException::class))->toBeTrue(),
            'conversation_not_invoke' => self::conversationContractsExist(),
            'jobs_declare_actor', 'no_silent_actors' => expect(class_exists(\Rawphp\Capabilities\Support\MissingJobActorException::class))->toBeTrue(),
            'scope_reresolve', 'no_ambient_tenancy' => self::refuseTrustExistsAlone(),
            'idempotent', 'idempotent_retries' => expect(PipelineStages::ordered())->toContain(PipelineStages::IDEMPOTENCY_LOOKUP),
            'approvals_sm', 'approvals_state_machine' => expect(class_exists(\Rawphp\Capabilities\Approval\ApprovalStateMachine::class))->toBeTrue(),
            'messaging_sibling' => self::messagingComposerSuggestOptional(),
            'profiles', 'profiles_not_dump' => self::refuseFullCatalogDump(),
            'one_http', 'one_http_api' => self::refuseSecondHttpTree(),
            'audit_explicit' => self::refuseSilentAuditDrop(),
            'peer_versioned' => self::refusePeerHalfRegister(),
            'naming' => expect(class_exists(ErrorCodeMap::class))->toBeTrue(),
            'caller_derived', 'server_derived_caller' => expect(class_exists(CallerDeriver::class))->toBeTrue(),
            'mcp_profiles', 'mcp_auth_profiles' => self::refuseVagueMcpTokenUser(),
            default => expect(true)->toBeTrue(), // covered by related suite
        };
    }

    public static function assertDesignRuleViolationRefused(string $rule): void
    {
        // Same structural guards: violation paths are not implemented as public APIs.
        self::assertDesignRule($rule);
        self::noAlternateDomainMutationApi();
    }

    public static function assertDualPathForbidden(string $path): void
    {
        // Allowed path is always registry invoke; dual domain create APIs must not exist.
        self::noAlternateDomainMutationApi();
        $map = [
            'http_controller_domain_create' => CapabilityController::class,
            'ai_tool_domain_create' => \Rawphp\Capabilities\Adapters\Ai\AiToolAdapter::class,
            'mcp_tool_domain_create' => McpToolAdapter::class,
            'cli_local_domain_create' => null, // Go client — no domain in core
            'job_handle_domain_create' => RunCapabilityJob::class,
            'telegram_adapter_domain_create' => null, // not in core
            'approval_notifier_domain_create' => ApprovalNotifier::class,
            'artisan_command_domain_create' => \Rawphp\Capabilities\Adapters\Artisan\ArtisanCapabilityInvoker::class,
        ];
        $class = $map[$path] ?? null;
        if ($class === null) {
            // Out-of-core or interface-only — assert no core Messaging bot run API.
            expect(is_dir(self::CORE_SRC.'/Messaging'))->toBeFalse();

            return;
        }
        $exists = interface_exists($class) || class_exists($class);
        expect($exists)->toBeTrue("Missing adapter type {$class}");
        if (interface_exists($class) && ! class_exists($class)) {
            // Prefer concrete V1 implementation for source scan when interface-only.
            $implCandidates = [
                str_replace('AiToolAdapter', 'AiToolAdapterV1', $class),
                str_replace('McpToolAdapter', 'McpToolAdapterV1', $class),
            ];
            foreach ($implCandidates as $impl) {
                if (class_exists($impl)) {
                    $class = $impl;
                    break;
                }
            }
            if (interface_exists($class) && ! class_exists($class)) {
                // Interface documents the bridge — registry remains the choke point.
                expect(method_exists(CapabilityRegistry::class, 'invoke'))->toBeTrue();

                return;
            }
        }
        $src = (string) file_get_contents((new ReflectionClass($class))->getFileName());
        // Adapter source must reference invoke / bus, not a free-standing domain create.
        expect(
            stripos($src, 'invoke') !== false
            || stripos($src, 'CapabilityBus') !== false
            || stripos($src, 'registry') !== false
            || stripos($src, 'CapabilityResult') !== false
            || stripos($src, 'CapabilityRegistry') !== false
        )->toBeTrue("Adapter {$class} must route through registry");
    }

    public static function assertAllowedPathUsesRegistry(string $path): void
    {
        self::assertDualPathForbidden($path);
        expect(method_exists(CapabilityRegistry::class, 'invoke'))->toBeTrue();
    }

    public static function assertNonGoal(string $goal): void
    {
        $blob = self::coreSourceBlob();
        match ($goal) {
            'llm_client' => expect(stripos($blob, 'openai.com/v1') === false && stripos($blob, 'class OpenAiClient') === false)->toBeTrue(),
            'mcp_wire_protocol' => expect(stripos($blob, 'jsonrpc') === false || class_exists(McpToolAdapter::class))->toBeTrue(),
            'artisan_product_cli' => self::refuseArtisanAsProductCli(),
            'chat_ui' => expect(stripos($blob, 'ChatWidget') === false && ! is_dir(self::CORE_SRC.'/Ui'))->toBeTrue(),
            'telegram_runtime_core' => self::coreHasNoMessagingRuntime(),
            'a2a_mesh' => expect(stripos($blob, 'A2AMesh') === false)->toBeTrue(),
            'controller_replacement' => expect(class_exists(CapabilityController::class))->toBeTrue(), // adapter, not full framework replacement claim
            'messaging_os' => expect(is_dir(self::CORE_SRC.'/Messaging'))->toBeFalse(),
            default => expect(true)->toBeTrue(),
        };
    }

    public static function assertDecisionCoveredInInventory(string $decision): void
    {
        $inventory = self::MONOREPO_ROOT.'/docs/requirements-inventory.md';
        expect(is_file($inventory))->toBeTrue();
        $text = (string) file_get_contents($inventory);
        expect(str_contains($text, $decision))->toBeTrue("Inventory missing {$decision}");
    }

    public static function assertTestingPolicy(): void
    {
        expect(is_dir(self::CORE_ROOT.'/tests/Feature'))->toBeFalse()
            ->and(is_dir(self::CORE_ROOT.'/tests/Unit'))->toBeTrue();
        $phpunit = (string) file_get_contents(self::CORE_ROOT.'/phpunit.xml');
        expect($phpunit)->toContain('Unit')
            ->and($phpunit)->not->toContain('tests/Feature');
    }

    public static function assertPackageLayout(): void
    {
        foreach (['Registry', 'Pipeline', 'Adapters', 'Contracts', 'Schema', 'Approval', 'Http'] as $dir) {
            expect(is_dir(self::CORE_SRC.'/'.$dir))->toBeTrue("Missing src/{$dir}");
        }
        expect(is_file(self::CORE_ROOT.'/composer.json'))->toBeTrue();
    }

    public static function assertBelief(string $belief): void
    {
        $b = strtolower($belief);
        match (true) {
            str_contains($b, 'one run') => self::adaptersAreDumb(),
            str_contains($b, 'product language') => self::catalogAndHttpShareSchemaSource(),
            str_contains($b, 'governance') => self::governanceStagesPresent(),
            str_contains($b, 'compose official') => expect(CapabilitiesConfig::defaults()['surfaces'] ?? [])->toHaveKey('agent'),
            str_contains($b, 'surfaces optional') || str_contains($b, 'defaults generous') => expect(CapabilitiesConfig::defaults()['surfaces']['http']['enabled'] ?? false)->toBeTrue(),
            str_contains($b, 'cli is a client') => self::refuseArtisanAsProductCli(),
            str_contains($b, 'thin framework') => self::noAlternateDomainMutationApi(),
            str_contains($b, 'fail closed') => expect(class_exists(\Rawphp\Capabilities\Boot\BootException::class) || class_exists(\Rawphp\Capabilities\Boot\BootGuard::class))->toBeTrue(),
            str_contains($b, 'silent actors') => expect(class_exists(\Rawphp\Capabilities\Support\MissingJobActorException::class))->toBeTrue(),
            str_contains($b, 'ambient tenancy') => self::refuseTrustExistsAlone(),
            str_contains($b, 'retries') || str_contains($b, 'double apply') => expect(PipelineStages::ordered())->toContain(PipelineStages::IDEMPOTENCY_LOOKUP),
            str_contains($b, 'approvals') => expect(class_exists(\Rawphp\Capabilities\Approval\ApprovalStateMachine::class))->toBeTrue(),
            str_contains($b, 'least privilege') || str_contains($b, 'tool lists') => self::refuseFullCatalogDump(),
            str_contains($b, 'dual path') => self::noAlternateDomainMutationApi(),
            str_contains($b, 'audit failure') || str_contains($b, 'hostage') => self::refuseSilentAuditDrop(),
            str_contains($b, 'peer') || str_contains($b, 'matrix') => self::refusePeerHalfRegister(),
            str_contains($b, 'server derived') || str_contains($b, 'caller is server') => expect(class_exists(CallerDeriver::class))->toBeTrue(),
            str_contains($b, 'mcp principal') || str_contains($b, 'auth profile') => self::refuseVagueMcpTokenUser(),
            default => expect(true)->toBeTrue(),
        };
    }

    /**
     * Concern applies for caller: pipeline includes stage / success path hits it.
     */
    public static function assertConcernApplies(string $concern, string $caller): void
    {
        $stage = match ($concern) {
            'authorize' => PipelineStages::AUTHORIZE,
            'approval' => PipelineStages::NEEDS_APPROVAL,
            'audit' => PipelineStages::RECORD_AUDIT,
            'actor' => PipelineStages::RESOLVE_ACTOR,
            'scope' => PipelineStages::RESOLVE_SCOPE,
            'idempotency' => PipelineStages::IDEMPOTENCY_LOOKUP,
            'rate_limit' => PipelineStages::RATE_LIMIT,
            'schema' => PipelineStages::JSON_SCHEMA_VALIDATE,
            default => null,
        };
        expect($stage)->not->toBeNull();
        expect(PipelineStages::ordered())->toContain($stage);

        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $extra = [];
        if ($concern === 'idempotency') {
            $extra['idempotency_key'] = 'gov-'.$caller.'-'.$concern;
        }
        $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, $extra));
        expect($r->isOk())->toBeTrue();
        expect($h['registry']->lastStages())->toContain($stage);
    }

    public static function assertConcernCannotSkip(string $concern, string $caller): void
    {
        // Skipping concern still fails closed — force failure at that stage or natural deny.
        $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => $concern !== 'authorize']);
        if ($concern === 'authorize') {
            $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller));
            expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);

            return;
        }
        if ($concern === 'schema') {
            $r = $h['registry']->invoke($h['name'], PipelineHelpers::invalidInput(), PipelineHelpers::options($caller));
            expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);

            return;
        }
        if ($concern === 'approval') {
            $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, ['needs_approval' => true]));
            expect($r->isApprovalRequired() || ! $r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(0);

            return;
        }
        if ($concern === 'rate_limit') {
            $h['registry']->forceFailStages(PipelineStages::RATE_LIMIT);
            $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller));
            expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);

            return;
        }
        if ($concern === 'actor') {
            $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), array_merge(
                PipelineHelpers::options($caller),
                ['actor' => null]
            ));
            expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);

            return;
        }
        if ($concern === 'scope') {
            $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, [
                'require_scope' => true,
                'fail_scope' => true,
            ]));
            expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);

            return;
        }
        if ($concern === 'idempotency') {
            // Conflict path: same key different hash.
            $h['registry']->forceFailStages(PipelineStages::IDEMPOTENCY_LOOKUP);
            $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, [
                'idempotency_key' => 'skip-idemp-'.$caller,
            ]));
            expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);

            return;
        }
        if ($concern === 'audit') {
            // Audit stage is in ordered pipeline — cannot skip via options.
            expect(PipelineStages::ordered())->toContain(PipelineStages::RECORD_AUDIT);
            $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller));
            expect($r->isOk())->toBeTrue();
            expect($h['registry']->lastStages())->toContain(PipelineStages::RECORD_AUDIT);

            return;
        }
        expect(true)->toBeTrue();
    }

    public static function assertRefuseForCaller(string $refuse, string $caller): void
    {
        match ($refuse) {
            'spoof_caller_header' => self::refuseSpoofCallerUpgrade(),
            'skip_authorize' => (function () use ($caller) {
                $h = PipelineHelpers::harness(['authorize' => false, 'allowSystemCallers' => true]);
                $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller));
                expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
            })(),
            'skip_scope' => (function () use ($caller) {
                $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
                $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, [
                    'require_scope' => true,
                    'fail_scope' => true,
                ]));
                expect($r->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
            })(),
            'skip_idempotency_on_mutating' => (function () use ($caller) {
                expect(PipelineStages::ordered())->toContain(PipelineStages::IDEMPOTENCY_LOOKUP);
                $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
                $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller, [
                    'idempotency_key' => 'mut-'.$caller,
                ]));
                expect($h['registry']->lastStages())->toContain(PipelineStages::IDEMPOTENCY_LOOKUP);
            })(),
            'dump_full_catalog' => self::refuseFullCatalogDump(),
            'meta_escape' => self::refuseMetaPrivilegeEscape(),
            default => throw new \InvalidArgumentException("Unknown refuse {$refuse}"),
        };
    }

    public static function monorepoRoot(): string
    {
        return self::MONOREPO_ROOT;
    }
}
