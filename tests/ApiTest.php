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
        $mockDM  = $this->getMock('Folksaurus\DataMapper');
        $mockRex = $this->getMock('Folksaurus\RequestExecutor', array(), array(), '', false);

        $mockDM->expects($this->once())
            ->method('getTermByAppId')
            ->with($this->equalTo(404))
            ->will($this->returnValue(false));

        $api = new Api($mockDM, $mockRex, CONFIG_PATH);
        $term = $api->getTermByAppId(404);
        $this->assertFalse($term);
    }

    public function testGetTermByAppIdForNonExpiredTermInDatabase()
    {
        $mockDM  = $this->getMock('Folksaurus\DataMapper');
        $mockRex = $this->getMock('Folksaurus\RequestExecutor', array(), array(), '', false);

        $fooTermArray = $this->_getFooTermArray();
        $fooTermArray['last_retrieved'] = time();

        $mockDM->expects($this->once())
            ->method('getTermByAppId')
            ->with($this->equalTo(self::FOO_APP_ID))
            ->will($this->returnValue($fooTermArray));

        $api = new Api($mockDM, $mockRex, CONFIG_PATH);
        $term = $api->getTermByAppId(self::FOO_APP_ID);
        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
    }

    public function testGetTermByAppIdForExpiredTermInDatabase()
    {
        $mockDM  = $this->getMock('Folksaurus\DataMapper');
        $mockRex = $this->getMock('Folksaurus\RequestExecutor', array(), array(), '', false);

        $fooTermArray = $this->_getFooTermArray();

        $mockDM->expects($this->once())
            ->method('getTermByAppId')
            ->with($this->equalTo(self::FOO_APP_ID))
            ->will($this->returnValue($fooTermArray));

        $updatedFooTermArray = $fooTermArray;
        $updatedFooTermArray['scope_note'] = 'Updated scope note';

        $mockRex->expects($this->once())
            ->method('getById')
            ->with($this->equalTo(self::FOO_FOLKSAURUS_ID))
            ->will($this->returnValue($updatedFooTermArray));

        $api = new Api($mockDM, $mockRex, CONFIG_PATH);

        $updatedFooTerm = new Term($updatedFooTermArray, $api);

        $mockDM->expects($this->once())
            ->method('saveTerm')
            ->with($this->isInstanceOf('Folksaurus\Term'));

        $term = $api->getTermByAppId(100);
        $this->assertTrue($term instanceof Term);
        $this->assertEquals('Foo', $term->getName());
        $this->assertEquals(self::FOO_FOLKSAURUS_ID, $term->getId());
        $this->assertEquals(self::FOO_APP_ID, $term->getAppId());
        $this->assertEquals('Updated scope note', $term->getScopeNote());
        $this->assertTrue($term->getLastRetrievedTime() > 0);
    }

    public function testGetTermByFolksaurusIdForTermNotInDatabase()
    {
        $this->markTestIncomplete();
    }

    public function testGetTermByFolksaurusIdForNonExpiredTermInDatabase()
    {
        $this->markTestIncomplete();
    }

    public function testGetTermByFolksaurusIdForExpiredTermInDatabase()
    {
        $this->markTestIncomplete();
    }
}
