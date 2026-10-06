<?php

declare(strict_types=1);

use Marko\DevAi\Agents\ClaudeCodeAgent;
use Marko\DevAi\Exceptions\DevAiInstallException;
use Marko\DevAi\Installation\AgentRegistry;
use Marko\Testing\Fake\FakeConfigRepository;

/**
 * Install claude-code through the registry into an external (non-monorepo) project
 * and return the marketplace source it registered.
 *
 * @return array<string, mixed>
 */
function registryMarketplaceSource(
    AgentRegistry $registry,
): array {
    $root = devaiTempDir();

    try {
        $registry->all($root)['claude-code']->install(devaiContext(skipLspDeps: true), $root);

        return json_decode(
            (string) file_get_contents($root . '/.claude/settings.json'),
            true,
        )['extraKnownMarketplaces']['marko']['source'];
    } finally {
        devaiRemoveDir($root);
    }
}

it('pins the claude-code marketplace to the configured ref', function (): void {
    $registry = new AgentRegistry(
        devaiRunner(),
        new FakeConfigRepository(['devai.claude_code.marketplace_ref' => '0123abcd']),
    );

    expect(registryMarketplaceSource($registry))->toBe([
        'source' => 'github',
        'repo' => 'marko-php/marko',
        'ref' => '0123abcd',
    ]);
});

it('pins the claude-code marketplace to a release tag when no ref is configured', function (): void {
    $registry = new AgentRegistry(
        devaiRunner(),
        new FakeConfigRepository(['devai.claude_code.marketplace_ref' => null]),
    );

    expect(registryMarketplaceSource($registry)['ref'])->toMatch('/^v?\d+\.\d+\.\d+$/')
        ->and(ClaudeCodeAgent::DEFAULT_MARKETPLACE_REF)->toMatch('/^\d+\.\d+\.\d+$/');
});

it('throws a helpful exception when the configured marketplace ref is empty', function (): void {
    $registry = new AgentRegistry(
        devaiRunner(),
        new FakeConfigRepository(['devai.claude_code.marketplace_ref' => '  ']),
    );

    expect(fn () => $registry->all('/nonexistent-project-root'))
        ->toThrow(DevAiInstallException::class, "Invalid devai config value for 'devai.claude_code.marketplace_ref'");
});
