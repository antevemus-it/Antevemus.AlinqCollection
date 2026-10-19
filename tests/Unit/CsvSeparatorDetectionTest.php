<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Tests\Unit;

use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\ALinqLazyCollection;
use Antevemus\ALinq\Interfaces\IALinqBaseCollection;
use Antevemus\ALinq\Interfaces\IALinqLazyCollection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

/**
 * fromCsv(separator: null) (2.2.0, forward 047, D20-D23): the separator is detected from the
 * first non-blank record, counted outside quotes.
 */
final class CsvSeparatorDetectionTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/alinq-csv-detect-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    private function file(string $content): string
    {
        $path = $this->dir . '/' . bin2hex(random_bytes(4)) . '.csv';
        file_put_contents($path, $content);
        return $path;
    }

    /**
     * @return array<string, array{string, list<list<string|null>>}> [content, records read without header]
     */
    public static function files(): array
    {
        return [
            'comma' => ["a,b,c\n1,2,3\n", [['a', 'b', 'c'], ['1', '2', '3']]],
            'semicolon' => ["a;b;c\n1;2;3\n", [['a', 'b', 'c'], ['1', '2', '3']]],
            'tab' => ["a\tb\tc\n1\t2\t3\n", [['a', 'b', 'c'], ['1', '2', '3']]],
            'pipe' => ["a|b|c\n1|2|3\n", [['a', 'b', 'c'], ['1', '2', '3']]],
            'a comma inside quotes does not count' => ["\"a,b\";c\n1;2\n", [['a,b', 'c'], ['1', '2']]],
            'two commas inside quotes against one semicolon' => ["\"x,y,z\";w\n", [['x,y,z', 'w']]],
            'a quoted record spanning lines' => ["\"line1,\nline2,\";b;c\n1;2;3\n", [["line1,\nline2,", 'b', 'c'], ['1', '2', '3']]],
            'the most frequent wins' => ["a;b;c,d\n", [['a', 'b', 'c,d']]],
            'one field: comma' => ["single\nother\n", [['single'], ['other']]],
            'blank lines before the first record' => ["\n\r\n\na;b\n", [['a', 'b']]],
            'BOM' => ["\xEF\xBB\xBF\"a;x\";b;c\n", [['a;x', 'b', 'c']]],
            'CRLF' => ["a;b\r\n1;2\r\n", [['a', 'b'], ['1', '2']]],
            'escaped quote inside quotes' => ["\"a\\\",b\";c\n", [['a\\",b', 'c']]],
            'doubled quote inside quotes' => ["\"a\"\",b\";c\n", [['a",b', 'c']]],
            'empty file' => ['', []],
            'only blank lines' => ["\n\n\n", []],
        ];
    }

    #[DataProvider('files')]
    public function testTheSeparatorIsDetected(string $content, array $expected): void
    {
        $path = $this->file($content);
        $this->assertSame($expected, ALinqLazyCollection::fromCsv($path, separator: null, hasHeader: false)->toArray());
        $this->assertSame($expected, ALinqCollection::fromCsv($path, null, hasHeader: false)->toArray());
    }

    public function testWithAHeader(): void
    {
        $path = $this->file("date;value\n2026-01-01;1,5\n2026-02-01;2,25\n");
        $this->assertSame(
            [['date' => '2026-01-01', 'value' => '1,5'], ['date' => '2026-02-01', 'value' => '2,25']],
            ALinqLazyCollection::fromCsv($path, separator: null)->toArray()
        );
    }

    public function testAOneFieldHeaderReadsWithCommaAndAWiderRecordStillThrows(): void
    {
        // no candidate in the first record: `,`, and strict mode keeps its answer for the rest
        $path = $this->file("value\n1;2\n3,4\n");
        $this->assertSame([['value' => '1;2']], ALinqLazyCollection::fromCsv($path, separator: null)->take(1)->toArray());
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CSV row 2 has 2 fields, header has 1');
        ALinqLazyCollection::fromCsv($path, separator: null)->toArray();
    }

    public function testATieThrows(): void
    {
        foreach (
            [
                "a,b;c\n" => '"," and ";" both appear 1 times',
                "a,b;c\td|e\n" => '",", ";", "\t" and "|" all appear 1 times',
                "a;b|c|d;e\n" => '";" and "|" both appear 2 times',
            ] as $content => $detail
        ) {
            $path = $this->file($content);
            try {
                ALinqLazyCollection::fromCsv($path, separator: null)->toArray();
                $this->fail('a tie must throw: ' . json_encode($content));
            } catch (RuntimeException $e) {
                $this->assertSame(sprintf('Cannot detect the CSV separator of "%s": %s in the first record; pass $separator', $path, $detail), $e->getMessage());
            }
        }
    }

    public function testAFirstRecordLongerThan64KiBThrows(): void
    {
        $path = $this->file('"' . str_repeat('x', 70000) . "\",y\n");
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf('Cannot detect the CSV separator of "%s": the first record is longer than 65536 bytes; pass $separator', $path));
        ALinqLazyCollection::fromCsv($path, separator: null)->toArray();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function longFirstRecords(): array
    {
        return [
            'one unquoted line' => [str_repeat('x', 70000) . ";y\n"],
            'a quoted record over lines that are each shorter' => ['"' . str_repeat('x', 40000) . "\n" . str_repeat('x', 40000) . "\";y\n"],
        ];
    }

    #[DataProvider('longFirstRecords')]
    public function testEveryLongFirstRecordThrows(string $content): void
    {
        $path = $this->file($content);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('the first record is longer than 65536 bytes');
        ALinqLazyCollection::fromCsv($path, separator: null, hasHeader: false)->toArray();
    }

    public function testTheLastCharacterOfAFileWithoutNewlineCountsOnce(): void
    {
        $path = $this->file('a,b;');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"," and ";" both appear 1 times');
        ALinqLazyCollection::fromCsv($path, separator: null, hasHeader: false)->toArray();
    }

    public function testAFirstRecordOfJust64KiBIsRead(): void
    {
        $line = str_repeat('x', 65536 - 3) . ";y\n";
        $this->assertSame(65536, strlen($line));
        $this->assertSame([[str_repeat('x', 65533), 'y']], ALinqLazyCollection::fromCsv($this->file($line), separator: null, hasHeader: false)->toArray());
    }

    public function testDetectionRunsOnEveryTraversalAndIsDeferred(): void
    {
        $path = $this->file("a;b\n");
        $stream = ALinqLazyCollection::fromCsv($path, separator: null, hasHeader: false);
        $this->assertSame([['a', 'b']], $stream->toArray());
        file_put_contents($path, "a|b|c\n");
        $this->assertSame([['a', 'b', 'c']], $stream->toArray());

        // nothing is opened before the traversal
        $missing = ALinqLazyCollection::fromCsv($this->dir . '/missing.csv', separator: null);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf('Unable to open CSV file for streaming: "%s"', $this->dir . '/missing.csv'));
        $missing->toArray();
    }

    public function testAStreamThatCannotSeekThrows(): void
    {
        $wrapper = new class {
            /** @var resource|null */
            public $context;
            public static string $data = '';
            private int $position = 0;

            public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
            {
                $this->position = 0;
                return true;
            }

            public function stream_read(int $count): string
            {
                $chunk = substr(self::$data, $this->position, $count);
                $this->position += strlen($chunk);
                return $chunk;
            }

            public function stream_eof(): bool
            {
                return $this->position >= strlen(self::$data);
            }
        };
        $class = get_class($wrapper);
        $this->assertTrue(stream_wrapper_register('alinqnoseekdetect', $class));

        try {
            $class::$data = "a;b\n1;2\n";
            // with an explicit separator the non-seekable stream is read as before
            $this->assertSame([['a' => '1', 'b' => '2']], ALinqLazyCollection::fromCsv('alinqnoseekdetect://x', ';')->toArray());
            try {
                ALinqLazyCollection::fromCsv('alinqnoseekdetect://x', separator: null)->toArray();
                $this->fail('detection on a stream that cannot seek must throw');
            } catch (RuntimeException $e) {
                $this->assertSame('Cannot detect the CSV separator on a stream that cannot seek: "alinqnoseekdetect://x"; pass $separator', $e->getMessage());
            }
        } finally {
            stream_wrapper_unregister('alinqnoseekdetect');
        }
    }

    public function testTheDefaultSeparatorIsStillAComma(): void
    {
        $path = $this->file("a;b\n1;2\n");
        $this->assertSame([['a;b' => '1;2']], ALinqLazyCollection::fromCsv($path)->toArray());

        foreach ([[IALinqBaseCollection::class, 'fromCsv'], [IALinqLazyCollection::class, 'fromCsv'], [ALinqCollection::class, 'fromCsv'], [ALinqLazyCollection::class, 'fromCsv']] as [$class, $method]) {
            $separator = (new ReflectionMethod($class, $method))->getParameters()[1];
            $this->assertSame('?string', (string) $separator->getType(), $class);
            $this->assertSame(',', $separator->getDefaultValue(), $class);
        }
    }
}
