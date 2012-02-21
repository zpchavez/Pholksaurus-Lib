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
     * @var RequestMaker
     */
    protected $_requestMaker;

    /**
     * Constructor.
     *     
     * @param array $values  An array with keys "id" and "name".
     * @param RequestMaker $requestMaker
     */
    public function __construct(array $values, RequestMaker $requestMaker)
    {
        $this->_id   = $values['id'];
        $this->_name = $values['name'];
        $this->_requestMaker = $requestMaker;
    }

    /**
     * Get a Term object for this term.
     *
     * @return Term|bool  False on failure.
     */
    public function getCompleteTerm()
    {
        return $this->_requestMaker->getById($this->_id);
    }

    public function __toString()
    {
        return $this->_name;
    }
}