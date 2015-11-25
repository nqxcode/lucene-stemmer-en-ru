<?php namespace Nqxcode\Stemming;

use ZendSearch\Lucene\Analysis\Token;
use ZendSearch\Lucene\Analysis\TokenFilter\TokenFilterInterface;

use \phpMorphy;

/**
 * Class MorphyFilter
 *
 * The morphological filter used by search.
 *
 * @package SearchEngine
 */
class TokenFilterEnRu implements TokenFilterInterface
{

    const DEFAULT_DICTIONARY_ENCODING = 'windows-1251';

    /**
     * @var phpMorphy[]
     */
    protected $morphy;

    protected $directory;
    protected $language;
    protected $options;

    /**
     * The minimum length of a lexeme, admissible in case of token normalization.
     *
     * @var int
     */
    const MIN_TOKEN_LENGTH = 1;

    public function __construct(PhpmorphyFactory $factory)
    {
        foreach ($this->configs() as $key => $config) {
            // Get phpMorphy instances for each dictionary.
            $this->morphy[$key] = $factory->newInstance($config['directory'], $config['language'], $config['options']);
        }
    }

    /**
     * Receives the list of configurations for phpMorphy for en/ru dictionaries.
     *getPseudoRoot
     * @return array
     */
    protected function configs()
    {
        $dictionariesRoot = __DIR__ . '/../../../resources/phpmorphy/dictionaries';

        $configs = array();

        $config['directory'] = $dictionariesRoot . '/ru/windows-1251'; // Path to directory with dictionaries.
        $config['language'] = 'ru_RU'; // Specify, for what language we will use the dictionary.
        $config['options'] = [
            'storage' => PHPMORPHY_STORAGE_FILE,
            'predict_by_suffix' => true,
            'predict_by_db' => true
        ];

        $configs['ru'] = $config;

        $config['directory'] = $dictionariesRoot . '/en/windows-1250';
        $config['language'] = 'en_EN';
        $config['options'] = [
            'storage' => PHPMORPHY_STORAGE_FILE,
            'predict_by_suffix' => true,
            'predict_by_db' => true
        ];

        $configs['en'] = $config;

        return $configs;
    }

    /**
     * Detec language of sting.
     *
     * @param $str
     * @return mixed
     */
    protected static function languageDetect($str)
    {
        if (preg_match('/[А-Яа-яЁё]/', $str)) {
            return 'ru';
        } elseif (preg_match('/[A-Za-z]/', $str)) {
            return 'en';
        }

        return 'unknown';
    }

    /**
     * Receives phpMorphy object by search query string.
     *
     * @param $str
     * @return phpMorphy
     */
    protected function getPhpmorphyByString($str)
    {
        $lang = self::languageDetect($str);

        switch ($lang) {
            case 'unknown':
                $morphy = $this->morphy['ru'];
                break;
            default:
                $morphy = $this->morphy[$lang];
        }

        return $morphy;
    }

    /**
     * Receives the list with pseudo-roots.
     *
     * @param string $toSearch
     * @return string[]
     */
    protected function getPseudoRoots($toSearch)
    {
        $morphy = $this->getPhpmorphyByString($toSearch);
        return $morphy->getPseudoRoot($toSearch);
    }

    /**
     * Receives the dictionary encoding.
     *
     * @param string $toSearch
     * @return string
     */
    protected function getDictionaryEncoding($toSearch)
    {
        $morphy = $this->getPhpmorphyByString($toSearch);
        $resultEncoding = $morphy->getEncoding();

        $encodingsList = mb_list_encodings();
        if (!in_array($resultEncoding, $encodingsList)) {
            $resultEncoding = self::DEFAULT_DICTIONARY_ENCODING;
        }

        return $resultEncoding;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize(Token $srcToken)
    {
        $newTokenString = $this->getPseudoRoot($srcToken->getTermText());


        $newToken = new Token(
            $newTokenString,
            $srcToken->getStartOffset(),
            $srcToken->getEndOffset()
        );

        $newToken->setPositionIncrement($srcToken->getPositionIncrement());

        return $newToken;
    }

    private function getPhpmorphyPseudoRoot($sourceStr)
    {
        $pseudoRootList = [];

        $sourceStr = mb_strtoupper($sourceStr, 'utf-8');

        $encoding = $this->getDictionaryEncoding($sourceStr);

        // If the lexeme is shorter than MIN_TOKEN_LENGTH of characters, we don't use it.
        if (mb_strlen($sourceStr, 'utf-8') < self::MIN_TOKEN_LENGTH) {
            return null;
        }

        $sourceStr = mb_convert_encoding($sourceStr, $encoding, 'utf-8');

        if (mb_strlen($sourceStr, $encoding) < self::MIN_TOKEN_LENGTH) {
            return null;
        }

        /**
         * Get pseudo-root for a word. it is hardcore))
         */
        $pseudoRootResult[] = $sourceStr;
        do {
            $temp = $pseudoRootResult[0];
            $pseudoRootResult = $this->getPseudoRoots($temp);

            // If many pseudo-roots return, select the shortest.
            if (is_array($pseudoRootResult)) {
                usort(
                    $pseudoRootResult,
                    function ($a, $b) use ($encoding) {
                        $len1 = mb_strlen($a, $encoding);
                        $len2 = mb_strlen($b, $encoding);

                        return $len1 > $len2;
                    }
                );
            }

            $flag = $pseudoRootResult !== false && is_array($pseudoRootResult) && $pseudoRootResult[0] != $temp;

            if ($flag) {
                array_unshift($pseudoRootList, $pseudoRootResult[0]);
            }
        } while ($flag);


        if (count($pseudoRootList) == 0 && $pseudoRootResult === false) {
            // If unable to get pseudo-root, take the original word.
            $pseudoRootStr = $sourceStr;
        } else {
            // From the received list of pseudo-roots select the first which length is at least MIN_TOKEN_LENGTH.
            $pseudoRootStr = null;

            foreach ($pseudoRootList as $pseudoRoot) {
                if (mb_strlen($pseudoRoot, $encoding) < self::MIN_TOKEN_LENGTH) {
                    continue;
                } else {
                    $pseudoRootStr = $pseudoRoot;
                    break;
                }
            }

            // If unable to get pseudo-root even now, take the original word.
            if (is_null($pseudoRootStr)) {
                $pseudoRootStr = $sourceStr;
            }
        }

        $pseudoRootStr = mb_convert_encoding($pseudoRootStr, 'utf-8', $encoding);
        return $pseudoRootStr;
    }

    private function getPhpStemmerPseudoRoot($word)
    {
        $word = mb_strtolower($word, 'utf-8');

        return stemword($word, $this->languageDetect($word), 'UTF_8');
    }

    private function getPseudoRoot($word)
    {
        if (extension_loaded('stemmer')) {
            $pseudoRoot =  $this->getPhpStemmerPseudoRoot($word);
        } else {
            $pseudoRoot = $this->getPhpmorphyPseudoRoot($word);
        }

        $pseudoRoot = mb_strtoupper($pseudoRoot, 'utf-8');

        return $pseudoRoot;
    }
}
