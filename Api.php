<?php
namespace Folksaurus;

/**
 * The class used to interface with Folksaurus and your own database.
 */
class Api
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
     * @param DataInterface $dataInterface  An implementation of DataInterface.
     * @param RequestExecutor $rex       If not provided, an instance will be created
     *                                   using api_key and api_url from the config file.
     * @param string $configFile         The relative path to the config file to use.
     * @throws Exception if config file could not be found or read or is missing
     *                   required values.
     */
    public function __construct(DataInterface $dataInterface, RequestExecutor $rex = null,
                                $configFile = 'config.ini')
    {
        $config = parse_ini_file($configFile);
        if (!$config) {
            throw new Exception('Failed to read config file: ' . $configFile);
        }
        $requiredKeys = array(
            'api_key',
            'api_url'
        );
        $missingKeys = array_diff(
            $requiredKeys,
            array_keys($config)
        );
        if ($missingKeys) {
            throw new Exception(
                'Values missing for the following keys: ' .
                implode(',', $missingKeys)
            );
        }

        if ($rex === null) {
            $rex = new RequestExecutor(
                $config['api_key'],
                $config['api_url']
            );
        }
        $this->_config        = $config;
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
     * @return Term
     */
    public function getTermByFolksaurusId($id)
    {
        $termArray = $this->_dataInterface->getTermByFolksaurusId($id);
        if ($termArray) {
            $term = new Term($termArray, $this);
            return $this->_getLatestTerm($term);
        } else {
            $termArray = $this->_rex->getById($id);
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
     * Get the latest data for $term and return an updated Term object.
     *
     * If term is current, return it back.
     *
     * If term is out-of-date, retrieve the latest version, save to DB, and return it.
     *
     * Original term also returned if the request fails.
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
            $updatedTermArray = $this->_rex->getByIdIfModifiedSince(
                $term->getId(),
                $lastRetrievedTime
            );
            if (!$updatedTermArray) {
                return $term;
            }
            $updatedTermArray['last_retrieved'] = time();
            $updatedTermArray['app_id'] = $term->getAppId();
            $updatedTerm = new Term($updatedTermArray, $this);
            $this->_dataInterface->saveTerm($updatedTerm);
            return $updatedTerm;
        }
        return $term;
    }
}
