<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Tests\Unit;

use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\ALinqLazyCollection;
use Antevemus\ALinq\Comparison\StringComparer;
use Antevemus\ALinq\DuplicateKeyPolicy;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * The code of the first production consumer that motivated 2.2.0 (forward 047, RN-08),
 * rewritten with the library and run against the hand-written original, on synthetic data.
 * No file of the consumer is read or changed: the originals are transcribed below.
 */
final class ConsumerExamplesTest extends TestCase
{
    /**
     * Three orderings by an accented name: a feature-flag list by category then name, a
     * pendency report by "blocking" (descending) then name, a lot alert by "without billing"
     * (descending) then name. The originals compare names with `<=>` on bytes
     * (`mb_strtolower()` does not remove accents), so "Ágil" lands after "Zeta".
     */
    #[RequiresPhpExtension('intl')]
    public function testTheThreeOrderingsByAnAccentedName(): void
    {
        $name = StringComparer::culture('pt_BR', ignoreCase: true, ignoreAccents: true);

        // 1. feature flags: uasort by [category order, mb_strtolower(name)]
        $categories = ['core' => 0, 'beta' => 1];
        $flags = [
            'f1' => (object) ['category' => 'beta', 'name' => 'Zeta'],
            'f2' => (object) ['category' => 'core', 'name' => 'Ética'],
            'f3' => (object) ['category' => 'core', 'name' => 'avaliação'],
            'f4' => (object) ['category' => 'beta', 'name' => 'Ágil'],
            'f5' => (object) ['category' => 'core', 'name' => 'Boleto'],
        ];
        $original = $flags;
        uasort($original, fn($a, $b) => [($categories[$a->category] ?? 99), mb_strtolower($a->name)] <=> [($categories[$b->category] ?? 99), mb_strtolower($b->name)]);
        $this->assertSame(['f3', 'f5', 'f2', 'f1', 'f4'], array_keys($original), 'the original: Ética after Boleto is right by chance, Ágil after Zeta is not');

        $sorted = ALinqCollection::from($flags)
            ->orderBy(fn($f) => $categories[$f->category] ?? 99)
            ->thenBy(fn($f) => $f->name, $name)
            ->toArray();
        $this->assertSame(['f3', 'f5', 'f2', 'f4', 'f1'], array_keys($sorted));

        // 2. pendency report: uasort by [bloqueiam desc, nome asc]
        $units = [
            7 => ['bloqueiam' => 2, 'nome' => 'Órion Imóveis'],
            3 => ['bloqueiam' => 2, 'nome' => 'Lar Imóveis'],
            9 => ['bloqueiam' => 0, 'nome' => 'Água Clara'],
            4 => ['bloqueiam' => 0, 'nome' => 'Casa Nova'],
        ];
        $original = $units;
        uasort($original, fn($a, $b) => [$b['bloqueiam'], $a['nome']] <=> [$a['bloqueiam'], $b['nome']]);
        $this->assertSame([3, 7, 4, 9], array_keys($original));
        $this->assertSame(
            [3, 7, 9, 4],
            array_keys(ALinqCollection::from($units)->orderByDescending(fn($u) => $u['bloqueiam'])->thenBy(fn($u) => $u['nome'], $name)->toArray())
        );

        // 3. lot alert: uasort by [sem_faturamento desc, nome asc]
        $lots = [
            11 => ['sem_faturamento' => true, 'nome' => 'Évora'],
            12 => ['sem_faturamento' => false, 'nome' => 'Itaú Lar'],
            13 => ['sem_faturamento' => true, 'nome' => 'Delta'],
            14 => ['sem_faturamento' => false, 'nome' => 'ômega'],
        ];
        $original = $lots;
        uasort($original, fn($a, $b) => [$b['sem_faturamento'], $a['nome']] <=> [$a['sem_faturamento'], $b['nome']]);
        $this->assertSame([13, 11, 12, 14], array_keys($original));
        $this->assertSame(
            [13, 11, 12, 14],
            array_keys(ALinqLazyCollection::from($lots)->orderByDescending(fn($l) => $l['sem_faturamento'])->thenBy(fn($l) => $l['nome'], $name)->toArray())
        );
        $lots[14]['nome'] = 'Ágape';
        $this->assertSame(
            [13, 11, 14, 12],
            array_keys(ALinqCollection::from($lots)->orderByDescending(fn($l) => $l['sem_faturamento'])->thenBy(fn($l) => $l['nome'], $name)->toArray())
        );
    }

    /**
     * A hand-written index, "the last one wins" in silence (`$m[$x->id] = $x`), and a
     * hand-written grouping (`$m[$k][] = $x`).
     */
    public function testTheHandWrittenIndexAndGrouping(): void
    {
        $rows = [
            (object) ['id' => 1, 'unit' => 'A', 'status' => 'old'],
            (object) ['id' => 2, 'unit' => 'B', 'status' => 'ok'],
            (object) ['id' => 1, 'unit' => 'A', 'status' => 'new'],
        ];

        $index = [];
        foreach ($rows as $row) {
            $index[$row->id] = $row;
        }
        $this->assertSame($index, ALinqCollection::from($rows)->toDictionary(fn($r) => $r->id, onDuplicate: DuplicateKeyPolicy::KeepLast));
        $this->assertSame($index, ALinqLazyCollection::from($rows)->toDictionary(fn($r) => $r->id, onDuplicate: DuplicateKeyPolicy::KeepLast));

        $byUnit = [];
        foreach ($rows as $row) {
            $byUnit[$row->unit][] = $row;
        }
        $lookup = ALinqCollection::from($rows)->toLookup(fn($r) => $r->unit);
        $this->assertSame($byUnit, $lookup->toArray());
        $this->assertSame([], $lookup->get('C')->toArray(), 'where the manual map needs `?? []`');
        $this->assertSame($byUnit, ALinqLazyCollection::from($rows)->toLookup(fn($r) => $r->unit)->toArray());
    }

    /**
     * Documents without a link to any participant: the original runs any() over the relations
     * once per document (O(n × m), loose `==`); exceptByKeys() reads the linked ids once.
     */
    public function testTheDocumentsWithoutAParticipant(): void
    {
        $doc = fn(int $id, string $type) => (object) ['id' => $id, 'document_type' => $type];
        $relation = fn(int $participant, string $document) => (object) ['id_participant' => $participant, 'id_document' => $document]; // ids come as text from PDO
        $existingDocs = ALinqCollection::from([$doc(1, 'RG'), $doc(2, 'RG'), $doc(3, 'CPF'), $doc(4, 'RG'), $doc(5, 'RG')]);
        $relations = ALinqCollection::from([$relation(10, '2'), $relation(11, '3'), $relation(10, '5')]);

        $generalDocsOfType = fn(string $docType) => $existingDocs->where(function ($d) use ($docType, $relations) {
            $hasParticipantRelation = $relations->any(fn($r) => $r->id_document == $d->id);
            return $d->document_type == $docType && !$hasParticipantRelation;
        })->toArray();

        $rewritten = fn(string $docType) => $existingDocs
            ->where(fn($d) => $d->document_type === $docType)
            ->exceptByKeys($relations->select(fn($r) => (int) $r->id_document), fn($d) => (int) $d->id)
            ->toArray();

        $this->assertSame([1, 4], array_column($generalDocsOfType('RG'), 'id'));
        $this->assertSame($generalDocsOfType('RG'), $rewritten('RG'));
        $this->assertSame($generalDocsOfType('CPF'), $rewritten('CPF'));
        $this->assertSame([], $rewritten('CNH'));
    }

    /**
     * The CSV reader of the economic indexes: separator by PRESENCE in the first physical line,
     * `;` before tab before `,`; the library decides by COUNT outside quotes and refuses a tie
     * (CHANGELOG 2.2.0). For index files (a date column and a value column) both choose alike.
     */
    public function testTheCsvReaderOfTheEconomicIndexes(): void
    {
        $readCsv = static function (string $path): array {
            $handle = fopen($path, 'r');
            $firstLine = (string) fgets($handle);
            rewind($handle);
            $delimiter = str_contains($firstLine, ';') ? ';' : (str_contains($firstLine, "\t") ? "\t" : ',');
            $out = [];
            $line = 0;
            while (($cells = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                $line++;
                if ($line === 1 && isset($cells[0])) {
                    $cells[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $cells[0]);
                }
                $out[$line] = $cells;
            }
            fclose($handle);
            return $out;
        };
        $path = tempnam(sys_get_temp_dir(), 'alinq-index');

        try {
            foreach (
                [
                    "\xEF\xBB\xBFdata;valor\n01/2026;0,52\n02/2026;0,61\n",
                    "data\tvalor\n01/2026\t0.52\n",
                    "data,valor\n01/2026,0.52\n02/2026,0.61\n",
                ] as $content
            ) {
                file_put_contents($path, $content);
                $this->assertSame(
                    array_values($readCsv($path)),
                    ALinqLazyCollection::fromCsv($path, separator: null, hasHeader: false, strict: false)->toArray(),
                    json_encode($content)
                );
            }

            // where the two rules part: a quoted `;` in a comma file
            file_put_contents($path, "\"Índice; mensal\",valor\nIPCA,0.52\n");
            $this->assertSame(['Índice; mensal,valor'], $readCsv($path)[1], 'presence picks `;`: one field');
            $this->assertSame(['Índice; mensal', 'valor'], ALinqLazyCollection::fromCsv($path, separator: null, hasHeader: false)->first());
        } finally {
            unlink($path);
        }
    }
}
