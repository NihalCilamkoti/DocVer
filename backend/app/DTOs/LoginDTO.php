<?php

namespace App\DTOs;

readonly class LoginDTO
{
    public function __construct(
        public string $email,
        public string $password,
        public bool $remember = false,
        public ?string $device = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            email: strtolower(trim($data['email'])),
            password: $data['password'],
            remember: (bool) ($data['remember'] ?? false),
            device: $data['device'] ?? null,
        );
    }
}
