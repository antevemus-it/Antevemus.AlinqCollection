<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClassConstant;

/**
 * DocumentationTreeTest - guard of the official documentation (v2.2.1).
 *
 * Since 2.2.1 the README is an index and the documentation lives in docs/en and docs/pt-BR, one page per
 * topic, in the Docs Kit format (a docs-nav.json per language). This class keeps the two trees aligned:
 * (a) the same files in both languages; (b) both navigations parse, have the same shape, point to existing
 * pages and list every page; (c) every relative link of a page or of a README resolves; (d) every page
 * carries the same code fences and the same PHP blocks in both languages, token by token (comments aside),
 * all 29 of them since 2.2.2 and 33 since 2.4.0 (no block is declared localized); (e) the number of PHP blocks of each page
 * is the one declared below, with its cause; (f) each README has exactly one PHP block, the Quick Start of
 * getting-started/index, and stays below 25 000 bytes; (g) the Quick Start is marked as a tested block in the
 * README and on the page of each language, so ReadmeConsumerGapsTest runs it with its written answers;
 * (h) nothing of the internal line leaks to a public page or README: no internal path, no Azure DevOps link,
 * and no relative link outside the language folders but to the public root files.
 *
 * @version    2.4.0
 * @package    Antevemus\ALinq\Tests
 * @subpackage Unit
 */
final class DocumentationTreeTest extends TestCase
{
    private const LANGUAGES = ['en', 'pt-BR'];

    /** The README of each language. */
    private const READMES = ['en' => 'README.md', 'pt-BR' => 'README.pt-BR.md'];

    /** A README above this size is no longer an index (it was 72 307 and 75 250 bytes in 2.2.0). */
    private const README_MAX_BYTES = 25000;

    /**
     * PHP blocks per page, the same in both languages; a page not listed has none. The 29 blocks of the
     * 2.2.0 README, moved page by page (the Quick Start included: it is on its page and in the README).
     */
    private const PHP_BLOCKS = [
        'getting-started/index' => 1,           // the Quick Start, also the only block of each README
        'concepts/aspecification-synergy' => 1, // "How It Works": ALinqBridge over a specification
        'api/factories' => 1,                   // §1
        'api/filtering' => 2,                   // §2, and its field filters (1.4.0)
        'api/joining' => 4,                     // §3, the outer joins (1.4.0), 3-keys (2.2.0) and 3-comparer (2.4.0)
        'api/aggregations' => 1,                // §4
        'api/projections' => 2,                 // §5, and 5-dictionary (2.2.0)
        'api/grouping' => 3,                    // §6, 6-lookup (2.2.0) and 6-comparer (2.4.0)
        'api/ordering' => 3,                    // §7, 7-ordinal and 7-culture (2.2.0)
        'api/query-builder' => 2,               // §8, and its shortcuts (1.4.0)
        'api/property-access' => 2,             // §9: paths, and the resolution order with #[Specifiable]
        'api/lazy-streaming' => 11,             // §10: log file, CSV, PDO cursor, paginated HTTP (1.5.0), pages by
                                                // index (2.4.0), remember(), ordering and outer joins (1.4.0),
                                                // 10-grouping (2.2.0), 10-joins (2.4.0), irregular input (1.3.2),
                                                // 10-csv (2.2.0)
    ];

    /**
     * The 29 blocks of the 2.2.0 README and the 4 of 2.4.0 (3-comparer, 6-comparer, the pages by index of
     * fromHttpPagesByIndex(), 10-joins); §11 (api/contract) has none.
     */
    private const PHP_BLOCKS_TOTAL = 33;

    /**
     * The blocks allowed to differ between the languages, none since 2.2.2. In 2.2.1 the 20 blocks whose
     * Portuguese version had its own identifiers and strings since it was written (`$pedidos` for `$orders`,
     * `'PENDENTE'` for `'PENDING'`, `NotaFiscal` for `Invoice`) were declared here, the tokens of each
     * language pinned; 2.2.2 rewrote the Portuguese code of the 20 with the English tokens (the comments stay
     * in Portuguese), so every one of the 29 blocks is now equal token by token. A block declared here must
     * differ between the languages and carry the sha1 of codeTokens() joined by "\n" of each language.
     * Key: "<page>#<block index>"; value: [EN, pt-BR].
     *
     * @var array<string, array{string, string}>
     */
    private const LOCALIZED_BLOCKS = [];

    /**
     * Material that lives only on the internal line (the internal docs/ files and the Reversa folders) and
     * must never reach a public page; compared ignoring case.
     */
    private const INTERNAL = ['_reversa', '.reversa', 'docs/RELEASING', 'docs/REVISAO', 'REVISAO-20'];

    /** The files of the repository root a page or a README may link to, besides the language folders. */
    private const PUBLIC_ROOT_FILES = ['README.md', 'README.pt-BR.md', 'CHANGELOG.md', 'ROADMAP.md', 'UPGRADE-2.0.md', 'UPGRADE-2.0.pt-BR.md', 'LICENSE', 'NOTICE.md', 'THIRD_PARTY_NOTICES.md'];

    // (a) -----------------------------------------------------------------------------------------------

    public function testBothLanguagesHaveTheSameFiles(): void
    {
        $english = self::files('en');
        $portuguese = self::files('pt-BR');
        self::assertContains('docs-nav.json', $english, 'docs/en/docs-nav.json');
        self::assertContains('index.md', $english, 'docs/en/index.md');
        self::assertSame($english, $portuguese, 'docs/en and docs/pt-BR have the same set of files');
    }

    // (b) -----------------------------------------------------------------------------------------------

    public function testNavigationsAreAlignedAndListEveryPage(): void
    {
        $shapes = [];
        foreach (self::LANGUAGES as $language) {
            $nav = json_decode((string) file_get_contents(self::root() . "/docs/{$language}/docs-nav.json"), true);
            self::assertIsArray($nav, "docs/{$language}/docs-nav.json parses");
            self::assertIsArray($nav['items'] ?? null, "docs/{$language}/docs-nav.json has items");
            self::assertSame(['text' => $nav['items'][0]['text'] ?? null, 'path' => 'index'], $nav['items'][0], "docs/{$language}: the first item is the index");
            $shape = self::shape($nav['items'], $language);
            $paths = array_values(array_filter(array_map(static fn(string $entry): ?string => str_starts_with($entry, 'path:') ? substr($entry, 5) : null, $shape)));
            self::assertSame(count($paths), count(array_unique($paths)), "docs/{$language}: no page twice in the navigation");
            foreach ($paths as $path) {
                self::assertFileExists(self::root() . "/docs/{$language}/{$path}.md", "docs/{$language}/docs-nav.json: {$path}");
            }
            $pages = self::pages($language);
            sort($paths);
            sort($pages);
            self::assertSame($pages, $paths, "docs/{$language}: every page is in the navigation, and only pages");
            $shapes[$language] = $shape;
        }
        self::assertSame($shapes['en'], $shapes['pt-BR'], 'both navigations have the same shape: same paths in the same order, groups aligned');
    }

    // (c) -----------------------------------------------------------------------------------------------

    public function testEveryRelativeLinkResolves(): void
    {
        $checked = 0;
        foreach (self::LANGUAGES as $language) {
            $base = self::root() . "/docs/{$language}";
            foreach (self::pages($language) as $page) {
                $file = "{$base}/{$page}.md";
                foreach (self::relativeLinks((string) file_get_contents($file)) as $target) {
                    self::assertNotFalse(realpath(dirname($file) . '/' . $target), "docs/{$language}/{$page}.md: the link {$target} resolves");
                    $checked++;
                }
            }
            foreach (self::relativeLinks((string) file_get_contents(self::root() . '/' . self::READMES[$language])) as $target) {
                self::assertFileExists(self::root() . '/' . $target, self::READMES[$language] . ": the link {$target} resolves");
                $checked++;
            }
        }
        self::assertGreaterThan(0, $checked);
    }

    // (d) -----------------------------------------------------------------------------------------------

    public function testEveryPageHasTheSameCodeInBothLanguages(): void
    {
        $localized = [];
        foreach (self::pages('en') as $page) {
            $english = (string) file_get_contents(self::root() . "/docs/en/{$page}.md");
            $portuguese = (string) file_get_contents(self::root() . "/docs/pt-BR/{$page}.md");
            self::assertSame(self::fences($english), self::fences($portuguese), "{$page}: the same code fences in the same order");
            $englishBlocks = self::phpBlocks($english);
            $portugueseBlocks = self::phpBlocks($portuguese);
            self::assertCount(count($englishBlocks), $portugueseBlocks, "{$page}: the same number of PHP blocks");
            foreach ($englishBlocks as $i => $block) {
                $englishTokens = self::codeTokens($block);
                $portugueseTokens = self::codeTokens($portugueseBlocks[$i] ?? '');
                $pinned = self::LOCALIZED_BLOCKS["{$page}#{$i}"] ?? null;
                if ($pinned === null) {
                    self::assertSame($englishTokens, $portugueseTokens, "{$page}: PHP block {$i} identical except comments");
                    continue;
                }
                self::assertNotSame($englishTokens, $portugueseTokens, "{$page}: PHP block {$i} is equal in both languages now, remove it from LOCALIZED_BLOCKS");
                self::assertSame($pinned, [sha1(implode("\n", $englishTokens)), sha1(implode("\n", $portugueseTokens))], "{$page}: PHP block {$i}, localized, changed in one language; align it or declare it again");
                $localized[] = "{$page}#{$i}";
            }
        }
        $declared = array_keys(self::LOCALIZED_BLOCKS);
        sort($declared);
        sort($localized);
        self::assertSame($declared, $localized, 'every declared localized block exists (none since 2.2.2: the 29 blocks are equal)');
    }

    // (e) -----------------------------------------------------------------------------------------------

    public function testEveryPageHasTheDeclaredNumberOfPhpBlocks(): void
    {
        self::assertSame(self::PHP_BLOCKS_TOTAL, array_sum(self::PHP_BLOCKS), 'the table adds up to the 29 blocks of 2.2.0 and the 4 of 2.4.0');
        foreach (self::LANGUAGES as $language) {
            $total = 0;
            foreach (self::pages($language) as $page) {
                $count = count(self::phpBlocks((string) file_get_contents(self::root() . "/docs/{$language}/{$page}.md")));
                self::assertSame(self::PHP_BLOCKS[$page] ?? 0, $count, "docs/{$language}/{$page}.md: PHP blocks");
                $total += $count;
            }
            self::assertSame(self::PHP_BLOCKS_TOTAL, $total, "docs/{$language}: PHP blocks in all pages");
        }
    }

    // (f) -----------------------------------------------------------------------------------------------

    public function testEachReadmeIsAnIndexWhoseOnlyCodeIsTheQuickStart(): void
    {
        foreach (self::READMES as $language => $readme) {
            $text = (string) file_get_contents(self::root() . '/' . $readme);
            self::assertLessThan(self::README_MAX_BYTES, strlen($text), "{$readme}: below " . self::README_MAX_BYTES . ' bytes');
            self::assertSame(['php'], self::fences($text), "{$readme}: one code block, PHP");
            $blocks = self::phpBlocks($text);
            self::assertCount(1, $blocks, "{$readme}: exactly one PHP block");
            $page = self::phpBlocks((string) file_get_contents(self::root() . "/docs/{$language}/getting-started/index.md"));
            self::assertSame($page[0] ?? null, $blocks[0] ?? null, "{$readme}: the block is the Quick Start of docs/{$language}/getting-started/index.md, byte by byte");
        }
    }

    // (g) -----------------------------------------------------------------------------------------------

    public function testTheQuickStartIsATestedBlock(): void
    {
        $answers = (new ReflectionClassConstant(ReadmeConsumerGapsTest::class, 'BLOCKS'))->getValue();
        self::assertGreaterThan(0, $answers['quick-start'] ?? 0, 'ReadmeConsumerGapsTest counts the answers of the Quick Start');
        $cases = ReadmeConsumerGapsTest::blocks();
        foreach (self::READMES as $language => $readme) {
            foreach ([$readme, "docs/{$language}/getting-started/index.md"] as $file) {
                $text = (string) file_get_contents(self::root() . '/' . $file);
                self::assertSame(1, preg_match_all('/<!-- tested: quick-start -->\n```php\n/', $text), "{$file}: the Quick Start carries the marker");
                self::assertContains([$file, 'quick-start'], $cases, "ReadmeConsumerGapsTest runs the Quick Start of {$file}");
            }
        }
    }

    // (h) -----------------------------------------------------------------------------------------------

    public function testNothingInternalLeaksToThePublicLine(): void
    {
        $files = [];
        foreach (self::LANGUAGES as $language) {
            foreach (self::files($language) as $file) {
                $files["docs/{$language}/{$file}"] = ["docs/{$language}"];
            }
        }
        foreach (self::READMES as $readme) {
            $files[$readme] = ['docs/en', 'docs/pt-BR'];
        }
        $root = (string) realpath(self::root());
        foreach ($files as $file => $folders) {
            $text = (string) file_get_contents(self::root() . '/' . $file);
            foreach (self::INTERNAL as $internal) {
                self::assertStringNotContainsStringIgnoringCase($internal, $text, "{$file}: nothing of the internal material ({$internal})");
            }
            self::assertDoesNotMatchRegularExpression('#dev\.azure\.com|visualstudio\.com#i', $text, "{$file}: no link to Azure DevOps");
            foreach (self::relativeLinks($text) as $target) {
                $resolved = (string) realpath(dirname(self::root() . '/' . $file) . '/' . $target);
                $relative = str_starts_with($resolved, $root . '/') ? substr($resolved, strlen($root) + 1) : $resolved;
                $inside = array_filter($folders, static fn(string $folder): bool => str_starts_with($relative, $folder . '/'));
                self::assertTrue($inside !== [] || in_array($relative, self::PUBLIC_ROOT_FILES, true), "{$file}: the link {$target} points to a public file ({$relative})");
            }
        }
    }

    // ---------------------------------------------------------------------------------------------------

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * The files of docs/<language>, relative to it and sorted.
     *
     * @return list<string>
     */
    private static function files(string $language): array
    {
        $base = self::root() . "/docs/{$language}";
        self::assertDirectoryExists($base);
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $files[] = substr($file->getPathname(), strlen($base) + 1);
        }
        sort($files);

        return $files;
    }

    /**
     * The pages of docs/<language>, without the extension.
     *
     * @return list<string>
     */
    private static function pages(string $language): array
    {
        return array_map(static fn(string $file): string => substr($file, 0, -3), array_values(array_filter(self::files($language), static fn(string $file): bool => str_ends_with($file, '.md'))));
    }

    /**
     * The shape of a navigation without its texts: one entry per item, "path:<path>" or "group:<folder>"
     * followed by its items and "end".
     *
     * @param  list<array<string, mixed>> $items
     * @return list<string>
     */
    private static function shape(array $items, string $language): array
    {
        $shape = [];
        foreach ($items as $item) {
            self::assertIsString($item['text'] ?? null, "docs/{$language}/docs-nav.json: every item has a text");
            self::assertNotSame('', trim($item['text']), "docs/{$language}/docs-nav.json: the text is not empty");
            if (isset($item['items'])) {
                self::assertArrayNotHasKey('path', $item, "docs/{$language}/docs-nav.json: a group has no path");
                $inner = self::shape($item['items'], $language);
                $folders = array_unique(array_map(static fn(string $entry): string => explode('/', substr($entry, 5))[0], array_filter($inner, static fn(string $entry): bool => str_starts_with($entry, 'path:'))));
                self::assertCount(1, $folders, "docs/{$language}/docs-nav.json: a group holds the pages of one folder ({$item['text']})");
                $shape = [...$shape, 'group:' . reset($folders), ...$inner, 'end'];
            } else {
                self::assertIsString($item['path'] ?? null, "docs/{$language}/docs-nav.json: an item has a path or items");
                self::assertDoesNotMatchRegularExpression('/\.md$|^\//', $item['path'], "docs/{$language}/docs-nav.json: a path has no extension and is relative");
                $shape[] = 'path:' . $item['path'];
            }
        }

        return $shape;
    }

    /**
     * The relative links of a Markdown text outside code fences, without their anchors.
     *
     * @return list<string>
     */
    private static function relativeLinks(string $text): array
    {
        $links = [];
        foreach (self::prose($text) as $line) {
            preg_match_all('/\]\(([^)\s]+)\)/', $line, $matches);
            foreach ($matches[1] as $target) {
                if (preg_match('#^(?:[a-z][a-z0-9+.-]*:|\#)#i', $target) === 1) {
                    continue;
                }
                $links[] = explode('#', $target)[0];
            }
        }

        return $links;
    }

    /**
     * The lines of a Markdown text outside code fences.
     *
     * @return list<string>
     */
    private static function prose(string $text): array
    {
        $lines = [];
        $fence = false;
        foreach (explode("\n", $text) as $line) {
            if (str_starts_with($line, '```')) {
                $fence = !$fence;
                continue;
            }
            if (!$fence) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * The info string of every code fence that opens a block, in order.
     *
     * @return list<string>
     */
    private static function fences(string $text): array
    {
        preg_match_all('/^```(\S*)\n.*?^```$/ms', $text, $matches);

        return $matches[1];
    }

    /**
     * The ```php blocks of a text, read as ReadmeConsumerGapsTest reads them.
     *
     * @return list<string>
     */
    private static function phpBlocks(string $text): array
    {
        preg_match_all('/```php\n(.*?)```/s', $text, $matches);

        return $matches[1];
    }

    /**
     * The code tokens of a block, without comments and whitespace (as ReadmeConsumerGapsTest compares them).
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
