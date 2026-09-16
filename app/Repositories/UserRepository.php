<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

class UserRepository extends BaseRepository implements UserRepositoryInterface {
    
    
    public function __construct(User $model)
    {
        return parent::__construct($model);
    }

    public function countByRole ($role) {
        return $this->model->where('role',$role)->count();
    }
}