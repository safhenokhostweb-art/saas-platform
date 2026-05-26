<?php

namespace SaaSPlatform\CoreUtils\Contracts\Platform;

interface ResellerModelInterface
{
    public function getId(): int;
    public function getKey(): mixed;
    public function getDomain(): ?string;
    public function getName(): ?string;
}
