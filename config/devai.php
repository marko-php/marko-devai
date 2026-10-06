<?php

declare(strict_types=1);

return [
    'claude_code' => [
        // Git tag or commit the Claude Code plugin marketplace (github: marko-php/marko) is pinned to.
        // null pins it to the installed marko/devai release, falling back to a known-good tag on dev
        // installs. Claude Code auto-installs marketplace plugins, so never point this at a branch.
        'marketplace_ref' => null,
    ],
    'guidelines' => [
        // Third-party (non marko/*) packages whose resources/ai/guidelines.md may be copied into
        // AGENTS.md/CLAUDE.md. null includes every installed package, each under a header naming it
        // as third-party. A list (e.g. ['acme/blog']) includes only those packages; [] includes none.
        'allow_packages' => null,
    ],
];
