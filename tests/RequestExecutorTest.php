<?php
namespace Folksaurus;

require_once 'PHPUnit/Framework/TestCase.php';

/**
 * Test class for RequestExecutor.
 */
class RequestExecutorTest extends \PHPUnit_Framework_TestCase
{
    const API_URL = 'http://www.folksaurus.com';
    const API_KEY = 'foobarbaz';

    public function testGetById()
    {
        $mockCurl = $this->getMock('Curl');
        // Init called on object with url to term resource.
        $mockCurl->expects($this->at(0))
            ->method('init')
            ->with(
                $this->equalTo(
                    sprintf(
                        RequestExecutor::RES_TERM_BY_ID,
                        self::API_URL,
                        '1'
                    )
                )
            );

        // Authorization header set.
        $headers = array(
            sprintf(RequestExecutor::AUTHORIZATION_HEADER, self::API_KEY)
        );
        $mockCurl->expects($this->at(1))
            ->method('__set')
            ->with(
                $this->equalTo('httpheader'),
                $this->equalTo($headers)
            );

        // JSON fetched.
        $mockCurl->expects($this->at(2))
            ->method('fetch_json')
            ->with($this->equalTo(true))
            ->will(
                $this->returnValue(
                    array(
                        'id'         => '1',
                        'name'       => 'Foo',
                        'scope_note' => 'A term.',
                        'broader'    => array(),
                        'narrower'   => array(),
                        'related'    => array(),
                        'used_for'   => array(),
                        'use'        => array()
                    )
                )
            );

        // Response code retrieved.
        $mockCurl->expects($this->at(3))
            ->method('info')
            ->with($this->equalTo('HTTP_CODE'))
            ->will($this->returnValue(200));

        $rex = new RequestExecutor(self::API_KEY, self::API_URL, $mockCurl);
        $term = $rex->getById('1');
        $this->assertTrue($term instanceof Term);
        $this->assertEquals(1, $term->getId());
        $this->assertEquals('Foo', $term->getName());
    }

    public function testGetByName()
    {
        $mockCurl = $this->getMock('Curl');
        // Init called on object with url to term resource.
        $mockCurl->expects($this->at(0))
            ->method('init')
            ->with(
                $this->equalTo(
                    sprintf(
                        RequestExecutor::RES_TERM_BY_NAME,
                        self::API_URL,
                        'Foo'
                    )
                )
            );

        // Authorization header set.
        $headers = array(
            sprintf(RequestExecutor::AUTHORIZATION_HEADER, self::API_KEY)
        );
        $mockCurl->expects($this->at(1))
            ->method('__set')
            ->with(
                $this->equalTo('httpheader'),
                $this->equalTo($headers)
            );

        // JSON fetched.
        $mockCurl->expects($this->at(2))
            ->method('fetch_json')
            ->with($this->equalTo(true))
            ->will(
                $this->returnValue(
                    array(
                        'id'         => '1',
                        'name'       => 'Foo',
                        'scope_note' => 'A term.',
                        'broader'    => array(),
                        'narrower'   => array(),
                        'related'    => array(),
                        'used_for'   => array(),
                        'use'        => array()
                    )
                )
            );

        // Response code retrieved.
        $mockCurl->expects($this->at(3))
            ->method('info')
            ->with($this->equalTo('HTTP_CODE'))
            ->will($this->returnValue(200));

        $rex = new RequestExecutor(self::API_KEY, self::API_URL, $mockCurl);
        $term = $rex->getByName('Foo');
        $this->assertTrue($term instanceof Term);
        $this->assertEquals(1, $term->getId());
        $this->assertEquals('Foo', $term->getName());
    }

    public function testCreateByName()
    {
        $mockCurl = $this->getMock('Curl');
        // Init called on object with url to term resource.
        $mockCurl->expects($this->at(0))
            ->method('init')
            ->with(
                $this->equalTo(
                    sprintf(
                        RequestExecutor::RES_TERM_BY_NAME,
                        self::API_URL,
                        'Foo'
                    )
                )
            );

        // Method set to PUT.
        $mockCurl->expects($this->at(1))
            ->method('__set')
            ->with(
                $this->equalTo('customrequest'),
                $this->equalTo('PUT')
            );

        // Content-Length and authorization headers set.
        $headers = array(
            'Content-Length: 0',
            sprintf(RequestExecutor::AUTHORIZATION_HEADER, self::API_KEY)
        );
        $mockCurl->expects($this->at(2))
            ->method('__set')
            ->with(
                $this->equalTo('httpheader'),
                $this->equalTo($headers)
            );

        // Post fields are blank, since all necessary info is in the URL.
        $mockCurl->expects($this->at(3))
            ->method('__set')
            ->with(
                $this->equalTo('postfields'),
                $this->equalTo('')
            );

        // Results fetched and ID returned.
        $mockCurl->expects($this->at(4))
            ->method('fetch')
            ->will($this->returnValue(1));

        // Response code retrieved.
        $mockCurl->expects($this->at(5))
            ->method('info')
            ->with($this->equalTo('HTTP_CODE'))
            ->will($this->returnValue(201));

        $rex = new RequestExecutor(self::API_KEY, self::API_URL, $mockCurl);
        $id = $rex->createByName('Foo');
        $this->assertEquals(1, $id);
        $this->assertEquals(201, $rex->getLatestResponseCode());
    }

    public function testGetOrCreate()
    {
        // Acts just like getByName if the name returns a result.
        $mockCurl = $this->getMock('Curl');
        // Init called on object with url to term resource.
        $mockCurl->expects($this->at(0))
            ->method('init')
            ->with(
                $this->equalTo(
                    sprintf(
                        RequestExecutor::RES_TERM_BY_NAME,
                        self::API_URL,
                        'Foo'
                    )
                )
            );

        // Authorization header set.
        $headers = array(
            sprintf(RequestExecutor::AUTHORIZATION_HEADER, self::API_KEY)
        );
        $mockCurl->expects($this->at(1))
            ->method('__set')
            ->with(
                $this->equalTo('httpheader'),
                $this->equalTo($headers)
            );

        // JSON fetched.
        $mockCurl->expects($this->at(2))
            ->method('fetch_json')
            ->with($this->equalTo(true))
            ->will(
                $this->returnValue(
                    array(
                        'id'         => '1',
                        'name'       => 'Foo',
                        'scope_note' => 'A term.',
                        'broader'    => array(),
                        'narrower'   => array(),
                        'related'    => array(),
                        'used_for'   => array(),
                        'use'        => array()
                    )
                )
            );

        // Response code retrieved.
        $mockCurl->expects($this->at(3))
            ->method('info')
            ->with($this->equalTo('HTTP_CODE'))
            ->will($this->returnValue(200));

        $rex = new RequestExecutor(self::API_KEY, self::API_URL, $mockCurl);
        $term = $rex->getOrCreate('Foo');
        $this->assertTrue($term instanceof Term);
        $this->assertEquals(1, $term->getId());
        $this->assertEquals('Foo', $term->getName());

        // But if no term is found, it creates it.
        $mockCurl->expects($this->at(2))
            ->method('fetch_json')
            ->with($this->equalTo(true))
            ->will($this->returnValue(NULL));

        // Response code retrieved.
        $mockCurl->expects($this->at(3))
            ->method('info')
            ->with($this->equalTo('HTTP_CODE'))
            ->will($this->returnValue(404));

                // Response code retrieved.
        $mockCurl->expects($this->at(4))
            ->method('close');


        // Init called on object with url to term resource.
        $mockCurl->expects($this->at(5))
            ->method('init')
            ->with(
                $this->equalTo(
                    sprintf(
                        RequestExecutor::RES_TERM_BY_NAME,
                        self::API_URL,
                        'Foo'
                    )
                )
            );

        // Method set to PUT.
        $mockCurl->expects($this->at(6))
            ->method('__set')
            ->with(
                $this->equalTo('customrequest'),
                $this->equalTo('PUT')
            );

        // Content-Length and authorization headers set.
        $headers = array(
            'Content-Length: 0',
            sprintf(RequestExecutor::AUTHORIZATION_HEADER, self::API_KEY)
        );
        $mockCurl->expects($this->at(7))
            ->method('__set')
            ->with(
                $this->equalTo('httpheader'),
                $this->equalTo($headers)
            );

        // Post fields are blank, since all necessary info is in the URL.
        $mockCurl->expects($this->at(8))
            ->method('__set')
            ->with(
                $this->equalTo('postfields'),
                $this->equalTo('')
            );

        // Results fetched and ID returned.
        $mockCurl->expects($this->at(9))
            ->method('fetch')
            ->will($this->returnValue(1));

        // Response code retrieved.
        $mockCurl->expects($this->at(10))
            ->method('info')
            ->with($this->equalTo('HTTP_CODE'))
            ->will($this->returnValue(201));

        // After the term is created it is fetched.
        $mockCurl->expects($this->at(11))
            ->method('init')
            ->with(
                $this->equalTo(
                    sprintf(
                        RequestExecutor::RES_TERM_BY_ID,
                        self::API_URL,
                        '1'
                    )
                )
            );

        // Authorization header set.
        $headers = array(
            sprintf(RequestExecutor::AUTHORIZATION_HEADER, self::API_KEY)
        );
        $mockCurl->expects($this->at(12))
            ->method('__set')
            ->with(
                $this->equalTo('httpheader'),
                $this->equalTo($headers)
            );

        // JSON fetched.
        $mockCurl->expects($this->at(13))
            ->method('fetch_json')
            ->with($this->equalTo(true))
            ->will(
                $this->returnValue(
                    array(
                        'id'         => '1',
                        'name'       => 'Foo',
                        'scope_note' => 'A term.',
                        'broader'    => array(),
                        'narrower'   => array(),
                        'related'    => array(),
                        'used_for'   => array(),
                        'use'        => array()
                    )
                )
            );

        // Response code retrieved.
        $mockCurl->expects($this->at(14))
            ->method('info')
            ->with($this->equalTo('HTTP_CODE'))
            ->will($this->returnValue(200));

        $term = $rex->getOrCreate('Foo');
        $this->assertTrue($term instanceof Term);
        $this->assertEquals(1, $term->getId());
        $this->assertEquals('Foo', $term->getName());
    }

    public function testGetByTermList()
    {
        $mockCurl = $this->getMock('Curl');
        // Init called on object with url to term resource.
        $mockCurl->expects($this->at(0))
            ->method('init')
            ->with(
                $this->equalTo(
                    sprintf(
                        RequestExecutor::RES_TERM_LIST,
                        self::API_URL,
                        'Foo',
                        '3'
                    )
                )
            );

        // Authorization header set.
        $headers = array(
            sprintf(RequestExecutor::AUTHORIZATION_HEADER, self::API_KEY)
        );
        $mockCurl->expects($this->at(1))
            ->method('__set')
            ->with(
                $this->equalTo('httpheader'),
                $this->equalTo($headers)
            );

        // JSON fetched.
        $mockCurl->expects($this->at(2))
            ->method('fetch_json')
            ->with($this->equalTo(true))
            ->will(
                $this->returnValue(
                    array(
                        array('id' => '1', 'name' => 'Foo'),
                        array('id' => '2', 'name' => 'Foobar')
                    )
                )
            );

        // Response code retrieved.
        $mockCurl->expects($this->at(3))
            ->method('info')
            ->with($this->equalTo('HTTP_CODE'))
            ->will($this->returnValue(200));

        $rex = new RequestExecutor(self::API_KEY, self::API_URL, $mockCurl);
        $termSummaries = $rex->getTermList('Foo', 3);
        $this->assertEquals(2, count($termSummaries));
        $this->assertEquals('1', $termSummaries[0]->getId());
        $this->assertEquals('Foo', $termSummaries[0]->getName());
        $this->assertEquals('2', $termSummaries[1]->getId());
        $this->assertEquals('Foobar', $termSummaries[1]->getName());
    }
}
