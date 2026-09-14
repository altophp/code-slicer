<?php

declare(strict_types=1);

namespace Alto\Code\Slicer\Tests\Fixtures;

#[\Attribute]
final class ExampleService
{
    public function configure(): void
    {
        $callback = static function (): string {
            return 'configured';
        };

        $callback();
    }

    /**
     * Execute the documented example.
     */
    #[\Deprecated]
    public function execute(): int
    {
        // Keep this explanation visible in UX demos.
        return 42;
    }

    private function finish(): void
    {
    }
}
