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
     * An Api object, which is used to get info on related terms.
     *
     * @var Api
     */
    protected $_api;

    /**
     * Constructor.
     *
     * @param array $values  An array with keys "id" and "name".
     * @param Api $api
     */
    public function __construct(array $values, Api $api)
    {
        $this->_id   = $values['id'];
        $this->_name = $values['name'];
        $this->_api  = $api;
    }

    /**
     * Get a complete term info array for this term.
     *
     * @return Term
     */
    public function getCompleteTerm()
    {
        return $this->_api->getTermByFolksaurusId($this->_id);
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