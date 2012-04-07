<?php
namespace Folksaurus;

/**
 * Test class for Term.
 */
class TermTest extends \PHPUnit_Framework_TestCase
{
    protected function _getPreferredTermArray()
    {
        return array(
            'id'         => '1',
            'name'       => 'Foo',
            'scope_note' => 'A preferred term.',
            'broader'    => array(array('id' => '2', 'name' => 'Super Foo')),
            'narrower'   => array(array('id' => '3', 'name' => 'Sub Foo')),
            'use'        => array(),
            'used_for'   => array(array('id' => '4', 'name' => 'Faux')),
            'related'    => array(
                                array('id' => '5', 'name' => 'Phoo'),
                                array('id' => '6', 'name' => 'Bar')
                            )
        );
    }

    protected function _getNonPreferredTermArray()
    {
        return array(
            'id'         => '4',
            'name'       => 'Faux',
            'scope_note' => 'A non-preferred term.',
            'broader'    => array(),
            'narrower'   => array(),
            'use'        => array(array('id' => '1', 'name' => 'Foo')),
            'used_for'   => array(),
            'related'    => array()
        );
    }

    protected function _getAmbiguousTermArray()
    {
        return array(
            'id'         => '4',
            'name'       => 'Faux',
            'scope_note' => 'A non-preferred term.',
            'broader'    => array(),
            'narrower'   => array(),
            'use'        => array(
                                array('id' => '1', 'name' => 'Foo'),
                                array('id' => '5', 'name' => 'Phoo')
                         ),
            'used_for'   => array(),
            'related'    => array()
        );
    }

    protected function _getUnsortedTermArray()
    {
        return array(
            'id'         => '7',
            'name'       => 'Unsorted',
            'scope_note' => 'An unsorted term.',
            'broader'    => array(),
            'narrower'   => array(),
            'use'        => array(),
            'used_for'   => array(),
            'related'    => array()
        );
    }

    public function testAccessorMethods()
    {
        $mockApi = $this->getMock('Folksaurus\Api', array(), array(), '', false);

        $termArray = $this->_getPreferredTermArray();
        $term = new Term($termArray, $mockApi);

        $this->assertEquals('1', $term->getId());
        $this->assertEquals('Foo', $term->getName());

        $broader = $term->getBroaderTerms();
        $this->assertEquals(1, count($broader));
        $this->assertTrue($broader[0] instanceof TermSummary);
        $this->assertEquals('2', $broader[0]->getId());
        $this->assertEquals('Super Foo', $broader[0]->getName());

        $narrower = $term->getNarrowerTerms();
        $this->assertEquals(1, count($narrower));
        $this->assertTrue($narrower[0] instanceof TermSummary);
        $this->assertEquals('3', $narrower[0]->getId());
        $this->assertEquals('Sub Foo', $narrower[0]->getName());

        $usedFor = $term->getUsedForTerms();
        $this->assertEquals(1, count($usedFor));
        $this->assertTrue($usedFor[0] instanceof TermSummary);
        $this->assertEquals('4', $usedFor[0]->getId());
        $this->assertEquals('Faux', $usedFor[0]->getName());

        $related = $term->getRelatedTerms();
        $this->assertEquals(2, count($related));
        $this->assertTrue($related[0] instanceof TermSummary);
        $this->assertTrue($related[1] instanceof TermSummary);
        $this->assertEquals('5', $related[0]->getId());
        $this->assertEquals('Phoo', $related[0]->getName());
        $this->assertEquals('6', $related[1]->getId());
        $this->assertEquals('Bar', $related[1]->getName());
    }

    public function testGetPreferredOnNonpreferredTermCallsGetTermByFolksaurusIdOnApiObject()
    {
        $mockApi = $this->getMock('Folksaurus\Api', array(), array(), '', false);

        $preferredTerm = new Term($this->_getPreferredTermArray(), $mockApi);

        $mockApi->expects($this->once())
            ->method('getTermByFolksaurusId')
            ->with($this->equalTo('1'))
            ->will($this->returnValue($preferredTerm));

        $term = new Term($this->_getNonPreferredTermArray(), $mockApi);
        $returnValue = $term->getPreferred();
        $this->assertEquals($preferredTerm, $returnValue);
    }

    public function testGetPreferredOnPreferredTermReturnsItself()
    {
        $mockApi = $this->getMock('Folksaurus\Api', array(), array(), '', false);

        $term = new Term($this->_getPreferredTermArray(), $mockApi);
        $returnValue = $term->getPreferred();
        $this->assertEquals($term, $returnValue);
    }

    public function testGetPreferredOnUnsortedTermReturnsItself()
    {
        $mockApi = $this->getMock('Folksaurus\Api', array(), array(), '', false);

        $term = new Term($this->_getUnsortedTermArray(), $mockApi);
        $returnValue = $term->getPreferred();
        $this->assertEquals($term, $returnValue);
    }

    public function testGetPreferredForAmbiguousTermReturnsArrayOfResultsOfGetByFolkIdCalls()
    {
        // If term is ambiguous, it returns an array of Term objects.
        $mockApi = $this->getMock('Folksaurus\Api', array(), array(), '', false);
        $dummyApi = $this->getMock('Folksaurus\Api', array(), array(), '', false);

        $termPhooArray = array(
            'id'         => '5',
            'name'       => 'Phoo',
            'scope_note' => 'A preferred term.',
            'broader'    => array(),
            'narrower'   => array(),
            'use'        => array(),
            'used_for'   => array(array('id' => '4', 'name' => 'Faux')),
            'related'    => array()
        );
        $termPhoo = new Term($termPhooArray, $dummyApi);
        $termFoo  = new Term(
            $this->_getPreferredTermArray(),
            $dummyApi
        );

        $mockApi->expects($this->at(0))
            ->method('getTermByFolksaurusId')
            ->with($this->equalTo('1'))
            ->will($this->returnValue($termFoo));

        $mockApi->expects($this->at(1))
            ->method('getTermByFolksaurusId')
            ->with($this->equalTo('5'))
            ->will($this->returnValue($termPhoo));

        $term = new Term($this->_getAmbiguousTermArray(), $mockApi);
        $returnValue = $term->getPreferred();
        $this->assertEquals(2, count($returnValue));
        $this->assertEquals($termFoo, $returnValue[0]);
        $this->assertEquals($termPhoo, $returnValue[1]);

    }

    public function testGetStatusReturnsPreferredIfTermHasRelationshipsOtherThanUse()
    {
        $mockDI = $this->getMock('Folksaurus\DataInterface');
        $api = new Api($mockDI);

        $preferredTerm = new Term($this->_getPreferredTermArray(), $api);

        $this->assertEquals(Term::STATUS_PREFERRED, $preferredTerm->getStatus());
    }

    public function testGetStatusReturnsNonPreferredIfTermHasUseRelationships()
    {
        $mockDI = $this->getMock('Folksaurus\DataInterface');
        $api = new Api($mockDI);

        $nonPreferredTerm = new Term($this->_getNonPreferredTermArray(), $api);

        $this->assertEquals(Term::STATUS_NONPREFERRED, $nonPreferredTerm->getStatus());
    }

    public function testGetStatusReturnsUnsortedIfTermHasNoRelationships()
    {
        $mockDI = $this->getMock('Folksaurus\DataInterface');
        $api = new Api($mockDI);

        $unsortedTerm = new Term($this->_getUnsortedTermArray(), $api);

        $this->assertEquals(Term::STATUS_UNSORTED, $unsortedTerm->getStatus());
    }

    public function testIsAmbiguousReturnsTrueIfTermHasMoreThanOneUseRelationships()
    {
        $mockDI = $this->getMock('Folksaurus\DataInterface');
        $api = new Api($mockDI);

        $preferredTerm = new Term($this->_getPreferredTermArray(), $api);
        $nonPreferredTerm = new Term($this->_getNonPreferredTermArray(), $api);
        $unsortedTerm = new Term($this->_getUnsortedTermArray(), $api);
        $ambiguousTerm = new Term($this->_getAmbiguousTermArray(), $api);

        $this->assertFalse($preferredTerm->isAmbiguous());
        $this->assertFalse($nonPreferredTerm->isAmbiguous());
        $this->assertFalse($unsortedTerm->isAmbiguous());
        $this->assertTrue($ambiguousTerm->isAmbiguous());
    }
}
