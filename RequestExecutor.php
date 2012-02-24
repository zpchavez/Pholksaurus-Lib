<?php
/**
 * Library for accessing Folksaurus.
 */
namespace Folksaurus;

require_once 'responseCodeMessages.php';

/**
 * Class for making requests to and receiving responses from Folksaurus.
 */
class RequestExecutor
{
    /**
     * The API key assigned to the app for which requests are made.
     *
     * @var string
     */
    protected $_apiKey;

    /**
     * The base URL to which requests will be made.
     *
     * @var string
     */
    protected $_url;

    /**
     * The latest response code received by this object.
     *
     * @var int
     */
    protected $_latestResponseCode;

    /**
     * Constructor
     *
     * @param string $apiKey
     * @param string $url
     */
    public function __construct($apiKey, $url)
    {
        $this->_apiKey = $apiKey;
        $this->_url = trim($url, '/ ');
    }

    /**
     * Get the latest response code receieved by this object.
     *
     * @return int
     */
    public function getLatestResponseCode()
    {
        return $this->_latestResponseCode;
    }

    /**
     * Get a short description of the latest response code.
     *
     * @return string
     */
    public function getLatestResponseMessage()
    {
        if (isset($responseCodeMessages[$this->_latestResponseCode])) {
            return $responseCodeMessages[$this->_latestResponseCode];
        }
        return false;
    }

    /**
     * Add the specified headers plus the authorization header to the handle.
     *
     * @param resource $handle  A cURL handle.
     * @param array $headers
     * @return resource         The handle with the headers added.
     */
    protected function _addHeaders($handle, $headers = array())
    {
        $headers[] = sprintf(
            'X-Folksaurus-Authorization: %s',
            $this->_apiKey
        );
        curl_setopt($handle, CURLINFO_HEADER_OUT, true);
        curl_setopt(
            $handle,
            CURLOPT_HTTPHEADER,
            $headers
        );
        return $handle;
    }

    /**
     * Add required headers and execute a GET request on $uri.
     *
     * Return the decoded JSON and set $this->_latestResponseCode to
     * the response code.  Return NULL if the body is empty.
     *
     * @param string $uri
     * @return mixed
     */
    protected function _executeGetRequest($uri)
    {
        $handle = curl_init($uri);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        $handle = $this->_addHeaders($handle);
        $response = curl_exec($handle);
        $responseCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $this->_latestResponseCode = $responseCode;
        curl_close($handle);
        return json_decode($response, true);
    }

    /**
     * Get a Term object by its ID.
     *
     * @param int $termId
     * @return Term|bool  False if not found.
     */
    public function getById($termId)
    {
        $uri = sprintf('%s/api/term/id/%s/', $this->_url, $termId);
        $termArray = $this->_executeGetRequest($uri);
        return new Term($termArray, $this);
    }

    /**
     * Get a Term object by its name, or false if not found.
     *
     * @param string $name
     * @return array
     */
    public function getByName($name)
    {
        $uri = sprintf(
            '%s/api/term/%s/',
            $this->_url,
            rawurlencode($name)
        );
        $termArray = $this->_executeGetRequest($uri);
        if (!$termArray) {
            return false;
        }
        return new Term($termArray, $this);
    }

    /**
     * Create a new term.
     *
     * @param string $name
     * @return string       If successfull, the ID of the newly
     *                      created term.  If the term already exists,
     *                      the ID of that term.  Otherwise false.
     */
    public function createByName($name)
    {
        $uri = sprintf(
            '%s/api/term/%s/',
            $this->_url,
            rawurlencode($name)
        );
        $handle = curl_init($uri);
        curl_setopt($handle, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        $handle = $this->_addHeaders($handle, array('Content-Length: 0'));
        curl_setopt($handle, CURLOPT_POSTFIELDS, '');
        $response = curl_exec($handle);
        $this->_latestResponseCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        return $response;
    }

    /**
     * Attempt to get a term by name.  If it does not exist, create it.
     *
     * @param string $name
     * @return array        The Term object for the new or found term.
     *                      False if unable to get or create.
     */
    public function getOrCreate($name)
    {
        $term = $this->getByName($name);
        if ($term) {
            return $term;
        }
        $id = $this->createByName($name);
        if ($id) {
            return $this->getById($id);
        }
        return false;
    }

    /**
     * Get an array of TermSummary objects for terms
     * whose names start with $query.
     *
     * @param string $query
     * @param int $limit
     * @return array
     */
    public function getTermList($query, $limit = 25)
    {
        $uri = sprintf(
            '%s/api/term-list/%s/%s/',
            $this->_url,
            rawurlencode($query),
            $limit
        );
        $termArrays = $this->_executeGetRequest($uri);
        $termList = array();
        foreach ($termArrays as $termArray) {
            $termList[] = new TermSummary($termArray, $this);
        }
        return $termList;
    }
}