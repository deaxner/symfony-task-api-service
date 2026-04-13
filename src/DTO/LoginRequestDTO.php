<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class LoginRequestDTO
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public ?string $email = null;

    #[Assert\NotBlank]
    public ?string $password = null;
}
