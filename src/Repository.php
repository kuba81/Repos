<?php

namespace Daveawb\Repos;

use Daveawb\Repos\Contracts\AllowCriteria;
use Daveawb\Repos\Contracts\AllowTerminators;
use Daveawb\Repos\Contracts\RepositoryStandards;
use Daveawb\Repos\Exceptions\RepositoryException;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;

/**
 * Class Repository
 * @package Daveawb\Repos
 */
abstract class Repository implements RepositoryStandards, AllowCriteria, AllowTerminators {

    /**
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $model;

    /**
     * @var Container
     */
    protected $app;

    /**
     * @var Collection
     */
    protected $criteria;

    /**
     * @var bool
     */
    protected $skipCriteria = false;

    /**
     * On construct create the model
     * @param Container $app
     * @param Collection $criteria
     */
    public function __construct(Container $app, Collection $criteria)
    {
        $this->app = $app;
        $this->model = $this->newModel();
        $this->criteria = $criteria;
    }

    /**
     * Retrieve all models
     *
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findAll(array $columns = ['*'])
    {
        $this->applyCriteria();

        return $this->model->get($columns);
    }

    /**
     * Find models where $column === $id
     *
     * @param $field
     * @param $id
     * @return \Illuminate\Database\Eloquent\Model
     * @throws RepositoryException
     */
    public function findBy($field, $id, array $columns = ['*'])
    {
        $this->applyCriteria();

        $model = $this->model->where($field, $id)->first($columns);

        if ( ! $model )
            throw new RepositoryException("Model does not exist.");

        return $model;
    }

    /**
     * Find models using $method as the terminator
     *
     * @param $method
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model|void
     * @throws RepositoryException
     */
    public function findByMethod($method, array $columns = ['*'])
    {
        $this->applyCriteria();

        $result = $this->model->{$method}($columns);

        if ( ! $result )
            throw new RepositoryException("Method {{$method}} does not exist on the model.");

        return $result;
    }

    /**
     * Persist a new set of data
     *
     * @param array $data
     * @return \Illuminate\Database\Eloquent\Model|static
     */
    public function create(array $data)
    {
        return $this->model->create($data);
    }

    /**
     * Update a model where $column === $id
     *
     * @param array $data
     * @param $field
     * @param $id
     * @return bool|int
     */
    public function update(array $data, $field, $id)
    {
        return $this->model->where($field, $id)->update($data);
    }

    /**
     * Paginate results
     *
     * @param int $perPage
     * @param array $columns
     * @return \Illuminate\Pagination\AbstractPaginator
     */
    public function paginate($perPage = 10, array $columns = ['*'])
    {
        return $this->model->paginate($perPage, $columns);
    }

    /**
     * Delete a model where $column === $id
     *
     * @param $field
     * @param $id
     * @return bool|null
     */
    public function delete($field, $id)
    {
        return $this->model->where($field, $id)->delete();
    }

    /**
     * Make a new model and attach it to the repository
     *
     * @param null $override
     *
     * @return \Illuminate\Database\Eloquent\Model|mixed
     */
    public function newModel($override = null)
    {
        $model = $this->model();

        if ($override) $model = $override;

        $class = '\\'.ltrim($model, '\\');

        return $this->app->make($class);
    }

    /**
     * Get the models namespaced class name
     *
     * @return string
     */
    abstract public function model();

    /**
     * @param bool $status
     * @return $this
     */
    public function skipCriteria($status = true)
    {
        $this->skipCriteria = $status;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getCriteria()
    {
        return $this->criteria;
    }

    /**
     * @param Criteria $criteria
     * @return $this
     */
    public function getByCriteria(Criteria $criteria)
    {
        $this->model = $criteria->apply($this->model, $this);

        return $this;
    }

    /**
     * @param Criteria $criteria
     * @return $this
     */
    public function pushCriteria(Criteria $criteria)
    {
        $this->criteria->push($criteria);

        return $this;
    }

    /**
     * @return $this
     */
    public function  applyCriteria()
    {
        if($this->skipCriteria === true)
            return $this;

        foreach($this->getCriteria() as $criteria)
        {
            $this->getByCriteria($criteria);
        }

        return $this;
    }

    /**
     * Retrieve results using a terminator expression.
     * This special type of criteria will return a
     * result set rather than modifying the model/builder.
     *
     * @param Terminator $terminator
     *
     * @return mixed
     */
    public function findByTerminator(Terminator $terminator)
    {
        return $terminator->apply($this->model, $this);
    }
}