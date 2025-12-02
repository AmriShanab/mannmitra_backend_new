<?php

namespace App\Services;

use App\Interfaces\UserRepositoryInterface;

class UserService
{
    protected $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function changeAppLanguage($userId, $code)
    {
        return $this->userRepository->updateLanguage($userId, $code);

    }
}