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

    public function testGetTermByAppIdForTermNotFoundInDbMakesNoRequestAndReturnsFalse()
    {
        $mockDI  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMock('Folksaurus\RequestExecutor', array(), array(), '', false);

        $mockDI->expects($this->once())
            ->method('getTermByAppId')
            ->with($this->equalTo(StatusCodes::NOT_FOUND))
            ->will($this->returnValue(false));

        // Won't send a request to Folksaurus, because the ID is unknown.
        $mockRex->expects($this->never())
            ->method('getTermById');
        $mockRex->expects($this->never())
            ->method('getTermByIdIfModifiedSince');

        $api = new Api($mockDI, CONFIG_PATH, $mockRex);
        $term = $api->getTermByAppId(StatusCodes::NOT_FOUND);

        $this->assertFalse($term);
    }

    public function testGetTermByAppIdForNonExpiredTermDoesNotMakeARequestOrUpdateTerm()
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
            ->method('getTermById');
        $mockRex->expects($this->never())
            ->method('getTermByIdIfModifiedSince');

        // Won't save, because no changes were retrieved.
        $mockDI->expects($this->never())
            ->method('saveTerm');

        $api = new Api($mockDI, CONFIG_PATH, $mockRex);
        $term = $api->getTermByAppId(self::FOO_APP_ID);

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
    }

    public function testGetTermByAppIdForModifiedExpiredTermMakesRequestAndUpdatesTerm()
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
            ->method('getTermByIdIfModifiedSince')
            ->with(
                $this->equalTo(self::FOO_FOLKSAURUS_ID),
                $this->equalTo($yesterday)
            )->will($this->returnValue($updatedFooTermArray));

        // Save latest term info.
        $mockDI->expects($this->once())
            ->method('saveTerm')
            ->with($this->isInstanceOf('Folksaurus\Term'));

        $api = new Api($mockDI, CONFIG_PATH, $mockRex);

        $term = $api->getTermByAppId(self::FOO_APP_ID);

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
        $this->assertEquals('Updated scope note', $term->getScopeNote());
        $this->assertTrue($term->getLastRetrievedTime() > $yesterday);
    }

    public function testGettingTermThatWasDeletedRemotelyCallsDeleteTermAndReturnsFalse()
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
            ->method('getTermByIdIfModifiedSince')
            ->with(
                $this->equalTo(self::FOO_FOLKSAURUS_ID),
                $this->equalTo($yesterday)
            )->will($this->returnValue(false));

        $mockRex->expects($this->once())
            ->method('getLatestResponseCode')
            ->will($this->returnValue(StatusCodes::GONE));

        $mockDI->expects($this->once())
            ->method('deleteTerm')
            ->with($this->equalTo($fooTermArray['app_id']));

        $mockDI->expects($this->never())
            ->method('saveTerm');

        $api = new Api($mockDI, CONFIG_PATH, $mockRex);

        $term = $api->getTermByAppId(self::FOO_APP_ID);

        $this->assertFalse($term);
    }

    public function testGetTermByAppIdForUnmodifiedExpiredTermMakesRequestAndUpdatesModTime()
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
        $mockRex->expects($this->once())
            ->method('getTermByIdIfModifiedSince')
            ->with(
                $this->equalTo(self::FOO_FOLKSAURUS_ID),
                $this->equalTo($yesterday)
            )->will($this->returnValue(false));

        // Response code is 304.
        $mockRex->expects($this->once())
            ->method('getLatestResponseCode')
            ->will($this->returnValue(StatusCodes::NOT_MODIFIED));

        // Term saved in order to update the last modified time.
        $mockDI->expects($this->once())
            ->method('saveTerm');

        $api = new Api($mockDI, CONFIG_PATH, $mockRex);

        $term = $api->getTermByAppId(self::FOO_APP_ID);

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
        $this->assertEquals('A term.', $term->getScopeNote());
        $this->assertTrue($term->getLastRetrievedTime() > $yesterday);
    }

    public function testGetTermByAppIdForExpiredTermWithNoFolksaurusIdGetsByNameAndSavesTerm()
    {
        $mockDI  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMock('Folksaurus\RequestExecutor', array(), array(), '', false);

        $yesterday = time() - (60 * 60 * 24);
        $fooTermArray = $this->_getFooTermArray();
        $fooTermArray['last_retrieved'] = $yesterday;
        $fooTermArray['id'] = '0';

        $mockDI->expects($this->once())
            ->method('getTermByAppId')
            ->with($this->equalTo(self::FOO_APP_ID))
            ->will($this->returnValue($fooTermArray));

        $updatedFooTermArray               = $fooTermArray;
        $updatedFooTermArray['scope_note'] = 'Updated scope note';
        $updatedFooTermArray['id']         = self::FOO_FOLKSAURUS_ID;

        // Term is expired, so send request for latest info.
        $mockRex->expects($this->once())
            ->method('getOrCreateTerm')
            ->with($this->equalTo('Foo'))
            ->will($this->returnValue($updatedFooTermArray));

        // Save latest term info.
        $mockDI->expects($this->once())
            ->method('saveTerm')
            ->with($this->isInstanceOf('Folksaurus\Term'));

        $api = new Api($mockDI, CONFIG_PATH, $mockRex);

        $term = $api->getTermByAppId(self::FOO_APP_ID);

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
        $this->assertEquals('Updated scope note', $term->getScopeNote());
        $this->assertTrue($term->getLastRetrievedTime() > $yesterday);
    }

    public function testGetTermByFolksaurusIdForTermNotInDbMakesRequestAndSavesNewTermIfFound()
    {
        $mockDI  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMock('Folksaurus\RequestExecutor', array(), array(), '', false);

        $mockDI->expects($this->once())
            ->method('getTermByFolksaurusId')
            ->with($this->equalTo(self::FOO_FOLKSAURUS_ID))
            ->will($this->returnValue(false));

        // Term not found, so send a request to Folksaurus for it.
        $mockRex->expects($this->once())
            ->method('getTermById')
            ->with($this->equalTo(self::FOO_FOLKSAURUS_ID))
            ->will($this->returnValue($this->_getFooTermArray()));

        // Save the term to the database.
        $mockDI->expects($this->once())
            ->method('saveTerm')
            ->with($this->isInstanceOf('Folksaurus\Term'));

        $api = new Api($mockDI, CONFIG_PATH, $mockRex);
        $term = $api->getTermByFolksaurusId(self::FOO_FOLKSAURUS_ID);

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
        $this->assertEquals('A term.', $term->getScopeNote());
        $this->assertTrue($term->getLastRetrievedTime() > 0);
    }

    public function testGetTermByFolksaurusIdForNonExpiredTermMakesNoRequestAndDoesNotUpdateTerm()
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
            ->method('getTermById');

        // Won't save, because no changes were retrieved.
        $mockDI->expects($this->never())
            ->method('saveTerm');

        $api = new Api($mockDI, CONFIG_PATH, $mockRex);
        $term = $api->getTermByFolksaurusId(self::FOO_FOLKSAURUS_ID);

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
    }

    public function testGetTermByFolksaurusIdForExpiredModifiedTermMakesRequestAndUpdatesTerm()
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
            ->method('getTermByIdIfModifiedSince')
            ->with($this->equalTo(self::FOO_FOLKSAURUS_ID))
            ->will($this->returnValue($updatedFooTermArray));

        // Save latest term info.
        $mockDI->expects($this->once())
            ->method('saveTerm')
            ->with($this->isInstanceOf('Folksaurus\Term'));

        $api = new Api($mockDI, CONFIG_PATH, $mockRex);
        $term = $api->getTermByFolksaurusId(self::FOO_FOLKSAURUS_ID);

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
        $this->assertEquals('Updated scope note', $term->getScopeNote());
        $this->assertTrue($term->getLastRetrievedTime() > 0);
    }

    public function testGetTermByFolksaurusIdForExpiredUnmodifiedTermMakesRequestButDoesNotUpdate()
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
            ->method('getTermByIdIfModifiedSince')
            ->with($this->equalTo(self::FOO_FOLKSAURUS_ID))
            ->will($this->returnValue(false));

        // No term saved since there were no changes.
        $mockDI->expects($this->never())
            ->method('saveTerm');

        $api = new Api($mockDI, CONFIG_PATH, $mockRex);
        $term = $api->getTermByFolksaurusId(self::FOO_FOLKSAURUS_ID);

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
        $this->assertEquals('A term.', $term->getScopeNote());
        $this->assertTrue($term->getLastRetrievedTime() > 0);
    }

    public function testGetOrCreateTermGetsTermFromDbIfItExistsAndIsCurrent()
    {
        $mockDI  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMock('Folksaurus\RequestExecutor', array(), array(), '', false);

        $fooTermArray = $this->_getFooTermArray();
        $fooTermArray['last_retrieved'] = time();

        $mockDI->expects($this->once())
            ->method('getTermByName')
            ->with($this->equalTo('Foo'))
            ->will($this->returnValue($fooTermArray));

        // Won't send a request to Folksaurus, because term was retrieved recently.
        $mockRex->expects($this->never())
            ->method('getTermByName');
        $mockRex->expects($this->never())
            ->method('getTermByIdIfModifiedSince');
        $mockRex->expects($this->never())
            ->method('getOrCreateTerm');
        $mockRex->expects($this->never())
            ->method('createTerm');

        // Won't save, because no changes were retrieved.
        $mockDI->expects($this->never())
            ->method('saveTerm');

        $api = new Api($mockDI, CONFIG_PATH, $mockRex);
        $term = $api->getOrCreateTerm('Foo');

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
    }

    public function testGetOrCreateTermGetsLatestVersionOfTermIfItExistsInDbButIsPassedExpireTime()
    {
        $mockDI  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMock('Folksaurus\RequestExecutor', array(), array(), '', false);

        $yesterday = time() - (60 * 60 * 24);
        $fooTermArray = $this->_getFooTermArray();
        $fooTermArray['last_retrieved'] = $yesterday;

        $mockDI->expects($this->once())
            ->method('getTermByName')
            ->with($this->equalTo('Foo'))
            ->will($this->returnValue($fooTermArray));

        $updatedFooTermArray = $fooTermArray;
        $updatedFooTermArray['scope_note'] = 'Updated scope note';

        $mockRex->expects($this->once())
            ->method('getTermByIdIfModifiedSince')
            ->with(
                $this->equalTo(self::FOO_FOLKSAURUS_ID),
                $this->equalTo($yesterday)
            )->will($this->returnValue($updatedFooTermArray));

        $mockDI->expects($this->once())
            ->method('saveTerm')
            ->with($this->isInstanceOf('Folksaurus\Term'));

        $api = new Api($mockDI, CONFIG_PATH, $mockRex);
        $term = $api->getOrCreateTerm('Foo');

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals('Updated scope note', $term->getScopeNote());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
    }

    public function testGetOrCreateTermGetsOrCreatesTermInFolksaurusThenAddsItToTheDb()
    {
        $mockDI  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMock('Folksaurus\RequestExecutor', array(), array(), '', false);

        $mockDI->expects($this->once())
            ->method('getTermByName')
            ->with($this->equalTo('Foo'))
            ->will($this->returnValue(false));

        $mockRex->expects($this->once())
            ->method('getOrCreateTerm')
            ->with('Foo')
            ->will($this->returnValue($this->_getFooTermArray()));

        $mockDI->expects($this->once())
            ->method('saveTerm')
            ->with($this->isInstanceOf('Folksaurus\Term'));

        $api = new Api($mockDI, CONFIG_PATH, $mockRex);
        $term = $api->getOrCreateTerm('Foo');

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
    }

    public function testGetOrCreateTermCreatesTermLocallyIfUnableToCreateItInFolksaurus()
    {
        $mockDI  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMock('Folksaurus\RequestExecutor', array(), array(), '', false);

        $mockDI->expects($this->once())
            ->method('getTermByName')
            ->with($this->equalTo('Foo'))
            ->will($this->returnValue(false));

        $mockRex->expects($this->once())
            ->method('getOrCreateTerm')
            ->with('Foo')
            ->will($this->returnValue(false));

        $mockDI->expects($this->once())
            ->method('saveTerm')
            ->with($this->isInstanceOf('Folksaurus\Term'));

        $api = new Api($mockDI, CONFIG_PATH, $mockRex);
        $term = $api->getOrCreateTerm('Foo');

        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals('', $term->getId());
    }

    public function testGetOrCreateOnRemotelyDeletedTermInDbReturnsFalseAndCallsDeleteTerm()
    {
        $mockDI  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMock('Folksaurus\RequestExecutor', array(), array(), '', false);

        $fooTermArray = $this->_getFooTermArray();

        $mockDI->expects($this->once())
            ->method('getTermByName')
            ->with($this->equalTo('Foo'))
            ->will($this->returnValue($fooTermArray));

        $mockRex->expects($this->once())
            ->method('getTermByIdIfModifiedSince')
            ->with(
                $this->equalTo(self::FOO_FOLKSAURUS_ID),
                $this->equalTo($fooTermArray['last_retrieved'])
            )->will($this->returnValue(false));

        $mockRex->expects($this->once())
            ->method('getLatestResponseCode')
            ->will($this->returnValue(StatusCodes::GONE));

        $mockDI->expects($this->once())
            ->method('deleteTerm')
            ->with($this->equalTo($fooTermArray['app_id']));

        $mockDI->expects($this->never())
            ->method('saveTerm');

        $api = new Api($mockDI, CONFIG_PATH, $mockRex);
        $term = $api->getOrCreateTerm('Foo');

        $this->assertFalse($term);
    }

    public function testGetOrCreateOnRemotelyDeletedTermNotinDbReturnsFalseAndDoesNotSaveTerm()
    {
        $mockDI  = $this->getMock('Folksaurus\DataInterface');
        $mockRex = $this->getMock('Folksaurus\RequestExecutor', array(), array(), '', false);

        $fooTermArray = $this->_getFooTermArray();

        $mockDI->expects($this->once())
            ->method('getTermByName')
            ->with($this->equalTo('Foo'))
            ->will($this->returnValue(false));

        $mockRex->expects($this->once())
            ->method('getOrCreateTerm')
            ->with($this->equalTo('Foo'))
            ->will($this->returnValue(false));

        // The FORBIDDEN code is returned when trying to PUT a term that has been deleted.
        $mockRex->expects($this->once())
            ->method('getLatestResponseCode')
            ->will($this->returnValue(StatusCodes::FORBIDDEN));

        $mockDI->expects($this->never())
            ->method('saveTerm');

        // Delete not called because there is no local term to delete.
        $mockDI->expects($this->never())
            ->method('deleteTerm');

        $api = new Api($mockDI, CONFIG_PATH, $mockRex);
        $term = $api->getOrCreateTerm('Foo');

        $this->assertFalse($term);
    }

}
