<?php
namespace Folksaurus;

require_once 'responseCodeMessages.php';

/**
 * Class for making requests to and receiving responses from Folksaurus.
 */
class RequestExecutor
{
    // Resource URL patterns.
    // In each pattern, the first %s will be replaced with the value of $this->_url.
    // For term list, the 2nd %s is a search string and the %d is a limit number.
    const RES_TERM_BY_ID   = '%s/api/term/id/%s/';
    const RES_TERM_BY_NAME = '%s/api/term/%s/';
    const RES_TERM_LIST    = '%s/api/term-list/%s/%d/';

    const AUTHORIZATION_HEADER = 'X-Folksaurus-Authorization: %s'; // %s is the API key

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
     * A Curl object.
     *
     * @var type Curl
     */
    protected $_curlObj;

    /**
     * Constructor
     *
     * @param string $apiKey
     * @param string $url
     * @param Curl   $curlObj For passing in a mock Curl object.
     */
    public function __construct($apiKey, $url, \Curl $curlObj = null)
    {
        $this->_apiKey = $apiKey;
        $this->_url = trim($url, '/ ');
        if ($curlObj === null) {
            $this->_curlObj = new \Curl();
        } else {
            $this->_curlObj = $curlObj;
        }
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
     * Set the specified headers plus the authorization header to the handle.
     *
     * @param Curl $handle   A Curl object.
     * @param array $headers
     */
    protected function _addHeaders($headers = array())
    {
        $headers[] = sprintf(
            self::AUTHORIZATION_HEADER,
            $this->_apiKey
        );
        $this->_curlObj->httpheader = $headers;
    }

    /**
     * Add required headers and execute a GET request on $uri.
     *
     * Return the decoded JSON and set $this->_latestResponseCode to
     * the response code.  Return NULL if the body is empty.
     *
     * @param string $uri
     * @param int $ifModifiedSince  Optional if-modified-since timestamp.
     * @return array      An array of decoded JSON data, or NULL if no response.
     */
    protected function _executeGetRequest($uri, $ifModifiedSince = null)
    {
        $this->_curlObj->url = $uri;
        $this->_curlObj->customrequest = 'GET';
        if ($ifModifiedSince) {
            $additionalHeaders = array(
                'If-Modified-Since: ' . gmdate('D, d M Y H:i:s \G\M\T', $ifModifiedSince)
            );
        } else {
            $additionalHeaders = array();
        }
        $this->_addHeaders($additionalHeaders);
        $response = $this->_curlObj->fetch_json(true);
        $responseCode = $this->_curlObj->info('HTTP_CODE');
        $this->_latestResponseCode = $responseCode;
        return $response;
    }

    /**
     * Get a term info array by its Folksaurus ID.
     *
     * @param type $termId
     * @param type $ifModifiedSince
     * @return array|bool            False if term not found, or if $ifModifiedSince
     *                               is specified and the term has not changed.
     */
    protected function _getTermById($termId, $ifModifiedSince = null)
    {
        $uri = sprintf(self::RES_TERM_BY_ID, $this->_url, $termId);
        $response = $this->_executeGetRequest($uri, $ifModifiedSince);
        return $response ?: false;
    }

    /**
     * Get a term info array by its Folksaurus ID.
     *
     * @param int $termId
     * @return array|bool  False if not found or not modified since.
     */
    public function getTermById($termId)
    {
        return $this->_getTermById($termId);
    }

    /**
     * Get a term info array only if the term has been modified since $ifModifiedSince.
     *
     * @param int $termId
     * @param int $ifModifiedSince  A timestamp.
     * @return array|bool           The term if found and modified since.
     *                              False if not found or not modified since.
     *                              To determine which, call getLastResponseCode.
     *                              304 means the term was found but unchanged.
     */
    public function getTermByIdIfModifiedSince($termId, $ifModifiedSince)
    {
        return $this->_getTermById($termId, $ifModifiedSince);
    }

    /**
     * Get a term info array by the term name.
     *
     * @param string $name
     * @param int $ifModifiedSince   A timestamp.
     * @return array|bool            False if term not found, or if $ifModifiedSince
     *                               is specified and the term has not changed since.
     */
    public function _getTermByName($name, $ifModifiedSince = null)
    {
        $uri = sprintf(
            self::RES_TERM_BY_NAME,
            $this->_url,
            rawurlencode($name)
        );
        $response = $this->_executeGetRequest($uri, $ifModifiedSince);
        return $response ?: false;
    }

    /**
     * Get a term info array by the term name, or false if not found.
     *
     * @param string $name
     * @return array
     */
    public function getTermByName($name)
    {
        return $this->_getTermByName($name);
    }

    /**
     * Get a term info array only if the term has been modified since $ifModifiedSince.
     *
     * @param string $name
     * @param int $ifModifiedSince  A timestamp.
     * @return array|bool           The term if found and modified since.
     *                              False if not found or not modified since.
     *                              To determine which, call getLastResponseCode.
     *                              304 means the term was found but unchanged.
     */
    public function getTermByNameIfModifiedSince($name, $ifModifiedSince)
    {
        return $this->_getTermByName($name, $ifModifiedSince);
    }

    /**
     * Create a new term.
     *
     * @param string $name
     * @return string       If successfull, the ID of the newly
     *                      created term.  If the term already exists,
     *                      the ID of that term.  Otherwise false.
     */
    public function createTerm($name)
    {
        $uri = sprintf(
            self::RES_TERM_BY_NAME,
            $this->_url,
            rawurlencode($name)
        );
        $this->_curlObj->url = $uri;
        $this->_curlObj->customrequest = 'PUT';
        $this->_addHeaders(array('Content-Length: 0'));
        $this->_curlObj->postfields = '';
        $id = $this->_curlObj->exec();
        $this->_latestResponseCode = $this->_curlObj->info('HTTP_CODE');
        return $id;
    }

    /**
     * Attempt to get a term by name.  If it does not exist, create it.
     *
     * @param string $name
     * @return array        If found, a term info array.  Otherwise, false.
     */
    public function getOrCreateTerm($name)
    {
        $termArray = $this->getTermByName($name);
        if ($termArray) {
            return $termArray;
        }
        $id = $this->createTerm($name);
        if ($id) {
            return $this->getTermById($id);
        }
        return false;
    }

    /**
     * Get an array of arrays with keys 'id' and 'name' for terms
     * whose names start with $query.
     *
     * @param string $query
     * @param int $limit
     * @return array
     */
    public function getTermList($query, $limit = 25)
    {
        $uri = sprintf(
            self::RES_TERM_LIST,
            $this->_url,
            rawurlencode($query),
            $limit
        );
        $termArrays = $this->_executeGetRequest($uri);
        $termList = array();
        foreach ($termArrays as $termArray) {
            $termList[] = $termArray;
        }
        return $termList;
    }
}