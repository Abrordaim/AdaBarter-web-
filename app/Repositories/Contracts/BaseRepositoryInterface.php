<?php

namespace App\Repositories\Contracts;

use Ramsey\Collection\Collection;

interface BaseRepositoryInterface {
    public function getAll();
    public function count();
}