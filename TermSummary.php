<?php
/**
 * Object representing a Folksaurus term.
 */
namespace Folksaurus;

/**
 * An object representing the ID and name of a term,
 * but not the scope note or relationships.
 */
class TermSummary
{
    /**
     * The term ID.
     *
     * @var int
     */
    protected $_id;

    /**
     * The term name.
     *
     * @var string
     */
    protected $_name;

    /**
     * @var RequestExecutor
     */
    protected $_rex;

    /**
     * Constructor.
     *
     * @param array $values  An array with keys "id" and "name".
     * @param RequestExecutor $rex
     */
    public function __construct(array $values, RequestExecutor $rex)
    {
        $this->_id   = $values['id'];
        $this->_name = $values['name'];
        $this->_rex  = $rex;
    }

    /**
     * Get a Term object for this term.
     *
     * @return Term|bool  False on failure.
     */
    public function getCompleteTerm()
    {
        return $this->_rex->getById($this->_id);
    }

    /**
     * Get the name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * Get the ID.
     *
     * @return int
     */
    public function getId()
    {
        return $this->_id;
    }


    /**
     * Get the name.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->_name;
    }
}