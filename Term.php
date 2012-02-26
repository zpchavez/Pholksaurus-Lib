<?php
/**
 * Object representing a Folksaurus term.
 */
namespace Folksaurus;

/**
 * An object representing a term.
 */
class Term
{
    // Values returned by getStatus().
    const STATUS_PREFERRED    = 'preferred';
    const STATUS_NONPREFERRED = 'non-preferred';
    const STATUS_UNSORTED     = 'unsorted';

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
     * @var string
     */
    protected $_scopeNote;

    /**
     * Broader terms for this term.
     *
     * @var array  An array of TermSummary objects.
     */
    protected $_broaderTerms = array();

    /**
     * Narrower terms for this term.
     *
     * @var array  An array of TermSummary objects.
     */
    protected $_narrowerTerms = array();

    /**
     * Related terms for this term.
     *
     * @var array  An array of TermSummary objects.
     */
    protected $_relatedTerms = array();

    /**
     * Non-preferred terms used for this term.
     *
     * @var array  An array of TermSummary objects.
     */
    protected $_usedForTerms = array();

    /**
     * Preferred terms used for this term.
     *
     * @var array  An array of TermSummary objects.
     */
    protected $_useTerms = array();

    /**
     * @var RequestExecutor
     */
    protected $_rex;

    /**
     * @var array $values  The array encoded in the JSON returned by
     *                     a get term request.
     * @param RequestExecutor $rex
     */
    public function __construct(array $values, RequestExecutor $rex)
    {
        $this->_id        = $values['id'];
        $this->_name      = $values['name'];
        $this->_scopeNote = $values['scope_note'];
        $this->_rex       = $rex;

        foreach ($values['broader'] as $broader) {
            $this->_broaderTerms[] = new TermSummary(
                $broader,
                $rex
            );
        }
        foreach ($values['narrower'] as $narrower) {
            $this->_narrowerTerms[] = new TermSummary(
                $narrower,
                $rex
            );
        }
        foreach ($values['related'] as $related) {
            $this->_relatedTerms[] = new TermSummary(
                $related,
                $rex
            );
        }
        foreach ($values['used_for'] as $usedFor) {
            $this->_usedForTerms[] = new TermSummary(
                $usedFor,
                $rex
            );
        }
        foreach ($values['use'] as $use) {
            $this->_useTerms[] = new TermSummary(
                $use,
                $rex
            );
        }
    }

    /**
     * Get the broader terms.
     *
     * @return array  An array of TermSummary objects.
     */
    public function getBroaderTerms()
    {
        return $this->_broaderTerms;
    }

    /**
     * Get the narrower terms.
     *
     * @return array  An array of TermSummary objects.
     */
    public function getNarrowerTerms()
    {
        return $this->_narrowerTerms;
    }

    /**
     * Get the related terms.
     *
     * @return array  An array of TermSummary objects.
     */
    public function getRelatedTerms()
    {
        return $this->_relatedTerms;
    }

    /**
     * Get the used for terms.
     *
     * @return array  An array of TermSummary objects.
     */
    public function getUsedForTerms()
    {
        return $this->_usedForTerms;
    }

    /**
     * Get the use terms.
     *
     * @return array  An array of TermSummary objects.
     */
    public function getUseTerms()
    {
        return $this->_useTerms;
    }

    /**
     * Return status as a string.
     *
     * @return string  One of this class's STATUS constants.
     */
    public function getStatus()
    {
        if ($this->getUseTerms()) {
            return self::STATUS_NONPREFERRED;
        }
        $allTerms = array_merge(
            $this->getBroaderTerms(),
            $this->getNarrowerTerms(),
            $this->getRelatedTerms(),
            $this->getUsedForTerms()
        );
        return $allTerms ? self::STATUS_PREFERRED : self::STATUS_UNSORTED;
    }

    /**
     * Return true if the term is a non-preferred term which may
     * describe any of multiple concepts.
     *
     * @return bool
     */
    public function isAmbiguous()
    {
        return count($this->getUseTerms()) > 1;
    }

    /**
     * Get the preferred term or terms for this term.
     *
     * If this is the preferred term, or if it is unsorted, return it.
     *
     * If this term is ambiguous, return an array of TermSummary objects.
     *
     * @return Term|array
     */
    public function getPreferred()
    {
        if ($this->getStatus() != self::STATUS_NONPREFERRED) {
            return $this;
        }
        $useTerms = $this->getUseTerms();
        if (count($useTerms) == 1) {
            return $this->_rex->getById($useTerms[0]->getId());
        }
        $preferredTerms = array();
        foreach ($useTerms as $useTerm) {
            $preferredTerms[] = $this->_rex->getById(
                $useTerm->getId()
            );
        }
        return $preferredTerms;
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