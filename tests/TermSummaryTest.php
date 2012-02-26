<?php
namespace Folksaurus;

/**
 * Test class for TermSummary.
 */
class TermSummaryTest extends \PHPUnit_Framework_TestCase
{
    public function testGetCompleteTerm()
    {
        $mockRex = $this->getMock('Folksaurus\RequestExecutor', array(), array(), '', false);

        $term = new Term(
            array(
                'id'         => '1',
                'name'       => 'Foo',
                'scope_note' => '',
                'broader'    => array(),
                'narrower'   => array(),
                'used_for'   => array(),
                'use'        => array(),
                'related'    => array()
            ),
            new RequestExecutor(API_KEY, API_URL)
        );

        $mockRex->expects($this->once())
            ->method('getById')
            ->with($this->equalTo('1'))
            ->will($this->returnValue($term));

        $termSummary = new TermSummary(array('id' => '1', 'name' => 'Foo'), $mockRex);
        $returnValue = $termSummary->getCompleteTerm();
        $this->assertEquals($term, $returnValue);
    }
}
