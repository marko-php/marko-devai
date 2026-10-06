<?php

declare(strict_types=1);

use Marko\DevAi\Process\CommandRunner;

describe('CommandRunner', function (): void {
    it('captures both stdout and stderr from a command', function (): void {
        $runner = new CommandRunner();
        $result = $runner->run('sh', ['-c', 'echo hello; echo world >&2']);

        expect($result['stdout'])->toContain('hello')
            ->and($result['stderr'])->toContain('world');
    });

    it('preserves the child process exit code', function (): void {
        $runner = new CommandRunner();
        $result = $runner->run('sh', ['-c', 'exit 42']);

        expect($result['exitCode'])->toBe(42);
    });

    it('completes without hanging when the child writes more than 64KB to stderr', function (): void {
        $runner = new CommandRunner();
        // Write ~128KB to stderr and some bytes to stdout; if sequential drain the parent blocks
        $script = 'printf "%s" "$(head -c 131072 /dev/zero | tr "\\0" "x")" >&2; echo ok';
        $started = microtime(true);
        $result = $runner->run('sh', ['-c', $script]);
        $elapsed = microtime(true) - $started;

        expect($elapsed)->toBeLessThan(10.0)
            ->and($result['stdout'])->toContain('ok')
            ->and(strlen($result['stderr']))->toBeGreaterThan(65536);
    })->group('integration');

    it('does not block when the child reads stdin (stdin detached to /dev/null)', function (): void {
        $runner = new CommandRunner();
        // A child that reads stdin would deadlock forever if it inherited the parent's
        // TTY. With stdin detached to /dev/null it gets immediate EOF and returns.
        $started = microtime(true);
        $result = $runner->run('sh', ['-c', 'read line; echo "done"']);
        $elapsed = microtime(true) - $started;

        expect($elapsed)->toBeLessThan(10.0)
            ->and($result['stdout'])->toContain('done')
            ->and($result['exitCode'])->toBe(0);
    });

    it('returns a non-zero exit code and a helpful stderr when the executable does not exist', function (): void {
        $result = (new CommandRunner())->run('/nonexistent/binary/that/does/not/exist/xyz123');

        expect($result['exitCode'])->toBe(127)
            ->and($result['stdout'])->toBe('')
            ->and($result['stderr'])->toContain('/nonexistent/binary/that/does/not/exist/xyz123');
    });

    it('runs an executable whose path contains spaces as a single command', function (): void {
        $root = sys_get_temp_dir() . '/devai runner ' . uniqid();
        $dir = $root . '/My Apps/vendor/bin';
        mkdir($dir, 0755, true);
        $bin = $dir . '/marko';
        file_put_contents($bin, "#!/bin/sh\necho \"ran with: \$1\"\n");
        chmod($bin, 0755);

        try {
            $result = (new CommandRunner())->run($bin, ['mcp:serve']);
        } finally {
            unlink($bin);
            rmdir($dir);
            rmdir(dirname($dir));
            rmdir(dirname($dir, 2));
            rmdir($root);
        }

        expect($result['exitCode'])->toBe(0)
            ->and($result['stdout'])->toContain('ran with: mcp:serve');
    });

    it('passes arguments verbatim without shell interpretation', function (): void {
        $result = (new CommandRunner())->run('printf', ['%s', 'a b; echo injected $HOME']);

        expect($result['stdout'])->toBe('a b; echo injected $HOME');
    });

    it('reports a binary on PATH and rejects a missing one', function (): void {
        $runner = new CommandRunner();

        expect($runner->isOnPath('sh'))->toBeTrue()
            ->and($runner->isOnPath('definitely-not-a-real-binary-xyz123'))->toBeFalse()
            ->and($runner->isOnPath('sh; echo injected'))->toBeFalse();
    });
});
