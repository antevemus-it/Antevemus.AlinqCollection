<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * §9 "Deep Dot-Notation Property Access" (EN and pt-BR): the code blocks are the test.
 *
 * Each PHP block of the section is read from its page (docs/<language>/api/property-access.md since
 * 2.2.1; the README until 2.2.0) and executed. A statement followed by a comment that starts with a
 * literal (`// 99.9 (public getter)`, `// null`, `// 0`) asserts that the statement evaluates to that
 * literal; a `// Returns: <literal>` (`// Retorna:`) line asserts the variable assigned on the line
 * before. A page edited without its answers fails here.
 *
 * @version    2.2.1
 * @package    Antevemus\ALinq\Tests
 * @subpackage Unit
 */
final class ReadmePropertyAccessTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function readmes(): array
    {
        return [
            'docs/en/api/property-access.md' => ['docs/en/api/property-access.md'],
            'docs/pt-BR/api/property-access.md' => ['docs/pt-BR/api/property-access.md'],
        ];
    }

    #[DataProvider('readmes')]
    public function testSection9BlocksRunAndAnswerAsWritten(string $readme): void
    {
        $text = (string) file_get_contents(dirname(__DIR__, 2) . '/' . $readme);
        $start = strpos($text, "\n### 9. ");
        $this->assertNotFalse($start, "$readme has §9");
        // §9 runs to the next `## ` heading or to the end of the page
        $end = strpos($text, "\n## ", $start + 1);
        $section = $end === false ? substr($text, $start) : substr($text, $start, $end - $start);

        preg_match_all('/```php\n(.*?)```/s', $section, $blocks);
        $this->assertCount(2, $blocks[1], "$readme §9 has two PHP blocks");

        $checked = 0;
        foreach ($blocks[1] as $index => $block) {
            [$code, $expectations] = self::instrument($block);
            $results = self::execute($code, $readme, $index);
            $this->assertCount(count($expectations), $results, "$readme §9 block $index: every expectation evaluated");
            foreach ($expectations as $i => [$literal, $line]) {
                $this->assertSame(eval("return $literal;"), $results[$i], "$readme §9: $line");
                $checked++;
            }
        }

        $this->assertSame(8, $checked, "$readme §9: 1 + 7 answers written in the blocks");
    }

    /**
     * Rewrite a README block so that every commented answer is recorded in $__results.
     *
     * @return array{string, list<array{string, string}>} [code, [[literal, original line], ...]]
     */
    private static function instrument(string $block): array
    {
        $literal = "('[^']*'|true|false|null|-?\\d+(?:\\.\\d+)?)";
        $lines = explode("\n", $block);
        $expectations = [];
        $lastAssigned = null;
        foreach ($lines as $n => $line) {
            if (preg_match('/^\s*\/\/\s*(?:Returns|Retorna):\s*' . $literal . '/', $line, $m) && $lastAssigned !== null) {
                $lines[$n] = '$__results[] = ' . $lastAssigned . ';';
                $expectations[] = [$m[1], trim($line)];
                continue;
            }
            if (preg_match('/^(\$\w+)\s*=/', $line, $m)) {
                $lastAssigned = $m[1];
            }
            if (preg_match('/^(\S.*?);\s*\/\/\s*' . $literal . '(?:\s|$)/', $line, $m)) {
                $lines[$n] = '$__results[] = ' . $m[1] . ';';
                $expectations[] = [$m[2], trim($line)];
            }
        }

        return [implode("\n", $lines), $expectations];
    }

    /**
     * Run the code of a block in its own namespace (so that its classes do not collide with those of
     * the other language) and return what it recorded.
     *
     * @return list<mixed>
     */
    private static function execute(string $code, string $readme, int $index): array
    {
        $namespace = 'Antevemus\\ALinq\\Tests\\Readme\\S9\\' . preg_replace('/\W/', '_', $readme) . '_' . $index;
        $__results = [];
        eval("namespace $namespace;\n" . $code);
        return $__results;
    }
}
