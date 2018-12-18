<?php
declare(strict_types=1);

namespace Paysera\Pagination\Tests\Functional\Fixtures;

use Psr\Log\AbstractLogger;

class TestLogger extends AbstractLogger
{
    private $logs;

    public function __construct()
    {
        $this->logs = [];
    }

    public function log($level, $message, array $context = [])
    {
        $this->logs[] = [$level, $message, $context];
    }

    public function getLogs(): array
    {
        return $this->logs;
    }
}
