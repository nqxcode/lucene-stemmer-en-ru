<?php  namespace tests\functional;

use Nqxcode\Stemming\PhpmorphyFactory;
use Nqxcode\Stemming\TokenFilterEnRu;
use tests\TestCase;
use ZendSearch\Lucene\Analysis\Token;

/**
 * Class TokenFilterEnRuTest
 * @package tests\functional
 */
class TokenFilterEnRuTest extends TestCase
{
    /** @var TokenFilterEnRu */
    private $filter;

    public function setUp()
    {
        $factory = new PhpmorphyFactory;
        $this->filter = new TokenFilterEnRu($factory);
    }

    /**
     * @dataProvider getNormalizeDataProvider
     */
    public function testNormalize($source, $normalized)
    {

        $token = new Token($source, 0, 100);
        $token->setPositionIncrement(50);

        $actualToken = $this->filter->normalize($token);

        $expectedToken = new Token($normalized, 0, 100);
        $expectedToken->setPositionIncrement(50);

        $this->assertEquals($expectedToken, $actualToken);
    }

    public function getNormalizeDataProvider()
    {
        return
            [
                ['test', 'TEST'],
                ['tests', 'TEST'],
                ['testing', 'TEST'],
                ['tested', 'TEST'],

                ['asdfgh', 'ASDFGH'],

                ['тест', 'ТЕСТ'],
                ['тесты', 'ТЕСТ'],
                ['тестов' , 'ТЕСТ'],

                ['фывапр', 'ФЫВАПР']
            ];
    }
} 