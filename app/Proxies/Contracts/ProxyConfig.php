<?php

namespace App\Proxies\Contracts;

interface ProxyConfig
{
    public function getProtocol(): string;

    public function getHost(): string;

    public function getPort(): int;

    public function getUser(): string;

    public function getPassword(): string;
}
