<?php
namespace Grav\Common\Object;

use Grav\Common\Object\Storage\StorageInterface;

abstract class AbstractObjectFinder implements ObjectFinderInterface
{
    /**
     * Storage associated with the model.
     *
     * @var StorageInterface
     */
    protected $storage;

    /**
     * @var int
     */
    protected $start = 0;

    /**
     * @var int
     */
    protected $limit = 0;

    /**
     * @var bool
     */
    protected $skip = false;

    /**
     * List of query rules.
     *
     * @var array
     */
    protected $query = [];

    /**
     * Finder constructor.
     *
     * @param array $options    Query options.
     */
    public function __construct(array $options = [])
    {
        if (!$this->storage) {
            throw new \DomainException('Storage missing from ' . get_class($this));
        }

        if ($options) {
            $this->parse($options);
        }
    }

    /**
     * Parse query options.
     *
     * @param array $options
     * @return $this
     */
    public function parse(array $options)
    {
        foreach ($options as $func => $params) {
            if (method_exists($this, $func)) {
                call_user_func_array([$this, $func], (array) $params);
            }
        }

        return $this;
    }

    /**
     * Set start position for the query.
     *
     * @param int   $start
     *
     * @return $this
     */
    public function start($start = null)
    {
        if (!is_null($start)) {
            if ((string)(int)$start === (string)$start && $start>=0) {
                throw new \RuntimeException('$start needs to be zero or a positive integer');
            }

            $this->start = (int)$start;
        }

        return $this;
    }

    /**
     * Set limit to the query.
     *
     * @param int   $limit
     *
     * @return $this
     */
    public function limit($limit = null)
    {
        if (!is_null($limit))
        {
            if ((string)(int)$limit === (string)$limit && $limit>=0) {
                throw new \RuntimeException('$limit needs to be zero or a positive integer');
            }

            $this->limit = (int)$limit;
        }

        return $this;
    }

    /**
     * Set order by field and direction.
     *
     * This function can be used more than once to chain order by.
     *
     * @param  string $field
     * @param  int    $direction
     *
     * @return $this
     */
    public function order($field, $direction = 1)
    {
        if (!is_string($field)) {
            throw new \RuntimeException('$field needs to be a string');
        }
        if ((string)(int)$direction !== (string)$direction || $direction <= -1 || $direction >= 1) {
            throw new \RuntimeException('$direction needs to be 1 (ascending), 0 (undefined) or -1 (descending)');
        }

        if ($direction) {
            $this->query['order'][] = [$field, (int)$direction];
        }

        return $this;
    }

    /**
     * Filter by field.
     *
     * @param  string        $field       Field name.
     * @param  string        $operation   Operation (>|>=|<|<=|==|!=|BETWEEN|NOT BETWEEN|IN|NOT IN)
     * @param  mixed         $value       Value.
     *
     * @return $this
     */
    public function where($field, $operation, $value = null)
    {
        if (!is_string($field)) {
            throw new \RuntimeException('$field needs to be a string');
        }

        $operation = strtoupper((string)$operation);

        switch ($operation)
        {
            case '>':
            case '>=':
            case '<':
            case '<=':
                $this->checkOperator($value);
                $this->query['where'][] = [$field, $operation, $value];
                break;
            case '==':
            case '!=':
                $this->checkEqOperator($value);
                $this->query['where'][] = [$field, $operation, $value];
                break;
            case 'BETWEEN':
            case 'NOT BETWEEN':
                $this->checkBetweenOperator($value);
                $this->query['where'][] = [$field, $operation, array_values($value)];
                break;
            case 'IN':
                if (is_null($value) || (is_array($value) && empty($value))) {
                    // IN (nothing): optimized to empty collection.
                    $this->skip = true;
                } else {
                    $this->checkInOperator($value);
                    $this->query['where'][] = [$field, $operation, array_values((array)$value)];
                }
                break;
            case 'NOT IN':
                if (is_null($value) || (is_array($value) && empty($value))) {
                    // NOT IN (nothing): optimized away.
                } else {
                    $this->checkInOperator($value);
                    $this->query['where'][] = [$field, $operation, array_values((array)$value)];
                }
                break;
            default:
                throw new \RuntimeException('$operation needs to be one of: > , >= , < , <= , == , != , BETWEEN , NOT BETWEEN , IN , NOT IN');
        }

        return $this;
    }

    /**
     * Get items.
     *
     * Derived classes should generally override this function to return correct objects.
     *
     * @param int $start
     * @param int $limit
     * @return array
     */
    public function find($start = null, $limit = null)
    {
        if ($this->skip)
        {
            return array();
        }

        $query = $this->query;
        $this->start($start)->limit($limit);
        $this->prepare();

        $results = (array) $this->storage->find($this->query, $this->start, $this->limit);

        $this->query = $query;

        return $results;
    }

    /**
     * Count items.
     *
     * @return int
     */
    public function count()
    {
        $query = $this->query;
        $this->prepare();

        $count = (int) $this->storage->count($this->query);

        $this->query = $query;

        return $count;
    }

    /**
     * Override to include common rules.
     *
     * @return void
     */
    protected function prepare()
    {
    }

    protected function checkOperator($value)
    {
        if (!is_scalar($value)) {
            throw new \RuntimeException('$value needs to be a scalar');
        }
    }

    protected function checkEqOperator($value)
    {
        if (!is_null($value) || !is_scalar($value)) {
            throw new \RuntimeException('$value needs to be a scalar or null');
        }
    }

    protected function checkBetweenOperator($value)
    {
        if (!is_array($value) || count($value) !== 2) {
            throw new \RuntimeException('$value needs to be a list with two values in it');
        }
    }

    protected function checkInOperator($value)
    {
        if (!is_scalar($value) || !is_array($value)) {
            throw new \RuntimeException('$value needs to be a single value or list of values');
        }
    }
}
