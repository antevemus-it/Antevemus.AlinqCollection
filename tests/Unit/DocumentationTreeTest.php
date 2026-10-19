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
 * except the blocks declared localized, which are pinned instead; (e) the number of PHP blocks of each page
 * is the one declared below, with its cause; (f) each README has exactly one PHP block, the Quick Start of
 * getting-started/index, and stays below 25 000 bytes; (g) the Quick Start is marked as a tested block in the
 * README and on the page of each language, so ReadmeConsumerGapsTest runs it with its written answers;
 * (h) nothing of the internal line leaks to a public page or README: no internal path, no Azure DevOps link,
 * and no relative link outside the language folders but to the public root files.
 *
 * @version    2.2.1
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
        'api/joining' => 3,                     // §3, the outer joins (1.4.0) and 3-keys (2.2.0)
        'api/aggregations' => 1,                // §4
        'api/projections' => 2,                 // §5, and 5-dictionary (2.2.0)
        'api/grouping' => 2,                    // §6, and 6-lookup (2.2.0)
        'api/ordering' => 3,                    // §7, 7-ordinal and 7-culture (2.2.0)
        'api/query-builder' => 2,               // §8, and its shortcuts (1.4.0)
        'api/property-access' => 2,             // §9: paths, and the resolution order with #[Specifiable]
        'api/lazy-streaming' => 9,              // §10: log file, CSV, PDO cursor, paginated HTTP (1.5.0), remember(),
                                                // ordering and outer joins (1.4.0), 10-grouping (2.2.0),
                                                // irregular input (1.3.2), 10-csv (2.2.0)
    ];

    /** The 29 blocks of the 2.2.0 README; §11 (api/contract) has none. */
    private const PHP_BLOCKS_TOTAL = 29;

    /**
     * The blocks whose Portuguese version has, since they were written, its own identifiers and strings
     * (`$pedidos` instead of `$orders`, `'PENDENTE'` instead of `'PENDING'`, `NotaFiscal` instead of
     * `Invoice`). They were never compared between the languages, and 2.2.1 moves code without changing it,
     * so these 20 of the 29 blocks are declared here instead of equal: each must still differ between the
     * languages (aligning one removes it from this list), and the tokens of each language are pinned (sha1 of
     * codeTokens() joined by "\n"), so a change to one of them without the other fails here until it is
     * declared again. The other 9 (the Quick Start, the paginated HTTP example and the 7 tested blocks of
     * 2.2.0) are equal token by token. Key: "<page>#<block index>"; value: [EN, pt-BR].
     */
    private const LOCALIZED_BLOCKS = [
        'concepts/aspecification-synergy#0'    => ['ef25e212e2d55780cb466dc2133219765e33aa4b', 'c6642f696b40d0ade6e1a5639132ea9a9bed926d'], // How It Works: $isEligibleCustomer / $clienteElegivel
        'api/aggregations#0'                   => ['0f17974bfaf9a956e444653c7cf8b30f7ee01562', '3c4180b3034913028b0ab1a300d3bd0ec9d418eb'], // §4: $orders / $pedidos
        'api/factories#0'                      => ['ca2cd07563acc31c0019a97d516b889fd1e0f223', 'e7ad843e26417871df47d7a5350229002ec4a82a'], // §1: $numbers / $numeros
        'api/filtering#0'                      => ['d7d3c043f5a10bef373556f7b1622cb3254c3c8c', '1b2573310cb06308e5baea8aeb829b279417ab61'], // §2: $collection / $colecao
        'api/filtering#1'                      => ['6547d25d70c9d870522788c481bb90d6a730fbfe', '23ae9377eaf08e54adc09f74b476751d7aaec761'], // §2: $staff / $equipe
        'api/grouping#0'                       => ['c5e6813a823d7aaf2df56240991562deab98e800', '3f9c234f65c0d4b2b65e0b0d971ec0c84da47547'], // §6: $transactions / $transacoes
        'api/joining#0'                        => ['62c3ef3c8a8d9aada077a4a5f1d239e3d3f86435', '4cb7d7111da7843fb42dbea456420d5cbac93ada'], // §3: $departments / $departamentos
        'api/joining#1'                        => ['6d5a389aed329950500d188373dabf5f1f859336', '1229e1d39674869d69af57598b65d5beeb634edf'], // §3: $orders / $pedidos
        'api/lazy-streaming#0'                 => ['bd7050cef3f562833a25018102293604e8c6d560', '5c35a0f344bc7388c8fe7f700f324dda5f7e60e0'], // §10: $highSeverityAlerts / $alertasCriticos
        'api/lazy-streaming#1'                 => ['4ffbcd88d8b4ed96830799536a39430287a02da7', '32fbad0fddb6ddd48fb554b90a42ad6d1db8ee7c'], // §10: $activeEnterpriseUsers / $clientesCorporativosAtivos
        'api/lazy-streaming#2'                 => ['f6b3a9c27fda0ed7dd638de829b1e9f9da3638da', 'f3125028058643e20c77af3730469f2d9e9c6f76'], // §10: 'SELECT id, name, salary, department FROM employees' / 'SELECT id, nome, salario, departamento FROM funcionarios'
        'api/lazy-streaming#4'                 => ['931e1dfdce003bb661255ad6a84b856e6f099650', '0341ef1186df1fca309a2049a32807e22d641d4e'], // §10: $cachedStream / $streamComCache
        'api/lazy-streaming#5'                 => ['d2ad27953e23eb3ca37a8ad89cd474e2fafb2da1', '4ba9a5320e78181d9113bd0ea51431fd823f7104'], // §10: $sorted / $ordenados
        'api/lazy-streaming#7'                 => ['8dc968bfdd89ccfca1340ba4bd0cba3f2bb4997b', '03923e7d56767fb31c30d28e23ca8f3d187accbc'], // §10: 'clients.csv' / 'clientes.csv'
        'api/ordering#0'                       => ['32e73375b9d13f06988001bdffc6ce1b1fccbf82', 'ed43a1d2c61e77fdf8ab94791b7560f4ee685116'], // §7: $files / $arquivos
        'api/projections#0'                    => ['f5bac4d551d5c233362d45e9b200736b13f7bdc8', '9993aca94709255a16693a1c9a445022c7b6757a'], // §5: $catalog / $catalogo
        'api/property-access#0'                => ['a89734a5fd2f1cf087a30d7a97009a6c07be9ec8', '572fad55e62039722151b8c9689720455fdf4294'], // §9: 'user' / 'usuario'
        'api/property-access#1'                => ['0c95d8f1994e00d2e9083680f92435863f62dcd7', '5fc49e8308da4455192ee05ba521d4d9f28bd039'], // §9: Invoice / NotaFiscal
        'api/query-builder#0'                  => ['0e0df06e56666ed65ee82e701b87afb89018b187', '8533d3d75cb212baf9681072235771197cd18ed9'], // §8: 'APPROVED' / 'APROVADO'
        'api/query-builder#1'                  => ['a878f2f06037988d5f6a4700e40265aeb9ffc88b', 'b5d0fa3d843e468f0892fca0a4b9e53cc0d43aab'], // §8: $applicants / $candidatos
    ];

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
        self::assertSame($declared, $localized, 'every declared localized block exists (20 of the 29 blocks)');
    }

    // (e) -----------------------------------------------------------------------------------------------

    public function testEveryPageHasTheDeclaredNumberOfPhpBlocks(): void
    {
        self::assertSame(self::PHP_BLOCKS_TOTAL, array_sum(self::PHP_BLOCKS), 'the table adds up to the 29 blocks of 2.2.0');
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
