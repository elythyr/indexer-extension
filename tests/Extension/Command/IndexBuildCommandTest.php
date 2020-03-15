<?php

namespace Phpactor\WorkspaceQuery\Tests\Extension\Command;

use Phpactor\WorkspaceQuery\Tests\IntegrationTestCase;
use Symfony\Component\Process\Process;

class IndexBuildCommandTest extends IntegrationTestCase
{
    public function testRefreshIndex(): void
    {
        $this->initProject();

        $process = new Process([
            __DIR__ . '/../../../bin/console',
            'index:build',
        ], $this->workspace()->path());
        $process->mustRun();
        self::assertEquals(0, $process->getExitCode());
    }
}