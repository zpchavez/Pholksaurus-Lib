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
    protected $_dataMapper;

    /**
     * @var RequestExecutor
     */
    protected $_rex;

    /**
     * Constructor.
     *
     * @param DataInterface $dataMapper  An implementation of DataMapper.
     * @param RequestExecutor $rex       If not provided, an instance will be created
     *                                   using api_key and api_url from the config file.
     * @param string $configFile         The relative path to the config file to use.
     * @throws Exception if config file could not be found or read or is missing
     *                   required values.
     */
    public function __construct(DataMapper $dataMapper, RequestExecutor $rex = null,
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
        $this->_dataMapper = $dataMapper;
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
        $termArray = $this->_dataMapper->getTermByAppId($id);
        if ($termArray) {
            $term = new Term($termArray, $this);
            $lastRetrievedTime = $term->getLastRetrievedTime();
            $now = time();
            $secondsSinceUpdate = ($now - $lastRetrievedTime);
            if ($secondsSinceUpdate > $this->_config['expire_time']) {
                $termArray = $this->_rex->getById($term->getId());
                if (!$termArray) {
                    return $term;
                }
                $termArray['last_retrieved'] = time();
                $termArray['app_id'] = $id;
                $updatedTerm = new Term($termArray, $this);
                $this->_dataMapper->saveTerm($updatedTerm);
                return $updatedTerm;
            }
            return $term;
        } else {
            return false;
        }
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
        $this->_dataMapper->getTermByFolksaurusId($id);
    }
}
