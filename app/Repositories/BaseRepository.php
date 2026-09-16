<?php 

namespace App\Repositories;

use App\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Ramsey\Collection\Collection;

abstract class BaseRepository implements BaseRepositoryInterface {
    protected Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function getAll()
    {
       return $this->model->all();

    }

    public function count()
    {
        return $this->model->count();
    }

    
}