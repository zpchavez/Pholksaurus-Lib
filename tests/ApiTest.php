<?php
namespace Folksaurus;

/**
 * Test class for Api.
 */
class ApiTest extends \PHPUnit_Framework_TestCase
{
    const FOO_FOLKSAURUS_ID = 1;
    const FOO_APP_ID        = 100;

    /**
     * Get a simple term array for an unsorted term called Foo.
     *
     * @return array
     */
    protected function _getFooTermArray()
    {
        return array(
            'id'             => self::FOO_FOLKSAURUS_ID,
            'app_id'         => self::FOO_APP_ID,
            'name'           => 'Foo',
            'scope_note'     => 'A term.',
            'broader'        => array(),
            'narrower'       => array(),
            'related'        => array(),
            'used_for'       => array(),
            'use'            => array(),
            'last_retrieved' => 0
        );
    }

    public function testGetTermByAppIdForTermNotFoundInDatabase()
    {
        $mockDI  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMock('Folksaurus\RequestExecutor', array(), array(), '', false);

        $mockDI->expects($this->once())
            ->method('getTermByAppId')
            ->with($this->equalTo(404))
            ->will($this->returnValue(false));

        // Won't send a request to Folksaurus, because the ID is unknown.
        $mockRex->expects($this->never())
            ->method('getById');
        $mockRex->expects($this->never())
            ->method('getByIdIfModifiedSince');

        $api = new Api($mockDI, $mockRex, CONFIG_PATH);
        $term = $api->getTermByAppId(404);

        $this->assertFalse($term);
    }

    public function testGetTermByAppIdForNonExpiredTermInDatabase()
    {
        $mockDI  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMock('Folksaurus\RequestExecutor', array(), array(), '', false);

        $justNow = time();
        $fooTermArray = $this->_getFooTermArray();
        $fooTermArray['last_retrieved'] = $justNow;

        $mockDI->expects($this->once())
            ->method('getTermByAppId')
            ->with($this->equalTo(self::FOO_APP_ID))
            ->will($this->returnValue($fooTermArray));

        // Won't send a request to Folksaurus, because term was retrieved recently.
        $mockRex->expects($this->never())
            ->method('getById');
        $mockRex->expects($this->never())
            ->method('getByIdIfModifiedSince');

        // Won't save, because no changes were retrieved.
        $mockDI->expects($this->never())
            ->method('saveTerm');

        $api = new Api($mockDI, $mockRex, CONFIG_PATH);
        $term = $api->getTermByAppId(self::FOO_APP_ID);

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
    }

    public function testGetTermByAppIdForExpiredTermInDatabaseThatHasBeenModified()
    {
        $mockDI  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMock('Folksaurus\RequestExecutor', array(), array(), '', false);

        $yesterday = time() - (60 * 60 * 24);
        $fooTermArray = $this->_getFooTermArray();
        $fooTermArray['last_retrieved'] = $yesterday;

        $mockDI->expects($this->once())
            ->method('getTermByAppId')
            ->with($this->equalTo(self::FOO_APP_ID))
            ->will($this->returnValue($fooTermArray));

        $updatedFooTermArray = $fooTermArray;
        $updatedFooTermArray['scope_note'] = 'Updated scope note';

        // Term is expired, so send request for latest info.
        $mockRex->expects($this->once())
            ->method('getByIdIfModifiedSince')
            ->with(
                $this->equalTo(self::FOO_FOLKSAURUS_ID),
                $this->equalTo($yesterday)
            )->will($this->returnValue($updatedFooTermArray));

        // Save latest term info.
        $mockDI->expects($this->once())
            ->method('saveTerm')
            ->with($this->isInstanceOf('Folksaurus\Term'));

        $api = new Api($mockDI, $mockRex, CONFIG_PATH);

        $term = $api->getTermByAppId(100);

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
        $this->assertEquals('Updated scope note', $term->getScopeNote());
        $this->assertTrue($term->getLastRetrievedTime() > 0);
    }

    public function testGetTermByAppIdForExpiredTermInDatabaseThatHasNotBeenModified()
    {
        $mockDI  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMock('Folksaurus\RequestExecutor', array(), array(), '', false);

        $yesterday = time() - (60 * 60 * 24);
        $fooTermArray = $this->_getFooTermArray();
        $fooTermArray['last_retrieved'] = $yesterday;

        $mockDI->expects($this->once())
            ->method('getTermByAppId')
            ->with($this->equalTo(self::FOO_APP_ID))
            ->will($this->returnValue($fooTermArray));

        // Term is expired, so send request for latest info.
        // False returned since there was no content in the body of the response.
        // But with response code 304, meaning not modified since.
        $mockRex->expects($this->once())
            ->method('getByIdIfModifiedSince')
            ->with(
                $this->equalTo(self::FOO_FOLKSAURUS_ID),
                $this->equalTo($yesterday)
            )->will($this->returnValue(false));

        // No term saved since there were no changes.
        $mockDI->expects($this->never())
            ->method('saveTerm');

        $api = new Api($mockDI, $mockRex, CONFIG_PATH);

        $term = $api->getTermByAppId(100);

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
        $this->assertEquals('A term.', $term->getScopeNote());
        $this->assertTrue($term->getLastRetrievedTime() > 0);
    }

    public function testGetTermByFolksaurusIdForTermNotInDatabase()
    {
        $mockDI  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMock('Folksaurus\RequestExecutor', array(), array(), '', false);

        $mockDI->expects($this->once())
            ->method('getTermByFolksaurusId')
            ->with($this->equalTo(self::FOO_FOLKSAURUS_ID))
            ->will($this->returnValue(false));

        // Term not found, so send a request to Folksaurus for it.
        $mockRex->expects($this->once())
            ->method('getById')
            ->with($this->equalTo(self::FOO_FOLKSAURUS_ID))
            ->will($this->returnValue($this->_getFooTermArray()));

        // Save the term to the database.
        $mockDI->expects($this->once())
            ->method('saveTerm')
            ->with($this->isInstanceOf('Folksaurus\Term'));

        $api = new Api($mockDI, $mockRex, CONFIG_PATH);
        $term = $api->getTermByFolksaurusId(self::FOO_FOLKSAURUS_ID);

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
        $this->assertEquals('A term.', $term->getScopeNote());
        $this->assertTrue($term->getLastRetrievedTime() > 0);
    }

    public function testGetTermByFolksaurusIdForNonExpiredTermInDatabase()
    {
        $mockDI  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMock('Folksaurus\RequestExecutor', array(), array(), '', false);

        $fooTermArray = $this->_getFooTermArray();
        $fooTermArray['last_retrieved'] = time();

        $mockDI->expects($this->once())
            ->method('getTermByFolksaurusId')
            ->with($this->equalTo(self::FOO_FOLKSAURUS_ID))
            ->will($this->returnValue($fooTermArray));

        // Won't send a request to Folksaurus, because term was retrieved recently.
        $mockRex->expects($this->never())
            ->method('getById');

        // Won't save, because no changes were retrieved.
        $mockDI->expects($this->never())
            ->method('saveTerm');

        $api = new Api($mockDI, $mockRex, CONFIG_PATH);
        $term = $api->getTermByFolksaurusId(self::FOO_FOLKSAURUS_ID);

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
    }

    public function testGetTermByFolksaurusIdForExpiredTermInDatabaseThatHasBeenModified()
    {
        $mockDI  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMock('Folksaurus\RequestExecutor', array(), array(), '', false);

        $yesterday = time() - (60 * 60 * 24);
        $fooTermArray = $this->_getFooTermArray();
        $fooTermArray['last_retrieved'] = $yesterday;

        $mockDI->expects($this->once())
            ->method('getTermByFolksaurusId')
            ->with($this->equalTo(self::FOO_FOLKSAURUS_ID))
            ->will($this->returnValue($fooTermArray));

        $updatedFooTermArray = $fooTermArray;
        $updatedFooTermArray['scope_note'] = 'Updated scope note';

        // Term is expired, so send request for latest info.
        $mockRex->expects($this->once())
            ->method('getByIdIfModifiedSince')
            ->with($this->equalTo(self::FOO_FOLKSAURUS_ID))
            ->will($this->returnValue($updatedFooTermArray));

        // Save latest term info.
        $mockDI->expects($this->once())
            ->method('saveTerm')
            ->with($this->isInstanceOf('Folksaurus\Term'));

        $api = new Api($mockDI, $mockRex, CONFIG_PATH);
        $term = $api->getTermByFolksaurusId(self::FOO_FOLKSAURUS_ID);

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
        $this->assertEquals('Updated scope note', $term->getScopeNote());
        $this->assertTrue($term->getLastRetrievedTime() > 0);
    }

    public function testGetTermByFolksaurusIdForExpiredTermInDatabaseThatHasNotBeenModified()
    {
        $mockDI  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMock('Folksaurus\RequestExecutor', array(), array(), '', false);

        $yesterday = time() - (60 * 60 * 24);
        $fooTermArray = $this->_getFooTermArray();
        $fooTermArray['last_retrieved'] = $yesterday;

        $mockDI->expects($this->once())
            ->method('getTermByFolksaurusId')
            ->with($this->equalTo(self::FOO_FOLKSAURUS_ID))
            ->will($this->returnValue($fooTermArray));

        $updatedFooTermArray = $fooTermArray;
        $updatedFooTermArray['scope_note'] = 'Updated scope note';

        // Term is expired, so send request for latest info.
        $mockRex->expects($this->once())
            ->method('getByIdIfModifiedSince')
            ->with($this->equalTo(self::FOO_FOLKSAURUS_ID))
            ->will($this->returnValue(false));

        // No term saved since there were no changes.
        $mockDI->expects($this->never())
            ->method('saveTerm');

        $api = new Api($mockDI, $mockRex, CONFIG_PATH);
        $term = $api->getTermByFolksaurusId(self::FOO_FOLKSAURUS_ID);

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
        $this->assertEquals('A term.', $term->getScopeNote());
        $this->assertTrue($term->getLastRetrievedTime() > 0);
    }
}
