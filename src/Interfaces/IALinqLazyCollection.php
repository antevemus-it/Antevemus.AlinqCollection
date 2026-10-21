<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Interfaces;

use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\ALinqLookup;
use Antevemus\ALinq\DuplicateKeyPolicy;
use Antevemus\ALinq\Http\HttpPageOptions;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use PDOStatement;
use stdClass;
use Traversable;

/**
 * IALinqLazyCollection
 *
 * Interface for generator-based lazy streaming collection operations with deferred
 * execution and constant O(1) memory overhead.
 *
 * Contract (1.3.0), the same as the eager ALinqCollection: a source known to be a list
 * (array list, file, CSV, cursor, range, repeat) streams reindexed keys through filtering
 * operators and a dictionary streams its original keys; materialization never loses an
 * item; set operators use the strict, type-aware identity of `ALinqCallable::hashKey()`;
 * callbacks receive `($item, $key)` only when they accept two parameters; an empty stream
 * throws on first/last/min/max/minBy/maxBy/average; numeric aggregations skip `null`;
 * a comparer may answer `bool` or `<=>`.
 *
 * Since 1.4.0 (forward 021) the lazy side also offers single(), whereIn()/whereNotIn()/
 * whereBetween(), the outer joins leftJoin()/rightJoin()/fullJoin() and the composite
 * ordering orderBy()/orderByDescending() + thenBy()/thenByDescending(), with the results and
 * exceptions of the eager side. Ordering and joins buffer: orderBy() consumes the stream when
 * it is traversed; a join indexes one side (the inner side for leftJoin()/fullJoin(), this
 * stream for rightJoin()) and streams the other.
 *
 * Since 1.5.0 (forward 027) fromHttpPages() is a lazy source over a paginated HTTP API with
 * prefetch of the next page (lazy side only, like fromFile()).
 *
 * Since 2.1.0 (forward 036): minOrDefault(), maxOrDefault(), averageOrDefault(),
 * minByOrDefault() and maxByOrDefault(), with the answers of the eager side, in one
 * traversal (safe on a single-pass source such as fromCursor()). Classes that implement this
 * interface on their own must add them.
 *
 * Since 2.2.0 (forward 047): the deferred stages groupBy(), countBy() and aggregateBy() (they
 * accumulate: groupBy() every item, countBy()/aggregateBy() one value per key), the terminals
 * toDictionary() (with a DuplicateKeyPolicy), toLookup() and implode(), the stages
 * exceptByKeys()/intersectByKeys(), a comparer on orderBy*(), distinct*(), min*() and max*(),
 * and `fromCsv(separator: null)`. Classes that implement this interface on their own must add
 * them and widen fromCsv()'s separator.
 *
 * Since 2.3.0 (forward 052): from() takes `?bool $isList = null`, the declared shape of a Closure
 * factory's stream, and isList(): ?bool answers the shape the collection knows. Classes that
 * implement this interface on their own must add the parameter and the method.
 *
 * Since 2.4.0: join() and groupJoin() (the inner side indexed on traversal, this stream
 * streamed, the answers of the eager side); an optional IALinqEqualityComparer as the last
 * parameter of the joins, of groupBy(), countBy(), aggregateBy(), toDictionary(), toLookup(),
 * exceptByKeys() and intersectByKeys() (hash-based; the first key seen names a group or is the
 * one kept by a set); a seed selector `fn($key)` in aggregateBy(); and fromHttpPagesByIndex(),
 * paged by index with several pages in flight. Classes that implement this interface on their
 * own must add them.
 *
 * @version    2.4.0
 * @package    antevemus
 * @subpackage alinq.interfaces
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
interface IALinqLazyCollection extends Countable, IteratorAggregate, JsonSerializable
{
    /**
     * Create a lazy collection from any iterable or generator factory closure.
     *
     * `$isList` (since 2.3.0) declares the shape of the stream of a Closure factory: `true` a
     * list (renumbered while streaming, toArray() a list), `false` a dictionary (keys kept, an
     * integer-keyed dictionary included), `null` unknown (keys as yielded, toArray() a list when
     * every key is an integer). Given with any source but a Closure it throws
     * InvalidArgumentException: the shape of an iterable comes from the iterable.
     *
     * @param iterable|callable $source
     * @param bool|null $isList
     * @return self
     */
    public static function from(iterable|callable $source, ?bool $isList = null): self;

    /**
     * The shape of the stream as this collection knows it (since 2.3.0): true, a list (the
     * renumbering operators renumber and toArray() returns a list); false, a dictionary (keys
     * kept); null, unknown (a generator-factory source without a declaration, a Traversable, a
     * Generator). An array source answers array_is_list(); fromFile(), fromCsv(), fromCursor(),
     * fromHttpPages(), range(), repeat() and empty() are lists; each operator answers the shape it
     * produces (where(), select(), take(), orderBy() keep their source's; selectMany(), concat(),
     * zip(), chunk() are lists; groupBy(), countBy(), aggregateBy() are dictionaries). Not the eager
     * isList(), which inspects the items it holds and is never unknown.
     *
     * @return bool|null
     */
    public function isList(): ?bool;

    /**
     * Create a lazy stream that reads a file line-by-line using constant O(1) memory.
     *
     * A UTF-8 BOM at the start of the file is removed from the first line. A directory or a
     * file that cannot be opened throws RuntimeException on the first traversal.
     *
     * @param string $filePath
     * @param int $bufferSize
     * @param callable|null $lineParser
     * @return self
     */
    public static function fromFile(string $filePath, int $bufferSize = 4096, ?callable $lineParser = null): self;

    /**
     * Create a lazy stream that reads a CSV file row-by-row.
     *
     * Irregular input: a blank line is skipped; a UTF-8 BOM at the start of the file is
     * removed before parsing (on a stream that cannot seek, from the first field of the first
     * record); a directory or a file that cannot be opened throws RuntimeException on the
     * first traversal.
     *
     * With `$separator = null` (since 2.2.0) the separator is detected on every traversal from
     * the first non-blank record (`,` `;` tab `|`, counted outside quotes; the most frequent
     * wins, a tie throws RuntimeException, none is `,`); a stream that cannot seek throws.
     *
     * @param string $filePath
     * @param string|null $separator
     * @param string $enclosure
     * @param string $escape
     * @param bool $hasHeader If true, yields associative arrays keyed by header columns.
     * @param bool $strict With a header, a record whose field count differs from the header
     *                     throws RuntimeException('CSV row N has K fields, header has H') when
     *                     it is read (true, the default); false hands it over as a positional
     *                     list (the 1.3.1 behaviour, for dirty files).
     * @return self
     */
    public static function fromCsv(
        string $filePath,
        ?string $separator = ',',
        string $enclosure = '"',
        string $escape = '\\',
        bool $hasHeader = true,
        bool $strict = true
    ): self;

    /**
     * Create a lazy stream over a single-pass PDOStatement cursor (one statement, one collection).
     *
     * A second traversal throws (call remember() to re-traverse). A second fromCursor() over
     * the same statement object returns a collection whose first traversal throws
     * RuntimeException('PDOStatement already bound to another lazy collection').
     *
     * @param PDOStatement $statement
     * @param callable|null $rowMapper fn($row, $index): mixed
     * @return self
     */
    public static function fromCursor(PDOStatement $statement, ?callable $rowMapper = null): self;

    /**
     * Create a lazy stream over the items of a paginated HTTP API, downloading the next page
     * while the items of the current one are consumed (since 1.5.0).
     *
     * `$requestFor($cursor, $pageIndex)` builds the request of a page (the cursor of the first
     * page is null, `$pageIndex` counts from 0; null ends the stream); `$parsePage($response)`
     * answers `[iterable $items, mixed $nextCursor]` (a null cursor marks the last page).
     * Cursor-in-body, page-number and `Link`-header pagination all fit this shape.
     *
     * As soon as page n is parsed, the request of page n+1 is started with curl_multi and
     * only then are the items of page n yielded (prefetch depth 1, no event loop, no Fiber).
     * A status outside 2xx, a transport error, the timeout or the page limit throw
     * `HttpPageException` at the point where that page would be delivered (the items of the
     * previous pages were already yielded); its message never carries the response body nor
     * the query string. Stopping early (`take()`, `first()`, `any()`) cancels the request in
     * flight: at most one page is downloaded for nothing. Every traversal fetches again from
     * the first page; call `remember()` to traverse once and replay from memory. TLS
     * verification is always on. Requires ext-curl.
     *
     * @param callable $requestFor fn(mixed $cursor, int $pageIndex): ?HttpPageRequest
     * @param callable $parsePage fn(HttpPageResponse $response): array{0: iterable, 1: mixed}
     * @param HttpPageOptions|null $options Timeout, page limit, common headers, redirects
     * @return self
     */
    public static function fromHttpPages(callable $requestFor, callable $parsePage, ?HttpPageOptions $options = null): self;

    /**
     * Create a lazy stream over the items of an HTTP API whose pages are addressed by their
     * index alone (`?page=N`), keeping up to `$prefetch` pages in flight ahead of the page being
     * consumed (since 2.4.0).
     *
     * `$requestFor($pageIndex)` answers the request of a page (an HttpPageRequest, or an absolute
     * http(s) URL for a GET; null ends the stream before that page); `$pageIndex` counts from 0
     * and needs no previous response, so pages n+1 … n+$prefetch are requested with curl_multi
     * before the items of page n are yielded. `$parsePage($response)` answers the iterable
     * items of the page. The stream ends after the first page without items, or after the page
     * for which `$until($pageIndex, $response)` answers true (its items are yielded); the
     * requests still in flight are then cancelled, as on early termination (`take()`,
     * `first()`, `any()`): at most `$prefetch` pages are downloaded for nothing. Errors, the
     * timeout and the page limit (`maxPages` pages were fetched and none ended the stream)
     * throw `HttpPageException` at the point where that page would be delivered, as in
     * fromHttpPages(). Every traversal fetches again from page 0. Requires ext-curl.
     *
     * @param callable $requestFor fn(int $pageIndex): HttpPageRequest|string|null
     * @param callable $parsePage fn(HttpPageResponse $response): iterable
     * @param int $prefetch Pages requested ahead of the page being consumed, at least 1 (default 2)
     * @param callable|null $until fn(int $pageIndex, HttpPageResponse $response): bool, true on the last page
     * @param HttpPageOptions|null $options Timeout, page limit, common headers, redirects
     * @return self
     * @throws \InvalidArgumentException When $prefetch is below 1
     * @throws \LogicException When ext-curl is not loaded
     */
    public static function fromHttpPagesByIndex(
        callable $requestFor,
        callable $parsePage,
        int $prefetch = 2,
        ?callable $until = null,
        ?HttpPageOptions $options = null
    ): self;

    /**
     * Create an empty lazy collection.
     *
     * @return self
     */
    public static function empty(): self;

    /**
     * Generate an arithmetic progression lazily without allocating array memory.
     *
     * @param int $start
     * @param int $end
     * @param int $step
     * @return self
     * @throws \InvalidArgumentException when $step is lower than 1
     */
    public static function range(int $start, int $end, int $step = 1): self;

    /**
     * Generate repeated elements lazily without allocating array memory.
     *
     * @param mixed $element
     * @param int $count
     * @return self
     * @throws \InvalidArgumentException when $count is negative
     */
    public static function repeat(mixed $element, int $count): self;

    /**
     * Filter items yielding only elements that satisfy the predicate.
     *
     * @param callable $predicate fn($item, $key): bool
     * @return self
     */
    public function where(callable $predicate): self;

    /**
     * Filter items yielding elements that do NOT satisfy the predicate.
     *
     * @param callable $predicate fn($item, $key): bool
     * @return self
     */
    public function whereNot(callable $predicate): self;

    /**
     * Keep the items whose field is one of $values, by strict identity (`in_array(..., true)`).
     *
     * @param string $field Property name or dot-notation path, read by the shared accessor (PropertyAccess)
     * @param array $values
     * @return self
     */
    public function whereIn(string $field, array $values): self;

    /**
     * Keep the items whose field is none of $values, by strict identity (`in_array(..., true)`).
     *
     * @param string $field Property name or dot-notation path, read by the shared accessor (PropertyAccess)
     * @param array $values
     * @return self
     */
    public function whereNotIn(string $field, array $values): self;

    /**
     * Keep the items whose field lies in [$min, $max], bounds included; null is never between.
     *
     * @param string $field Property name or dot-notation path, read by the shared accessor (PropertyAccess)
     * @param mixed $min
     * @param mixed $max
     * @return self
     */
    public function whereBetween(string $field, mixed $min, mixed $max): self;

    /**
     * Transform each item using a selector closure.
     *
     * @param callable $selector fn($item, $key): mixed
     * @return self
     */
    public function select(callable $selector): self;

    /**
     * Project each item to an iterable and flatten the resulting sequences.
     *
     * @param callable $selector fn($item, $key): iterable
     * @return self
     * @throws \UnexpectedValueException when the selector returns a non-iterable value
     */
    public function selectMany(callable $selector): self;

    /**
     * Yield up to $count elements and immediately terminate the stream.
     *
     * @param int $count
     * @return self
     * @throws \InvalidArgumentException when $count is negative
     */
    public function take(int $count): self;

    /**
     * Discard the first $count elements and stream the remaining items.
     *
     * @param int $count
     * @return self
     * @throws \InvalidArgumentException when $count is negative
     */
    public function skip(int $count): self;

    /**
     * Yield elements while the predicate returns true, then stop.
     *
     * @param callable $predicate fn($item, $key): bool
     * @return self
     */
    public function takeWhile(callable $predicate): self;

    /**
     * Skip elements while the predicate returns true, then yield the rest.
     *
     * @param callable $predicate fn($item, $key): bool
     * @return self
     */
    public function skipWhile(callable $predicate): self;

    /**
     * Yield unique elements by tracking a hash set of observed keys lazily.
     *
     * @param callable|null $keySelector
     * @param IALinqEqualityComparer|null $comparer The identity (since 2.2.0): hash() buckets, equals() decides
     * @return self
     */
    public function distinct(?callable $keySelector = null, ?IALinqEqualityComparer $comparer = null): self;

    /**
     * Yield unique elements based on a key selector.
     *
     * @param callable $keySelector
     * @param IALinqEqualityComparer|null $comparer The identity of the selected key (since 2.2.0)
     * @return self
     */
    public function distinctBy(callable $keySelector, ?IALinqEqualityComparer $comparer = null): self;

    /**
     * Yield chunks of $size elements (the last one may be shorter), each chunk an eager
     * ALinqCollection; inside each chunk a list is reindexed and a dictionary keeps its keys.
     *
     * @param int $size
     * @return self
     * @throws \InvalidArgumentException when $size is lower than 1
     */
    public function chunk(int $size): self;

    /**
     * Pad the stream up to a specified size with a fallback value.
     *
     * @param int $size
     * @param mixed $value
     * @return self
     * @throws \InvalidArgumentException when $size is negative
     */
    public function pad(int $size, mixed $value): self;

    /**
     * Concatenate another sequence after the current stream.
     *
     * @param iterable|callable $second
     * @return self
     */
    public function concat(iterable|callable $second): self;

    /**
     * Pairwise combine two sequences into a stream of paired elements.
     *
     * @param iterable|callable $second
     * @param callable|null $resultSelector fn($first, $second): mixed
     * @return self
     */
    public function zip(iterable|callable $second, ?callable $resultSelector = null): self;

    /**
     * Inner join (Join in LINQ, since 2.4.0): the inner side is read once and indexed on
     * traversal, this stream is streamed; for each outer item, one result per matching inner
     * item (outer order, then inner order), the answers of the eager join(). A null key never
     * matches. The result is a list.
     *
     * @param iterable $inner
     * @param callable $outerKeySelector fn($outer, $key): mixed
     * @param callable $innerKeySelector fn($inner, $key): mixed
     * @param callable $resultSelector fn($outer, $inner): mixed
     * @param IALinqEqualityComparer|null $comparer The identity of the keys (since 2.4.0); strict identity when null
     * @return self
     */
    public function join(iterable $inner, callable $outerKeySelector, callable $innerKeySelector, callable $resultSelector, ?IALinqEqualityComparer $comparer = null): self;

    /**
     * Group join (GroupJoin in LINQ, since 2.4.0): as join(), but one result per outer item,
     * the matching inner items handed over as an ALinqCollection (empty when nothing matches).
     * The result is a list.
     *
     * @param iterable $inner
     * @param callable $outerKeySelector fn($outer, $key): mixed
     * @param callable $innerKeySelector fn($inner, $key): mixed
     * @param callable $resultSelector fn($outer, ALinqCollection $matches): mixed
     * @param IALinqEqualityComparer|null $comparer The identity of the keys (since 2.4.0); strict identity when null
     * @return self
     */
    public function groupJoin(iterable $inner, callable $outerKeySelector, callable $innerKeySelector, callable $resultSelector, ?IALinqEqualityComparer $comparer = null): self;

    /**
     * Left outer join (LeftJoin in .NET 10/11 LINQ): the inner side is indexed on traversal and
     * this stream is streamed; every outer item appears, with `null` as the inner side when
     * nothing matches (a null key never matches). The result is a list.
     *
     * @param iterable $inner
     * @param callable $outerKeySelector fn($outer, $key): mixed
     * @param callable $innerKeySelector fn($inner, $key): mixed
     * @param callable|null $resultSelector fn($outer, $inner): mixed; the pair [$outer, $inner] when null
     * @param IALinqEqualityComparer|null $comparer The identity of the keys (since 2.4.0); strict identity when null
     * @return self
     */
    public function leftJoin(iterable $inner, callable $outerKeySelector, callable $innerKeySelector, ?callable $resultSelector = null, ?IALinqEqualityComparer $comparer = null): self;

    /**
     * Right outer join (RightJoin in .NET 10/11 LINQ): this stream is indexed on traversal and
     * the inner side is streamed; every inner item appears, with `null` as the outer side when
     * nothing matches (a null key never matches). The result is a list, in inner order.
     *
     * @param iterable $inner
     * @param callable $outerKeySelector fn($outer, $key): mixed
     * @param callable $innerKeySelector fn($inner, $key): mixed
     * @param callable|null $resultSelector fn($outer, $inner): mixed; the pair [$outer, $inner] when null
     * @param IALinqEqualityComparer|null $comparer The identity of the keys (since 2.4.0); strict identity when null
     * @return self
     */
    public function rightJoin(iterable $inner, callable $outerKeySelector, callable $innerKeySelector, ?callable $resultSelector = null, ?IALinqEqualityComparer $comparer = null): self;

    /**
     * Full outer join (FullJoin in .NET 10/11 LINQ): the inner side is indexed on traversal and
     * this stream is streamed (pairs and unmatched outer items, in outer order), then the
     * unmatched inner items follow in inner order. A null key never matches. The result is a list.
     *
     * @param iterable $inner
     * @param callable $outerKeySelector fn($outer, $key): mixed
     * @param callable $innerKeySelector fn($inner, $key): mixed
     * @param callable|null $resultSelector fn($outer, $inner): mixed; the pair [$outer, $inner] when null
     * @param IALinqEqualityComparer|null $comparer The identity of the keys (since 2.4.0); strict identity when null
     * @return self
     */
    public function fullJoin(iterable $inner, callable $outerKeySelector, callable $innerKeySelector, ?callable $resultSelector = null, ?IALinqEqualityComparer $comparer = null): self;

    /**
     * Stable ascending sort by the selected key (OrderBy in LINQ). The stream is consumed and
     * sorted when the result is traversed; thenBy()/thenByDescending() may follow.
     *
     * @param callable $keySelector fn($item, $key): mixed
     * @param callable|null $comparer fn($keyA, $keyB): int in the `<=>` convention (since 2.2.0)
     * @return self
     */
    public function orderBy(callable $keySelector, ?callable $comparer = null): self;

    /**
     * Stable descending sort by the selected key (OrderByDescending in LINQ).
     *
     * @param callable $keySelector fn($item, $key): mixed
     * @param callable|null $comparer fn($keyA, $keyB): int in the `<=>` convention (since 2.2.0)
     * @return self
     */
    public function orderByDescending(callable $keySelector, ?callable $comparer = null): self;

    /**
     * Group the items by key (GroupBy in LINQ), as a deferred stage (since 2.2.0): on traversal
     * the upstream is read once and every item is kept (O(n)); yields `key => ALinqCollection`
     * in first-appearance order. Keys follow the eager groupBy() (int, string or BackedEnum).
     *
     * @param callable $keySelector fn($item, $key): int|string|\BackedEnum
     * @param IALinqEqualityComparer|null $comparer The identity of the keys (since 2.4.0); the first key seen names the group
     * @return self
     * @throws \InvalidArgumentException when a key is not valid (at traversal)
     */
    public function groupBy(callable $keySelector, ?IALinqEqualityComparer $comparer = null): self;

    /**
     * Count the items per key (CountBy in .NET 9), as a deferred stage keeping one integer per
     * key (since 2.2.0); yields `key => count` in first-appearance order.
     *
     * @param callable $keySelector fn($item, $key): int|string|\BackedEnum
     * @param IALinqEqualityComparer|null $comparer The identity of the keys (since 2.4.0); the first key seen names the group
     * @return self
     * @throws \InvalidArgumentException when a key is not valid (at traversal)
     */
    public function countBy(callable $keySelector, ?IALinqEqualityComparer $comparer = null): self;

    /**
     * Fold the items of each key (AggregateBy in .NET 9), as a deferred stage keeping one
     * accumulator per key (since 2.2.0); every group starts from $seed and `$func` receives the
     * position of the item inside its group, as on the eager side.
     *
     * @param callable $keySelector fn($item, $key): int|string|\BackedEnum
     * @param mixed $seed The seed of every group, whatever its type (ignored when $seedSelector is given)
     * @param callable $func fn($carry, $item, $position): mixed
     * @param IALinqEqualityComparer|null $comparer The identity of the keys (since 2.4.0); the first key seen names the group
     * @param callable|null $seedSelector fn($key): the seed of each group, called once per group (since 2.4.0)
     * @return self
     * @throws \InvalidArgumentException when a key is not valid (at traversal)
     */
    public function aggregateBy(callable $keySelector, mixed $seed, callable $func, ?IALinqEqualityComparer $comparer = null, ?callable $seedSelector = null): self;

    /**
     * Materialize a dictionary `key => element` in one pass (ToDictionary in LINQ, since 2.2.0).
     *
     * @param callable $keySelector fn($item, $key): int|string|\BackedEnum
     * @param callable|null $elementSelector fn($item, $key): mixed
     * @param DuplicateKeyPolicy $onDuplicate Throw (default), KeepFirst or KeepLast
     * @param IALinqEqualityComparer|null $comparer The identity of the keys (since 2.4.0); the first key seen is kept
     * @return array
     * @throws \InvalidArgumentException when a key is not valid or, under Throw, produced twice
     */
    public function toDictionary(callable $keySelector, ?callable $elementSelector = null, DuplicateKeyPolicy $onDuplicate = DuplicateKeyPolicy::Throw, ?IALinqEqualityComparer $comparer = null): array;

    /**
     * Materialize an immutable one-to-many lookup in one pass (ToLookup in LINQ, since 2.2.0).
     *
     * @param callable $keySelector fn($item, $key): int|string|\BackedEnum
     * @param callable|null $elementSelector fn($item, $key): mixed
     * @param IALinqEqualityComparer|null $comparer The identity of the keys (since 2.4.0), kept by the lookup
     * @return ALinqLookup
     * @throws \InvalidArgumentException when a key is not valid (a null key included)
     */
    public function toLookup(callable $keySelector, ?callable $elementSelector = null, ?IALinqEqualityComparer $comparer = null): ALinqLookup;

    /**
     * The distinct items whose selected key is not in $keys (ExceptBy of .NET 6), as a
     * deferred stage that reads $keys first on each traversal (since 2.2.0).
     *
     * @param iterable $keys
     * @param callable $keySelector fn($item, $key): mixed
     * @param IALinqEqualityComparer|null $comparer The identity of the keys (since 2.4.0)
     * @return self
     */
    public function exceptByKeys(iterable $keys, callable $keySelector, ?IALinqEqualityComparer $comparer = null): self;

    /**
     * The distinct items whose selected key is in $keys (IntersectBy of .NET 6), as a deferred
     * stage that reads $keys first on each traversal (since 2.2.0).
     *
     * @param iterable $keys
     * @param callable $keySelector fn($item, $key): mixed
     * @param IALinqEqualityComparer|null $comparer The identity of the keys (since 2.4.0)
     * @return self
     */
    public function intersectByKeys(iterable $keys, callable $keySelector, ?IALinqEqualityComparer $comparer = null): self;

    /**
     * Subsequent ascending ordering (ThenBy in LINQ), applied in the same sort as the
     * preceding orderBy*()/thenBy*().
     *
     * @param callable $keySelector fn($item, $key): mixed
     * @param callable|null $comparer fn($keyA, $keyB): int in the `<=>` convention
     * @return self
     * @throws \LogicException when the collection does not come straight from orderBy*()/thenBy*()
     */
    public function thenBy(callable $keySelector, ?callable $comparer = null): self;

    /**
     * Subsequent descending ordering (ThenByDescending in LINQ).
     *
     * @param callable $keySelector fn($item, $key): mixed
     * @param callable|null $comparer fn($keyA, $keyB): int in the `<=>` convention
     * @return self
     * @throws \LogicException when the collection does not come straight from orderBy*()/thenBy*()
     */
    public function thenByDescending(callable $keySelector, ?callable $comparer = null): self;

    /**
     * Inspect elements as they stream through the pipeline without altering the stream.
     *
     * @param callable $callback fn($item, $key): void
     * @return self
     */
    public function tap(callable $callback): self;

    /**
     * Cache the evaluated items in memory so subsequent traversals do not re-run generators.
     *
     * @return self
     */
    public function remember(): self;

    /**
     * Return the first element matching the predicate with short-circuiting.
     *
     * @param callable|null $predicate
     * @return mixed
     * @throws \UnderflowException when the stream is empty or no element matches
     */
    public function first(?callable $predicate = null): mixed;

    /**
     * Return the first element matching the predicate, or fallback default if not found.
     *
     * @param mixed $default
     * @param callable|null $predicate
     * @return mixed
     */
    public function firstOrDefault(mixed $default = null, ?callable $predicate = null): mixed;

    /**
     * Return the last element matching the predicate.
     *
     * @param callable|null $predicate
     * @return mixed
     * @throws \UnderflowException when the stream is empty or no element matches
     */
    public function last(?callable $predicate = null): mixed;

    /**
     * Return the last element matching the predicate, or fallback default if not found.
     *
     * @param mixed $default
     * @param callable|null $predicate
     * @return mixed
     */
    public function lastOrDefault(mixed $default = null, ?callable $predicate = null): mixed;

    /**
     * Return the single element, or the single element matching the predicate (Single in LINQ);
     * stops at the second match.
     *
     * @param callable|null $predicate
     * @return mixed
     * @throws \UnderflowException when the stream is empty or no element matches
     * @throws \OverflowException when more than one element (or matching element) exists
     */
    public function single(?callable $predicate = null): mixed;

    /**
     * Ensure the sequence contains at most one element (or one matching predicate).
     *
     * @param mixed $default
     * @param callable|null $predicate
     * @return mixed
     * @throws \OverflowException when more than one element matches
     */
    public function singleOrDefault(mixed $default = null, ?callable $predicate = null): mixed;

    /**
     * Check if any element satisfies the predicate with short-circuiting.
     *
     * @param callable|null $predicate
     * @return bool
     */
    public function any(?callable $predicate = null): bool;

    /**
     * Check if all elements satisfy the predicate with short-circuiting; vacuously true on
     * an empty stream. Without a predicate, whether every element is truthy.
     *
     * @param callable|null $predicate
     * @return bool
     */
    public function all(?callable $predicate = null): bool;

    /**
     * Check if the stream contains a specific value with short-circuiting. Without a
     * comparer the comparison is strict; a comparer may answer `bool` (`true` = equal) or an
     * int in the `<=>` convention (`0` = equal).
     *
     * @param mixed $value
     * @param callable|null $comparer
     * @return bool
     */
    public function contains(mixed $value, ?callable $comparer = null): bool;

    /**
     * Compute the running sum of elements; `null` values are skipped, an empty stream sums to 0.
     *
     * @param callable|null $selector
     * @return int|float
     * @throws \InvalidArgumentException when a value is not numeric
     */
    public function sum(?callable $selector = null): int|float;

    /**
     * Compute the running arithmetic mean of elements; `null` values are skipped.
     *
     * @param callable|null $selector
     * @return int|float
     * @throws \UnderflowException when there is no non-null value to average
     * @throws \InvalidArgumentException when a value is not numeric
     */
    public function average(?callable $selector = null): int|float;

    /**
     * Compute the arithmetic mean like average(), in one traversal, or return `$default` when
     * there is no non-null value to average.
     *
     * @param mixed $default
     * @param callable|null $selector
     * @return mixed
     * @throws \InvalidArgumentException when a value is not numeric
     */
    public function averageOrDefault(mixed $default = null, ?callable $selector = null): mixed;

    /**
     * Find the minimum value in the stream; `null` values are skipped.
     *
     * @param callable|null $selector
     * @return mixed
     * @throws \UnderflowException when there is no non-null value
     * @throws \InvalidArgumentException when a value is not comparable
     */
    public function min(?callable $selector = null, ?callable $comparer = null): mixed;

    /**
     * Find the minimum value like min(), in one traversal, or return `$default` when there is
     * no non-null value.
     *
     * @param mixed $default
     * @param callable|null $selector
     * @return mixed
     * @throws \InvalidArgumentException when a value is not comparable
     */
    public function minOrDefault(mixed $default = null, ?callable $selector = null, ?callable $comparer = null): mixed;

    /**
     * Find the maximum value in the stream; `null` values are skipped.
     *
     * @param callable|null $selector
     * @return mixed
     * @throws \UnderflowException when there is no non-null value
     * @throws \InvalidArgumentException when a value is not comparable
     */
    public function max(?callable $selector = null, ?callable $comparer = null): mixed;

    /**
     * Find the maximum value like max(), in one traversal, or return `$default` when there is
     * no non-null value.
     *
     * @param mixed $default
     * @param callable|null $selector
     * @return mixed
     * @throws \InvalidArgumentException when a value is not comparable
     */
    public function maxOrDefault(mixed $default = null, ?callable $selector = null, ?callable $comparer = null): mixed;

    /**
     * Find the element yielding the minimum value via key selector.
     *
     * @param callable $keySelector
     * @return mixed
     * @throws \UnderflowException when the stream is empty
     */
    public function minBy(callable $keySelector, ?callable $comparer = null): mixed;

    /**
     * Find the element yielding the minimum value like minBy() (the first one on a tie), in
     * one traversal, or return `$default` when the stream is empty.
     *
     * @param mixed $default
     * @param callable $keySelector
     * @return mixed
     */
    public function minByOrDefault(mixed $default, callable $keySelector, ?callable $comparer = null): mixed;

    /**
     * Find the element yielding the maximum value via key selector.
     *
     * @param callable $keySelector
     * @return mixed
     * @throws \UnderflowException when the stream is empty
     */
    public function maxBy(callable $keySelector, ?callable $comparer = null): mixed;

    /**
     * Find the element yielding the maximum value like maxBy() (the first one on a tie), in
     * one traversal, or return `$default` when the stream is empty.
     *
     * @param mixed $default
     * @param callable $keySelector
     * @return mixed
     */
    public function maxByOrDefault(mixed $default, callable $keySelector, ?callable $comparer = null): mixed;

    /**
     * Perform an accumulator fold over the stream with constant memory.
     *
     * @param mixed $seed
     * @param callable $func fn($accumulator, $item, $key): mixed
     * @return mixed
     */
    public function aggregate(mixed $seed, callable $func): mixed;

    /**
     * Join the text of the items (or of the selected values) with a separator, in one pass
     * (since 2.2.0): strings, ints, floats and Stringable by their text, null as '', '' when
     * the stream is empty. The min*()/max*() family above takes an optional comparer
     * `fn($a, $b): int` as its last parameter since 2.2.0.
     *
     * @param string $separator
     * @param callable|null $selector fn($item, $key): mixed
     * @return string
     * @throws \InvalidArgumentException for a bool, an array or an object that is not Stringable
     */
    public function implode(string $separator = '', ?callable $selector = null): string;

    /**
     * Execute a callback for each element in the stream.
     *
     * @param callable $callback fn($item, $key): void
     * @return self
     */
    public function each(callable $callback): self;

    /**
     * Count the elements, or the elements that satisfy the predicate, consuming the stream.
     *
     * @param callable|null $predicate fn($item) or fn($item, $key): bool
     * @return int
     */
    public function count(?callable $predicate = null): int;

    /**
     * Implement JsonSerializable: materializes the stream (list → JSON array, dictionary → JSON object).
     *
     * @return array
     */
    public function jsonSerialize(): array;

    /**
     * Materialize the lazy stream into a native PHP array.
     *
     * @return array
     */
    public function toArray(): array;

    /**
     * Materialize the lazy stream into an in-memory eager ALinqCollection.
     *
     * @return ALinqCollection
     */
    public function toCollection(): ALinqCollection;

    /**
     * Materialize the lazy stream into a stdClass object.
     *
     * @return stdClass
     */
    public function toObject(): stdClass;
}
