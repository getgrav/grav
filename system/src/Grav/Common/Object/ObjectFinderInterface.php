<?php
namespace Grav\Common\Object;

interface ObjectFinderInterface
{
    /**
     * Finder constructor.
     *
     * @param array $options    Query options.
     */
    public function __construct(array $options = []);

    /**
     * Parse query options.
     *
     * @param array $options
     * @return $this
     */
    public function parse(array $options);

    /**
     * Set start position for the query.
     *
     * @param int   $start
     *
     * @return $this
     */
    public function start($start = null);

    /**
     * Set limit to the query.
     *
     * @param int   $limit
     *
     * @return $this
     */
    public function limit($limit = null);

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
    public function order($field, $direction = 1);
    /**
     * Filter by field.
     *
     * @param  string        $field       Field name.
     * @param  string        $operation   Operation (>|>=|<|<=|==|!=|BETWEEN|NOT BETWEEN|IN|NOT IN)
     * @param  mixed         $value       Value.
     *
     * @return $this
     */
    public function where($field, $operation, $value = null);

    /**
     * Get items.
     *
     * Derived classes should generally override this function to return correct objects.
     *
     * @param int $start
     * @param int $limit
     * @return array
     */
    public function find($start = null, $limit = null);

    /**
     * Count items.
     *
     * @return int
     */
    public function count();
}
