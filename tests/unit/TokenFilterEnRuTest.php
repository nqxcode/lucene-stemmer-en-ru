<?php namespace tests\unit;

use Nqxcode\Stemming\TokenFilterEnRu;
use Mockery as m;
use ZendSearch\Lucene\Analysis\Token;
use tests\TestCase;

/**
 * Class EnRuTokenFilterTest
 * @package unit
 */
class EnRuTokenFilterTest extends TestCase
{
    /** @var  \Mockery\MockInterface */
    private $phpmorphyFactory;
    /** @var  \Mockery\MockInterface */
    private $phpmorphy;

    public function setUp()
    {
        parent::setUp();

        $this->phpmorphyFactory = m::mock('Nqxcode\Stemming\PhpmorphyFactory');
        $this->phpmorphyFactory->shouldReceive('newInstance')->andReturn($phpmorphy = m::mock('\phpMorphy'));
        $this->phpmorphy = $phpmorphy;
    }

    protected function tearDown()
    {
        m::close();
    }

    /**
     * @dataProvider getNormalizeDataProvider
     */
    public function testNormalize($source, $expected, $pseudoRoots, $encoding)
    {
        $this->phpmorphy->shouldReceive('getEncoding')->andReturn($encoding);
        $this->phpmorphy->shouldReceive('getPseudoRoot')->andReturn($pseudoRoots);

        $filter = new TokenFilterEnRu($this->phpmorphyFactory);

        $token = new Token($source, 0, 100);
        $token->setPositionIncrement(50);

        $actualToken = $filter->normalize($token);

        $expectedToken = new Token($expected, 0, 100);
        $expectedToken->setPositionIncrement(50);

        $this->assertEquals($expectedToken, $actualToken);
    }

    public function getNormalizeDataProvider()
    {
        return [
            ['test', 'TEST', false, false],
            ['test', 'TEST', false, ''],
            ['test', 'TEST', '', false],
            ['test', 'TEST', '', ''],
            ['test', 'TEST', 'test', false],
            ['test', 'TEST', 'test', ''],
            ['test', 'TEST', 'test', 'utf-8'],

            ['test', 'TEST', ['TEST', 'TESTING'], 'utf-8'],
            ['test', 'TEST', ['TEST', 'TESTING'], 'unknown'],
            ['test', 'TEST', ['TEST', 'TESTING'], 'utf-8'],
            ['test', 'TEST', ['TEST', 'TESTING'], 'unknown'],
        ];
    }
} 