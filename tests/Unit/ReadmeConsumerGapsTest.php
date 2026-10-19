<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * The README blocks of 2.2.0 (forward 047, RN-05), EN and pt-BR: the code blocks are the test.
 *
 * A tested block is a ```php block preceded by the marker `<!-- tested: <id> -->`. Each one is
 * read from the README itself and executed in its own namespace. A one-line statement followed
 * by a comment that is a PHP literal (`// [1, 3]`, `// 'Álvaro'`, `// true`, `// 0`) asserts
 * that the statement evaluates to that literal; `// throws Class: message` asserts the class
 * (short name) and the message of the exception. Any other comment is prose. The guard
 * compares the two READMEs: the same markers, in the same order, with the same code (comments
 * and whitespace aside), and every block has at least one answer.
 */
final class ReadmeConsumerGapsTest extends TestCase
{
    /**
     * The tested blocks of 2.2.0 and the number of answers each one writes down.
     */
    private const BLOCKS = [
        '3-keys' => 6,
        '5-dictionary' => 7,
        '6-lookup' => 7,
        '7-ordinal' => 5,
        '7-culture' => 6,
        '10-grouping' => 6,
        '10-csv' => 2,
    ];

    /** Blocks that need ext-intl (StringComparer::culture()). */
    private const NEEDS_INTL = ['7-culture'];

    /**
     * @return array<string, array{string, string}>
     */
    public static function blocks(): array
    {
        $cases = [];
        foreach (['README.md', 'README.pt-BR.md'] as $readme) {
            foreach (array_keys(self::BLOCKS) as $id) {
                $cases["$readme $id"] = [$readme, $id];
            }
        }
        return $cases;
    }

    /**
     * @return array<string, string> id => code
     */
    private static function tested(string $readme): array
    {
        $text = (string) file_get_contents(dirname(__DIR__, 2) . '/' . $readme);
        preg_match_all('/<!-- tested: ([\w-]+) -->\s*```php\n(.*?)```/s', $text, $matches, PREG_SET_ORDER);
        $blocks = [];
        foreach ($matches as [, $id, $code]) {
            $blocks[$id] = $code;
        }
        return $blocks;
    }

    #[DataProvider('blocks')]
    public function testTheBlockRunsAndAnswersAsWritten(string $readme, string $id): void
    {
        if (in_array($id, self::NEEDS_INTL, true) && !extension_loaded('intl')) {
            $this->markTestSkipped("README block $id uses StringComparer::culture(), which needs ext-intl");
        }

        $blocks = self::tested($readme);
        $this->assertArrayHasKey($id, $blocks, "$readme has the tested block $id");
        [$code, $expectations] = self::instrument($blocks[$id]);
        $results = self::execute($code, $readme, $id);

        $this->assertCount(self::BLOCKS[$id], $expectations, "$readme $id: the answers written in the block");
        $this->assertCount(count($expectations), $results, "$readme $id: every answer evaluated");
        foreach ($expectations as $i => [$expected, $line]) {
            $this->assertSame($expected, $results[$i], "$readme $id: $line");
        }
    }

    public function testBothReadmesCarryTheSameBlocks(): void
    {
        $english = self::tested('README.md');
        $portuguese = self::tested('README.pt-BR.md');

        $this->assertSame(array_keys(self::BLOCKS), array_keys($english), 'README.md: the tested blocks, in order');
        $this->assertSame(array_keys(self::BLOCKS), array_keys($portuguese), 'README.pt-BR.md: the tested blocks, in order');
        foreach ($english as $id => $code) {
            $this->assertSame(self::codeTokens($code), self::codeTokens($portuguese[$id]), "block $id: the same code in both languages, comments aside");
            $this->assertSame(array_column(self::instrument($code)[1], 0), array_column(self::instrument($portuguese[$id])[1], 0), "block $id: the same answers in both languages");
        }
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
        foreach ($lines as $n => $line) {
            if (!preg_match('/^(\S.*?);\s*\/\/\s*(.+?)\s*$/', $line, $m)) {
                continue;
            }
            [, $statement, $comment] = $m;
            if (preg_match('/^throws (\w+): (.+)$/', $comment, $t)) {
                $lines[$n] = 'try { ' . $statement . '; $__results[] = "no exception"; } catch (\Throwable $__e) { $__results[] = [(new \ReflectionClass($__e))->getShortName(), $__e->getMessage()]; }';
                $expectations[] = [[$t[1], $t[2]], trim($line)];
                continue;
            }
            if (!preg_match('/^(\[.*\]|\'.*\'|-?\d+(?:\.\d+)?|true|false|null)$/', $comment)) {
                continue; // prose
            }
            $lines[$n] = '$__results[] = ' . $statement . ';';
            $expectations[] = [eval("return $comment;"), trim($line)];
        }

        return [implode("\n", $lines), $expectations];
    }

    /**
     * Runs a block in its own namespace and returns what it recorded.
     *
     * @return list<mixed>
     */
    private static function execute(string $code, string $readme, string $id): array
    {
        $namespace = 'Antevemus\\ALinq\\Tests\\Readme\\Gaps\\' . preg_replace('/\W/', '_', $readme) . '_' . preg_replace('/\W/', '_', $id);
        $__results = [];
        try {
            eval("namespace $namespace;\n" . $code);
        } catch (Throwable $e) {
            throw new \RuntimeException("$readme block $id threw " . $e::class . ': ' . $e->getMessage(), 0, $e);
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
