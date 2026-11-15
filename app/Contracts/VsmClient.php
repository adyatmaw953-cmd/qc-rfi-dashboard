<?php

namespace App\Contracts;

interface VsmClient
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function projects(): array;
}
