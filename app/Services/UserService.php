<?php

namespace App\Services;

use App\Repositories\Contracts\UserRepositoryInterface;

class UserService {
    public function __construct(
        protected UserRepositoryInterface $userRepository 
    )
    {}

    public function countUser($role)
    {
        return $this->userRepository->countByRole($role) ;
    }
}