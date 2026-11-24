<?php

namespace App\DTOs;

readonly class RegisterDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public string $organization,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            email: strtolower(trim($data['email'])),
            password: $data['password'],
            organization: $data['organization'] ?? 'Default Organization',
        );
    }
}
