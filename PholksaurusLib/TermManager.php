<?php
namespace PholksaurusLib;

/**
 * Retrieve terms and keep the database in sync with Folksaurus.
 */
class TermManager
{
    /**
     * The name-value pairs from the configuration file.
     *
     * @var array
     */
    protected $_config;

    /**
     * An implementation of DataInterface.
     *
     * @var DataInterface
     */
    protected $_dataInterface;

    /**
     * @var RequestExecutor
     */
    protected $_rex;

    /**
     * Constructor.
     *
     * The options array accepts the following:
     * - configPath: the path to an alternate config file to use
     * - configSection: the name of a section in the ini file from which to get values
     * - rex: a PholksaurusLib\RequestExecutor object to use in place of the default.
     *
     * @param DataInterface $dataInterface  An implementation of DataInterface.
     * @param array $options                See method description.
     * @throws Exception if config file could not be found or read or is missing
     *                   required values.
     */
    public function __construct(DataInterface $dataInterface, array $options = array())
    {
        $configPath = isset($options['configPath'])
            ? $options['configPath']
            : __DIR__ . '/config.ini';

        $config = parse_ini_file($configPath, true);
        if (!$config) {
            throw new Exception('Failed to read config file: ' . $configPath);
        }

        if (isset($options['configSection'])) {
            if (!isset($config[$options['configSection']])) {
                throw new Exception(
                    sprintf(
                        'Section "%s" not found in ini file.',
                        $options['configSection']
                    )
                );
            }
            $configValues = $config[$options['configSection']];
        } else {
            // Filter out sections.
            $configValues = array_filter(
                $config,
                function($v) {return !is_array($v);}
            );
        }

        $requiredKeys = array(
            'api_key',
            'api_url'
        );
        $missingKeys = array_diff(
            $requiredKeys,
            array_keys($configValues)
        );
        if ($missingKeys) {
            throw new Exception(
                'Values missing for the following keys: ' .
                implode(',', $missingKeys)
            );
        }

        $rex = isset($options['rex']) && ($options['rex'] instanceof RequestExecutor)
            ? $options['rex']
            : new RequestExecutor($configValues['api_key'], $configValues['api_url']);

        $this->_config        = $configValues;
        $this->_dataInterface = $dataInterface;
        $this->_rex           = $rex;
    }

    /**
     * Retrieve a term by the ID assigned to it by your application.
     *
     * Update database with most recent term details if expire_time has
     * passed since the last_retrieved date.
     *
     * @param type $id
     * @return Term|bool  False if not found.
     */
    public function getTermByAppId($id)
    {
        $termArray = $this->_dataInterface->getTermByAppId($id);
        if ($termArray) {
            $term = new Term($termArray, $this);
            return $this->_getLatestTerm($term);
        }
        return false;
    }

    /**
     * Retrieve a term by its Folksaurus-assigned ID.
     *
     * Update database if expire_time has passed since the last_retrieved date.
     *
     * @param int $id
     * @return Term|bool  False if unable to retrieve or if the term was deleted.
     */
    public function getTermByFolksaurusId($id)
    {
        $termArray = $this->_dataInterface->getTermByFolksaurusId($id);
        if ($termArray) {
            $term = new Term($termArray, $this);
            return $this->_getLatestTerm($term);
        } else {
            $termArray = $this->_rex->getTermById($id);
            if ($termArray) {
                $termArray['last_retrieved'] = time();
                $term = new Term($termArray, $this);
                $this->_dataInterface->saveTerm($term);
                return $term;
            }
            return false;
        }
    }

    /**
     * Get a term by its name.
     *
     * @param type $name
     * @return Term|bool  False if not found.
     */
    public function getTermByName($name)
    {
        $termArray = $this->_dataInterface->getTermByName($name);
        if ($termArray) {
            $term = new Term($termArray, $this);
            return $this->_getLatestTerm($term);
        }
        $termArray = $this->_rex->getTermByName($name);
        if ($termArray) {
            $termArray['last_retrieved'] = time();
            $term = new Term($termArray, $this);
            $this->_dataInterface->saveTerm($term);
            return $term;
        }
        return false;
    }

    /**
     * Get a term by its name.  If it doesn't exist, create it.
     *
     * @param string $name
     * @return Term|bool  False if unable to get or create term.
     */
    public function getOrCreateTerm($name)
    {
        $termArray = $this->_dataInterface->getTermByName($name);
        if ($termArray) {
            $term = new Term($termArray, $this);
            return $this->_getLatestTerm($term);
        }
        $termArray = $this->_rex->getOrCreateTerm($name);
        if ($termArray) {
            $termArray['last_retrieved'] = time();
            $term = new Term($termArray, $this);
            $this->_dataInterface->saveTerm($term);
            return $term;
        } else if ($this->_rex->getLatestResponseCode() == StatusCodes::GONE) {
            return false;
        } else {
            // If term not retrieved or created for some other reason, create a
            // placeholder term locally.
            $term = new Term(
                array(
                    'name'           => $name,
                    'last_retrieved' => 0
                ),
                $this
            );
            $this->_dataInterface->saveTerm($term);
            return $term;
        }
    }

    /**
     * Get the RequestExecutor object used by this instance.
     *
     * @return RequestExecutor
     */
    public function getRequestExecutor()
    {
        return $this->_rex;
    }

    /**
     * Get the array of configuration values parsed from the config file.
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->_config;
    }

    /**
     * Get the latest data for $term and return an updated Term object.
     *
     * If term is current, return it back.
     *
     * If term is out-of-date, retrieve the latest version, save to your DB, and return it.
     *
     * If Folksaurus ID is not set, search Folksaurus by term name.
     *
     * If term does not exist in Folksaurus, add it as a new term.  This will reset
     * the term info in your database.
     *
     * Original term returned if the request fails for any reason.
     *
     * @param Term $term
     * @return Term
     */
    protected function _getLatestTerm(Term $term)
    {
        $lastRetrievedTime = $term->getLastRetrievedTime();
        $now = time();
        $secondsSinceUpdate = ($now - $lastRetrievedTime);
        if ($secondsSinceUpdate > $this->_config['expire_time']) {
            // If Folksaurus ID is known, search by that.
            if ($term->getId()) {
                $updatedTermArray = $this->_rex->getTermByIdIfModifiedSince(
                    $term->getId(),
                    $lastRetrievedTime
                );
            }

            // If that didn't work, or if Folksaurus ID not set, get or create by name.
            if (!$term->getId() || !$updatedTermArray) {
                $updatedTermArray = $this->_rex->getOrCreateTerm($term->getName());
            }

            $responseCode = $this->_rex->getLatestResponseCode();
            switch ($responseCode) {
                case StatusCodes::CREATED:
                    // Intentional fall-through.
                case StatusCodes::OK:
                    $updatedTermArray['last_retrieved'] = time();
                    $updatedTermArray['app_id'] = $term->getAppId();
                    $updatedTerm = new Term($updatedTermArray, $this);
                    $this->_dataInterface->saveTerm($updatedTerm);
                    return $updatedTerm;
                    break;
                case StatusCodes::NOT_MODIFIED:
                    $term->updateLastRetrievedTime();
                    $this->_dataInterface->saveTerm($term);
                    break;
                case StatusCodes::GONE:
                    $this->_dataInterface->deleteTerm($term->getAppId());
                    return false;
                    break;
                // Other responses are possible, but require no action.
            }
        }
        return $term;
    }
}
