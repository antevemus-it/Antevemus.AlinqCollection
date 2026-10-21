<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * The tested blocks of the documentation, EN and pt-BR: the code blocks are the test.
 *
 * A tested block is a ```php block preceded by the marker `<!-- tested: <id> -->`: the 2.2.0
 * blocks (forward 047, RN-05), on the API pages since 2.2.1, and the Quick Start (2.2.1), on
 * getting-started/index and in each README. Each one is read from the page itself and executed
 * in its own namespace (a leading `<?php` is dropped and a `declare(strict_types=1);` is kept
 * ahead of the namespace). A one-line statement followed by a comment that is a PHP literal
 * (`// [1, 3]`, `// 'Álvaro'`, `// true`, `// 0`), or a scalar literal followed by a remark in
 * parentheses (`// true (via array_any)`), asserts that the statement evaluates to that literal;
 * a `// Result: <literal>` (`// Resultado:`) line asserts the variable assigned last;
 * `// throws Class: message` asserts the class (short name) and the message of the exception.
 * Any other comment is prose, and so is a literal that does not parse (`['id' => 1, ...]`).
 * The guard compares the two languages: the same markers on the same pages, in the same order,
 * with the same code (comments and whitespace aside) and the same answers.
 *
 * @version    2.4.0
 * @package    Antevemus\ALinq\Tests
 * @subpackage Unit
 */
final class ReadmeConsumerGapsTest extends TestCase
{
    /**
     * The tested blocks and the number of answers each one writes down: the Quick Start (2.2.1),
     * the blocks of 2.2.0 and those of 2.4.0 (3-comparer, 6-comparer, 10-joins).
     */
    private const BLOCKS = [
        'quick-start' => 4,
        '3-keys' => 6,
        '3-comparer' => 7,
        '5-dictionary' => 7,
        '6-lookup' => 7,
        '6-comparer' => 6,
        '7-ordinal' => 5,
        '7-culture' => 6,
        '10-grouping' => 6,
        '10-joins' => 3,
        '10-csv' => 2,
    ];

    /** The page of docs/<language> that hosts each tested block. */
    private const PAGES = [
        'quick-start' => 'getting-started/index',
        '3-keys' => 'api/joining',
        '3-comparer' => 'api/joining',
        '5-dictionary' => 'api/projections',
        '6-lookup' => 'api/grouping',
        '6-comparer' => 'api/grouping',
        '7-ordinal' => 'api/ordering',
        '7-culture' => 'api/ordering',
        '10-grouping' => 'api/lazy-streaming',
        '10-joins' => 'api/lazy-streaming',
        '10-csv' => 'api/lazy-streaming',
    ];

    /** The README of each language: its only block is the Quick Start, run here too. */
    private const READMES = ['en' => 'README.md', 'pt-BR' => 'README.pt-BR.md'];

    /** Blocks that need ext-intl (StringComparer::culture()). */
    private const NEEDS_INTL = ['7-culture', '6-comparer'];

    /**
     * @return array<string, array{string, string}>
     */
    public static function blocks(): array
    {
        $cases = [];
        foreach (array_keys(self::READMES) as $language) {
            foreach (self::PAGES as $id => $page) {
                $file = "docs/$language/$page.md";
                $cases["$file $id"] = [$file, $id];
            }
        }
        foreach (self::READMES as $readme) {
            $cases["$readme quick-start"] = [$readme, 'quick-start'];
        }
        return $cases;
    }

    /**
     * @return array<string, string> id => code
     */
    private static function tested(string $file): array
    {
        $text = (string) file_get_contents(dirname(__DIR__, 2) . '/' . $file);
        preg_match_all('/<!-- tested: ([\w-]+) -->\s*```php\n(.*?)```/s', $text, $matches, PREG_SET_ORDER);
        $blocks = [];
        foreach ($matches as [, $id, $code]) {
            $blocks[$id] = $code;
        }
        return $blocks;
    }

    #[DataProvider('blocks')]
    public function testTheBlockRunsAndAnswersAsWritten(string $file, string $id): void
    {
        if (in_array($id, self::NEEDS_INTL, true) && !extension_loaded('intl')) {
            $this->markTestSkipped("Block $id uses StringComparer::culture(), which needs ext-intl");
        }

        $blocks = self::tested($file);
        $this->assertArrayHasKey($id, $blocks, "$file has the tested block $id");
        [$code, $expectations] = self::instrument($blocks[$id]);
        $results = self::execute($code, $file, $id);

        $this->assertCount(self::BLOCKS[$id], $expectations, "$file $id: the answers written in the block");
        $this->assertCount(count($expectations), $results, "$file $id: every answer evaluated");
        foreach ($expectations as $i => [$expected, $line]) {
            $this->assertSame($expected, $results[$i], "$file $id: $line");
        }
    }

    public function testBothLanguagesCarryTheSameBlocks(): void
    {
        $english = self::language('en');
        $portuguese = self::language('pt-BR');

        $this->assertSame(array_keys(self::BLOCKS), array_keys(self::PAGES), 'every tested block has its page');
        $this->assertSame(array_keys(self::BLOCKS), array_keys($english), 'docs/en: the tested blocks, in order');
        $this->assertSame(array_keys(self::BLOCKS), array_keys($portuguese), 'docs/pt-BR: the tested blocks, in order');
        foreach (self::READMES as $language => $readme) {
            $this->assertSame(['quick-start' => ($language === 'en' ? $english : $portuguese)['quick-start']], self::tested($readme), "$readme: its only tested block is the Quick Start of docs/$language/getting-started/index.md, byte by byte");
        }
        foreach ($english as $id => $code) {
            $this->assertSame(self::codeTokens($code), self::codeTokens($portuguese[$id]), "block $id: the same code in both languages, comments aside");
            $this->assertSame(array_column(self::instrument($code)[1], 0), array_column(self::instrument($portuguese[$id])[1], 0), "block $id: the same answers in both languages");
        }
    }

    /**
     * The tested blocks of every page of docs/<language>, in the order of PAGES; each page carries
     * exactly the markers declared for it.
     *
     * @return array<string, string> id => code
     */
    private static function language(string $language): array
    {
        $blocks = [];
        foreach (array_unique(self::PAGES) as $page) {
            $found = self::tested("docs/$language/$page.md");
            $declared = array_keys(self::PAGES, $page, true);
            self::assertSame($declared, array_keys($found), "docs/$language/$page.md: the tested blocks declared for the page");
            $blocks += $found;
        }
        return array_merge(array_flip(array_keys(self::PAGES)), $blocks);
    }

    /**
     * Rewrites a block so that every written answer is recorded in $__results.
     *
     * @return array{string, list<array{mixed, string}>} [code, [[expected, original line], ...]]
     */
    private static function instrument(string $block): array
    {
        $lines = explode("\n", $block);
        $expectations = [];
        $lastAssigned = null;
        foreach ($lines as $n => $line) {
            if (preg_match('/^\s*\/\/\s*(?:Result|Resultado):\s*(.+?)\s*$/', $line, $r) && $lastAssigned !== null) {
                $expected = self::literal($r[1]);
                if ($expected !== null) {
                    $lines[$n] = '$__results[] = ' . $lastAssigned . ';';
                    $expectations[] = [$expected[0], trim($line)];
                }
                continue;
            }
            if (preg_match('/^(\$\w+)\s*=/', $line, $a)) {
                $lastAssigned = $a[1];
            }
            if (!preg_match('/^(\S.*?);\s*\/\/\s*(.+?)\s*$/', $line, $m)) {
                continue;
            }
            [, $statement, $comment] = $m;
            if (preg_match('/^throws (\w+): (.+)$/', $comment, $t)) {
                $lines[$n] = 'try { ' . $statement . '; $__results[] = "no exception"; } catch (\Throwable $__e) { $__results[] = [(new \ReflectionClass($__e))->getShortName(), $__e->getMessage()]; }';
                $expectations[] = [[$t[1], $t[2]], trim($line)];
                continue;
            }
            $expected = self::literal($comment);
            if ($expected === null) {
                continue; // prose
            }
            $lines[$n] = '$__results[] = ' . $statement . ';';
            $expectations[] = [$expected[0], trim($line)];
        }

        return [implode("\n", $lines), $expectations];
    }

    /**
     * The value of a written answer, wrapped in a one-element array, or null when the comment is
     * prose: a PHP literal, or a scalar literal followed by a remark in parentheses
     * (`true (via array_any)`); a literal that does not parse (`['id' => 1, 'name' => 'Alice'...]`,
     * an elided illustration) is prose.
     *
     * @return array{mixed}|null
     */
    private static function literal(string $comment): ?array
    {
        if (preg_match('/^(\'[^\']*\'|-?\d+(?:\.\d+)?|true|false|null)\s+\(.*\)$/', $comment, $m)) {
            $comment = $m[1];
        }
        if (!preg_match('/^(\[.*\]|\'.*\'|-?\d+(?:\.\d+)?|true|false|null)$/', $comment)) {
            return null;
        }
        try {
            return [eval("return $comment;")];
        } catch (\ParseError) {
            return null;
        }
    }

    /**
     * Runs a block in its own namespace and returns what it recorded.
     *
     * @return list<mixed>
     */
    private static function execute(string $code, string $file, string $id): array
    {
        $namespace = 'Antevemus\\ALinq\\Tests\\Readme\\Gaps\\' . preg_replace('/\W/', '_', $file) . '_' . preg_replace('/\W/', '_', $id);
        // A block written as a whole file (the Quick Start): its open tag cannot follow the
        // namespace, and its strict_types declaration must come first, so it is kept ahead.
        $code = (string) preg_replace('/^\s*<\?php\s*/', '', $code);
        $declare = '';
        if (preg_match('/^declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;/', $code, $d)) {
            $declare = $d[0] . "\n";
            $code = substr($code, strlen($d[0]));
        }
        $__results = [];
        try {
            eval($declare . "namespace $namespace;\n" . $code);
        } catch (Throwable $e) {
            throw new \RuntimeException("$file block $id threw " . $e::class . ': ' . $e->getMessage(), 0, $e);
        }
        return $__results;
    }

    /**
     * The code tokens of a block, without comments and whitespace.
     *
     * @return list<string>
     */
    private static function codeTokens(string $code): array
    {
        $tokens = [];
        foreach (token_get_all('<?php ' . $code) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_WHITESPACE, T_OPEN_TAG], true)) {
                    continue;
                }
                $tokens[] = $token[1];
            } else {
                $tokens[] = $token;
            }
        }
        return $tokens;
    }
}
