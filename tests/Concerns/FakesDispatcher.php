<?php

namespace Tests\Concerns;

use Illuminate\Contracts\Bus\Dispatcher;

trait FakesDispatcher
{
    protected function fakeNoOpDispatcher(): void {
        $this->app->bind(Dispatcher::class, fn() => new class implements Dispatcher {
            public function dispatch($command) {
                //
            }

            public function dispatchSync($command, $handler = null) {
                $this->dispatch($command);
            }

            public function dispatchNow($command, $handler = null) {
                $this->dispatch($command);
            }

            public function hasCommandHandler($command) {
                return false;
            }

            public function getCommandHandler($command) {
                return null;
            }

            public function pipeThrough(array $pipes) {
                throw new \Exception('Not implemented');
            }

            public function map(array $map) {
                throw new \Exception('Not implemented');
            }
        });
    }

    protected function fakeExceptionSuppressingDispatcher(): void {
        $this->app->bind(Dispatcher::class, fn() => new class implements Dispatcher {
            public function dispatch($command) {
                try {
                    app()->call([$command, 'handle']);
                } catch (\Throwable $t) {
                    //
                }
            }

            public function dispatchSync($command, $handler = null) {
                $this->dispatch($command);
            }

            public function dispatchNow($command, $handler = null) {
                $this->dispatch($command);
            }

            public function hasCommandHandler($command) {
                return false;
            }

            public function getCommandHandler($command) {
                return null;
            }

            public function pipeThrough(array $pipes) {
                throw new \Exception('Not implemented');
            }

            public function map(array $map) {
                throw new \Exception('Not implemented');
            }
        });
    }
}
