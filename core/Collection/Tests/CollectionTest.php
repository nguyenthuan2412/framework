<?php

namespace Ryra\Core\Collection\Tests;

use PHPUnit\Framework\TestCase;
use Ryra\Core\Collection\Collection;
use Ryra\Core\Collection\LazyCollection;
use Ryra\Core\Collection\ItemNotFoundException;
use Ryra\Core\Collection\MultipleItemsFoundException;
use BadMethodCallException;

class CollectionTest extends TestCase
{
    // =========================================================================
    // make / range / constructor
    // =========================================================================

    public function test_make_creates_collection(): void
    {
        $c = Collection::make([1, 2, 3]);
        $this->assertSame([1, 2, 3], $c->all());
    }

    public function test_constructor_accepts_array(): void
    {
        $c = new Collection(['a', 'b']);
        $this->assertSame(['a', 'b'], $c->all());
    }

    public function test_range_creates_sequential_collection(): void
    {
        $c = Collection::range(1, 5);
        $this->assertSame([1, 2, 3, 4, 5], $c->all());
    }

    public function test_range_with_step(): void
    {
        $c = Collection::range(0, 10, 2);
        $this->assertSame([0, 2, 4, 6, 8, 10], $c->all());
    }

    // =========================================================================
    // all / get / has / hasAny
    // =========================================================================

    public function test_all_returns_items(): void
    {
        $this->assertSame([1, 2], Collection::make([1, 2])->all());
    }

    public function test_get_returns_value_by_key(): void
    {
        $c = Collection::make(['a' => 1, 'b' => 2]);
        $this->assertSame(1, $c->get('a'));
    }

    public function test_get_returns_default(): void
    {
        $c = Collection::make(['a' => 1]);
        $this->assertNull($c->get('missing'));
        $this->assertSame('default', $c->get('missing', 'default'));
    }

    public function test_get_with_callable_default(): void
    {
        $c = Collection::make([]);
        $this->assertSame('computed', $c->get('x', fn() => 'computed'));
    }

    public function test_has_returns_true_for_existing_keys(): void
    {
        $c = Collection::make(['a' => 1, 'b' => 2]);
        $this->assertTrue($c->has('a', 'b'));
        $this->assertFalse($c->has('a', 'c'));
    }

    public function test_has_any_returns_true_if_at_least_one_exists(): void
    {
        $c = Collection::make(['a' => 1]);
        $this->assertTrue($c->hasAny('a', 'x'));
        $this->assertFalse($c->hasAny('x', 'y'));
    }

    // =========================================================================
    // push / prepend / put / concat
    // =========================================================================

    public function test_push_appends_value(): void
    {
        $c = Collection::make([1, 2])->push(3, 4);
        $this->assertSame([1, 2, 3, 4], $c->all());
    }

    public function test_prepend_without_key(): void
    {
        $c = Collection::make([1, 2])->prepend(0);
        $this->assertSame([0, 1, 2], $c->all());
    }

    public function test_prepend_with_key(): void
    {
        $c = Collection::make(['b' => 2])->prepend(1, 'a');
        $this->assertSame(['a' => 1, 'b' => 2], $c->all());
    }

    public function test_put_sets_value_by_key(): void
    {
        $c = Collection::make(['a' => 1])->put('b', 2);
        $this->assertSame(2, $c->get('b'));
    }

    public function test_concat_appends_all_items(): void
    {
        $c = Collection::make([1, 2])->concat([3, 4]);
        $this->assertSame([1, 2, 3, 4], $c->all());
    }

    // =========================================================================
    // pop / shift
    // =========================================================================

    public function test_pop_removes_and_returns_last(): void
    {
        $c = Collection::make([1, 2, 3]);
        $this->assertSame(3, $c->pop());
        $this->assertSame([1, 2], $c->all());
    }

    public function test_pop_multiple(): void
    {
        $c = Collection::make([1, 2, 3, 4]);
        $popped = $c->pop(2);
        $this->assertInstanceOf(Collection::class, $popped);
        $this->assertSame([4, 3], $popped->all());
    }

    public function test_shift_removes_and_returns_first(): void
    {
        $c = Collection::make([1, 2, 3]);
        $this->assertSame(1, $c->shift());
        $this->assertSame([2, 3], $c->all());
    }

    public function test_shift_multiple(): void
    {
        $c = Collection::make([1, 2, 3, 4]);
        $shifted = $c->shift(2);
        $this->assertInstanceOf(Collection::class, $shifted);
        $this->assertSame([1, 2], $shifted->all());
        $this->assertSame([3, 4], $c->all());
    }

    // =========================================================================
    // forget / except / pull
    // =========================================================================

    public function test_forget_removes_key(): void
    {
        $c = Collection::make(['a' => 1, 'b' => 2])->forget('a');
        $this->assertFalse($c->has('a'));
    }

    public function test_except_returns_new_without_keys(): void
    {
        $c = Collection::make(['a' => 1, 'b' => 2, 'c' => 3])->except(['a', 'c']);
        $this->assertSame(['b' => 2], $c->all());
    }

    public function test_pull_returns_and_removes(): void
    {
        $c = Collection::make(['a' => 1, 'b' => 2]);
        $val = $c->pull('a');
        $this->assertSame(1, $val);
        $this->assertFalse($c->has('a'));
    }

    // =========================================================================
    // keys / values / keyBy / only
    // =========================================================================

    public function test_keys_returns_collection_of_keys(): void
    {
        $c = Collection::make(['a' => 1, 'b' => 2])->keys();
        $this->assertSame(['a', 'b'], $c->all());
    }

    public function test_values_reindexes(): void
    {
        $c = Collection::make(['a' => 1, 'b' => 2])->values();
        $this->assertSame([0 => 1, 1 => 2], $c->all());
    }

    public function test_key_by_string(): void
    {
        $data = [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']];
        $c = Collection::make($data)->keyBy('id');
        $this->assertArrayHasKey(1, $c->all());
        $this->assertArrayHasKey(2, $c->all());
    }

    public function test_only_returns_specified_keys(): void
    {
        $c = Collection::make(['a' => 1, 'b' => 2, 'c' => 3])->only('a', 'c');
        $this->assertSame(['a' => 1, 'c' => 3], $c->all());
    }

    // =========================================================================
    // count / isEmpty / isNotEmpty
    // =========================================================================

    public function test_count_returns_number_of_items(): void
    {
        $this->assertSame(3, Collection::make([1, 2, 3])->count());
        $this->assertSame(0, Collection::make([])->count());
    }

    public function test_count_with_key_counts_items_having_that_field(): void
    {
        $data = [['a' => 1], ['a' => 2], ['b' => 3]];
        $this->assertSame(2, Collection::make($data)->count('a'));
    }

    public function test_count_with_callback(): void
    {
        $c = Collection::make([1, 2, 3, 4]);
        $this->assertSame(2, $c->count(fn($v) => $v > 2));
    }

    public function test_is_empty(): void
    {
        $this->assertTrue(Collection::make([])->isEmpty());
        $this->assertFalse(Collection::make([1])->isEmpty());
    }

    public function test_is_not_empty(): void
    {
        $this->assertTrue(Collection::make([1])->isNotEmpty());
        $this->assertFalse(Collection::make([])->isNotEmpty());
    }

    // =========================================================================
    // sum / avg / max / min / median / mode
    // =========================================================================

    public function test_sum_without_key(): void
    {
        $this->assertSame(15, Collection::make([1, 2, 3, 4, 5])->sum());
    }

    public function test_sum_with_key(): void
    {
        $data = [['price' => 10], ['price' => 20], ['price' => 30]];
        $this->assertSame(60, Collection::make($data)->sum('price'));
    }

    public function test_sum_with_callback(): void
    {
        $c = Collection::make([1, 2, 3, 4]);
        $this->assertSame(6, $c->sum(fn($v) => $v % 2 === 0 ? $v : 0));
    }

    public function test_avg(): void
    {
        $this->assertEquals(3, Collection::make([1, 2, 3, 4, 5])->avg());
    }

    public function test_avg_with_key(): void
    {
        $data = [['score' => 10], ['score' => 20]];
        $this->assertEquals(15, Collection::make($data)->avg('score'));
    }

    public function test_avg_returns_null_when_empty(): void
    {
        $this->assertNull(Collection::make([])->avg());
    }

    public function test_max_without_callback(): void
    {
        $this->assertSame(5, Collection::make([3, 1, 5, 2])->max());
    }

    public function test_max_with_key(): void
    {
        $data = [['age' => 25], ['age' => 30], ['age' => 20]];
        $this->assertSame(30, Collection::make($data)->max('age'));
    }

    public function test_min_without_callback(): void
    {
        $this->assertSame(1, Collection::make([3, 1, 5, 2])->min());
    }

    public function test_min_with_key(): void
    {
        $data = [['age' => 25], ['age' => 30], ['age' => 20]];
        $this->assertSame(20, Collection::make($data)->min('age'));
    }

    public function test_median_odd_count(): void
    {
        $this->assertSame(3, Collection::make([5, 1, 3, 2, 4])->median());
    }

    public function test_median_even_count(): void
    {
        $this->assertSame(2.5, Collection::make([1, 2, 3, 4])->median());
    }

    public function test_mode(): void
    {
        $result = Collection::make([1, 2, 2, 3, 3, 3])->mode();
        $this->assertContains(3, $result);
    }

    // =========================================================================
    // map / transform / filter / reject
    // =========================================================================

    public function test_map_applies_callback(): void
    {
        $c = Collection::make([1, 2, 3])->map(fn($v) => $v * 2);
        $this->assertSame([2, 4, 6], $c->all());
    }

    public function test_map_with_key_in_callback(): void
    {
        $c = Collection::make(['a' => 1, 'b' => 2])->map(fn($v, $k) => "$k=$v");
        $this->assertSame(['a' => 'a=1', 'b' => 'b=2'], $c->all());
    }

    public function test_transform_mutates_in_place(): void
    {
        $c = Collection::make([1, 2, 3]);
        $result = $c->transform(fn($v) => $v * 10);
        $this->assertSame($c, $result);
        $this->assertSame([10, 20, 30], $c->all());
    }

    public function test_filter_keeps_matching_items(): void
    {
        $c = Collection::make([1, 2, 3, 4, 5])->filter(fn($v) => $v > 3);
        $this->assertSame([3 => 4, 4 => 5], $c->all());
    }

    public function test_filter_without_callback_removes_falsy(): void
    {
        $c = Collection::make([0, 1, '', 'hello', null, false, true])->filter();
        $this->assertContains(1, $c->all());
        $this->assertContains('hello', $c->all());
        $this->assertContains(true, $c->all());
    }

    public function test_reject_removes_matching_items(): void
    {
        $c = Collection::make([1, 2, 3, 4, 5])->reject(fn($v) => $v > 3);
        $this->assertSame([0 => 1, 1 => 2, 2 => 3], $c->all());
    }

    // =========================================================================
    // each / eachSpread / tap / pipe
    // =========================================================================

    public function test_each_iterates_all_items(): void
    {
        $results = [];
        Collection::make([1, 2, 3])->each(function ($v) use (&$results) {
            $results[] = $v;
        });
        $this->assertSame([1, 2, 3], $results);
    }

    public function test_each_stops_on_false_return(): void
    {
        $results = [];
        Collection::make([1, 2, 3, 4])->each(function ($v) use (&$results) {
            if ($v === 3) return false;
            $results[] = $v;
        });
        $this->assertSame([1, 2], $results);
    }

    public function test_tap_calls_callback_and_returns_self(): void
    {
        $called = false;
        $c = Collection::make([1, 2]);
        $result = $c->tap(function ($col) use (&$called) {
            $called = true;
        });
        $this->assertTrue($called);
        $this->assertSame($c, $result);
    }

    public function test_pipe_passes_to_callback(): void
    {
        $result = Collection::make([1, 2, 3])->pipe(fn($c) => $c->sum());
        $this->assertSame(6, $result);
    }

    // =========================================================================
    // reduce / every / partition
    // =========================================================================

    public function test_reduce_accumulates(): void
    {
        $result = Collection::make([1, 2, 3, 4])->reduce(fn($carry, $v) => $carry + $v, 0);
        $this->assertSame(10, $result);
    }

    public function test_every_returns_true_when_all_pass(): void
    {
        $this->assertTrue(Collection::make([2, 4, 6])->every(fn($v) => $v % 2 === 0));
    }

    public function test_every_returns_false_when_any_fails(): void
    {
        $this->assertFalse(Collection::make([2, 3, 6])->every(fn($v) => $v % 2 === 0));
    }

    public function test_partition_splits_collection(): void
    {
        [$evens, $odds] = Collection::make([1, 2, 3, 4, 5])
            ->partition(fn($v) => $v % 2 === 0)
            ->all();
        $this->assertSame([2, 4], $evens->all());
        $this->assertSame([1, 3, 5], $odds->all());
    }

    // =========================================================================
    // first / firstOrFail / firstWhere / last
    // =========================================================================

    public function test_first_returns_first_item(): void
    {
        $this->assertSame(1, Collection::make([1, 2, 3])->first());
    }

    public function test_first_with_callback(): void
    {
        $result = Collection::make([1, 2, 3, 4])->first(fn($v) => $v > 2);
        $this->assertSame(3, $result);
    }

    public function test_first_returns_default(): void
    {
        $this->assertSame('n/a', Collection::make([])->first(null, 'n/a'));
    }

    public function test_first_or_fail_throws_on_empty(): void
    {
        $this->expectException(ItemNotFoundException::class);
        Collection::make([])->firstOrFail();
    }

    public function test_first_or_fail_returns_value(): void
    {
        $this->assertSame(1, Collection::make([1, 2, 3])->firstOrFail());
    }

    public function test_first_where(): void
    {
        $data = [['name' => 'Alice', 'age' => 30], ['name' => 'Bob', 'age' => 25]];
        $result = Collection::make($data)->firstWhere('age', 25);
        $this->assertSame('Bob', $result['name']);
    }

    public function test_last_returns_last_item(): void
    {
        $this->assertSame(3, Collection::make([1, 2, 3])->last());
    }

    public function test_last_with_callback(): void
    {
        $result = Collection::make([1, 2, 3, 4])->last(fn($v) => $v < 3);
        $this->assertSame(2, $result);
    }

    // =========================================================================
    // contains / containsStrict / sole
    // =========================================================================

    public function test_contains_value(): void
    {
        $c = Collection::make([1, 2, 3]);
        $this->assertTrue($c->contains(2));
        $this->assertFalse($c->contains(5));
    }

    public function test_contains_with_callback(): void
    {
        $this->assertTrue(Collection::make([1, 2, 3])->contains(fn($v) => $v > 2));
    }

    public function test_contains_with_key_value(): void
    {
        $data = [['name' => 'Alice'], ['name' => 'Bob']];
        $this->assertTrue(Collection::make($data)->contains('name', 'Alice'));
        $this->assertFalse(Collection::make($data)->contains('name', 'Eve'));
    }

    public function test_contains_strict(): void
    {
        // containsStrict with key-value: uses strict comparison on nested field
        $data = [['val' => 1], ['val' => '1'], ['val' => 2]];
        $c = Collection::make($data);
        $this->assertTrue($c->containsStrict('val', 1));    // int 1 matches
        $this->assertFalse($c->containsStrict('val', true)); // bool true !== int 1 (strict)
    }

    public function test_sole_returns_single_matching(): void
    {
        $data = [['id' => 1, 'role' => 'admin'], ['id' => 2, 'role' => 'user']];
        $result = Collection::make($data)->sole('role', 'admin');
        $this->assertSame(1, $result['id']);
    }

    public function test_sole_throws_when_none(): void
    {
        $this->expectException(ItemNotFoundException::class);
        Collection::make([['role' => 'user']])->sole('role', 'admin');
    }

    public function test_sole_throws_when_multiple(): void
    {
        $this->expectException(MultipleItemsFoundException::class);
        Collection::make([['role' => 'admin'], ['role' => 'admin']])->sole('role', 'admin');
    }

    // =========================================================================
    // merge / mergeRecursive / replace / replaceRecursive / union
    // =========================================================================

    public function test_merge(): void
    {
        $c = Collection::make(['a' => 1])->merge(['b' => 2]);
        $this->assertSame(['a' => 1, 'b' => 2], $c->all());
    }

    public function test_merge_recursive(): void
    {
        $c = Collection::make(['a' => [1]])->mergeRecursive(['a' => [2]]);
        $this->assertSame(['a' => [1, 2]], $c->all());
    }

    public function test_replace(): void
    {
        $c = Collection::make(['a' => 1, 'b' => 2])->replace(['b' => 99]);
        $this->assertSame(['a' => 1, 'b' => 99], $c->all());
    }

    public function test_replace_recursive(): void
    {
        $c = Collection::make(['a' => ['x' => 1]])->replaceRecursive(['a' => ['x' => 99, 'y' => 2]]);
        $this->assertSame(['a' => ['x' => 99, 'y' => 2]], $c->all());
    }

    public function test_union(): void
    {
        $c = Collection::make(['a' => 1])->union(['a' => 99, 'b' => 2]);
        $this->assertSame(['a' => 1, 'b' => 2], $c->all());
    }

    // =========================================================================
    // diff / diffAssoc / diffKeys / intersect / intersectAssoc / intersectByKeys
    // =========================================================================

    public function test_diff(): void
    {
        $c = Collection::make([1, 2, 3, 4])->diff([2, 4]);
        $this->assertSame([0 => 1, 2 => 3], $c->all());
    }

    public function test_diff_assoc(): void
    {
        $c = Collection::make(['a' => 1, 'b' => 2])->diffAssoc(['a' => 1]);
        $this->assertSame(['b' => 2], $c->all());
    }

    public function test_diff_keys(): void
    {
        $c = Collection::make(['a' => 1, 'b' => 2, 'c' => 3])->diffKeys(['a' => 0, 'c' => 0]);
        $this->assertSame(['b' => 2], $c->all());
    }

    public function test_intersect(): void
    {
        $c = Collection::make([1, 2, 3])->intersect([2, 4]);
        $this->assertContains(2, $c->all());
    }

    public function test_intersect_by_keys(): void
    {
        $c = Collection::make(['a' => 1, 'b' => 2])->intersectByKeys(['a' => 99]);
        $this->assertSame(['a' => 1], $c->all());
    }

    // =========================================================================
    // unique / uniqueStrict / duplicates
    // =========================================================================

    public function test_unique_removes_duplicates(): void
    {
        $c = Collection::make([1, 2, 2, 3, 3, 3])->unique();
        $this->assertSame([1, 2, 3], array_values($c->all()));
    }

    public function test_unique_by_key(): void
    {
        $data = [['name' => 'Alice', 'dept' => 'A'], ['name' => 'Bob', 'dept' => 'A'], ['name' => 'Carol', 'dept' => 'B']];
        $c = Collection::make($data)->unique('dept');
        $this->assertCount(2, $c);
    }

    public function test_unique_strict(): void
    {
        $c = Collection::make([1, '1', 2])->uniqueStrict();
        $this->assertCount(3, $c);
    }

    public function test_duplicates(): void
    {
        $c = Collection::make([1, 2, 2, 3, 3, 3])->duplicates();
        $this->assertContains(2, $c->all());
        $this->assertContains(3, $c->all());
    }

    public function test_duplicates_strict(): void
    {
        $c = Collection::make([1, '1', 1])->duplicatesStrict();
        $this->assertCount(1, $c);
    }

    // =========================================================================
    // sort / sortDesc / sortBy / sortByDesc / thenBy / thenByDesc / sortKeys / sortKeysDesc
    // =========================================================================

    public function test_sort_ascending(): void
    {
        $c = Collection::make([3, 1, 2])->sort();
        $this->assertSame([1, 2, 3], array_values($c->all()));
    }

    public function test_sort_desc(): void
    {
        $c = Collection::make([3, 1, 2])->sortDesc();
        $this->assertSame([3, 2, 1], array_values($c->all()));
    }

    public function test_sort_by_key(): void
    {
        $data = [['name' => 'Bob', 'age' => 30], ['name' => 'Alice', 'age' => 25]];
        $c = Collection::make($data)->sortBy('age');
        $this->assertSame(25, array_values($c->all())[0]['age']);
    }

    public function test_sort_by_desc_key(): void
    {
        $data = [['name' => 'Bob', 'age' => 30], ['name' => 'Alice', 'age' => 25]];
        $c = Collection::make($data)->sortByDesc('age');
        $this->assertSame(30, array_values($c->all())[0]['age']);
    }

    public function test_then_by_requires_sort_by(): void
    {
        $this->expectException(BadMethodCallException::class);
        Collection::make([1, 2, 3])->thenBy(fn($v) => $v);
    }

    public function test_then_by_sorts_secondary(): void
    {
        $data = [
            ['dept' => 'A', 'name' => 'Charlie'],
            ['dept' => 'A', 'name' => 'Alice'],
            ['dept' => 'B', 'name' => 'Bob'],
        ];
        $c = Collection::make($data)->sortBy('dept')->thenBy('name');
        $items = array_values($c->all());
        $this->assertSame('Alice', $items[0]['name']);
        $this->assertSame('Charlie', $items[1]['name']);
    }

    public function test_sort_keys(): void
    {
        $c = Collection::make(['b' => 2, 'a' => 1, 'c' => 3])->sortKeys();
        $this->assertSame(['a', 'b', 'c'], array_keys($c->all()));
    }

    public function test_sort_keys_desc(): void
    {
        $c = Collection::make(['b' => 2, 'a' => 1, 'c' => 3])->sortKeysDesc();
        $this->assertSame(['c', 'b', 'a'], array_keys($c->all()));
    }

    // =========================================================================
    // groupBy / countBy
    // =========================================================================

    public function test_group_by_key(): void
    {
        $data = [['type' => 'A', 'val' => 1], ['type' => 'B', 'val' => 2], ['type' => 'A', 'val' => 3]];
        $grouped = Collection::make($data)->groupBy('type');
        $this->assertCount(2, $grouped);
        $this->assertCount(2, $grouped->get('A'));
    }

    public function test_group_by_callback(): void
    {
        $grouped = Collection::make([1, 2, 3, 4, 5])->groupBy(fn($v) => $v % 2 === 0 ? 'even' : 'odd');
        $this->assertCount(3, $grouped->get('odd'));
        $this->assertCount(2, $grouped->get('even'));
    }

    public function test_count_by(): void
    {
        $c = Collection::make([1, 2, 3, 4])->countBy(fn($v) => $v % 2 === 0 ? 'even' : 'odd');
        $this->assertSame(['odd' => 2, 'even' => 2], $c->all());
    }

    // =========================================================================
    // pluck / flip / map / flatMap / mapSpread
    // =========================================================================

    public function test_pluck_values(): void
    {
        $data = [['name' => 'Alice'], ['name' => 'Bob']];
        $c = Collection::make($data)->pluck('name');
        $this->assertSame(['Alice', 'Bob'], $c->all());
    }

    public function test_pluck_with_key(): void
    {
        $data = [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']];
        $c = Collection::make($data)->pluck('name', 'id');
        $this->assertSame([1 => 'Alice', 2 => 'Bob'], $c->all());
    }

    public function test_flip(): void
    {
        $c = Collection::make(['a' => 1, 'b' => 2])->flip();
        $this->assertSame([1 => 'a', 2 => 'b'], $c->all());
    }

    public function test_flat_map(): void
    {
        $c = Collection::make([1, 2, 3])->flatMap(fn($v) => [$v, $v * 10]);
        $this->assertSame([1, 10, 2, 20, 3, 30], $c->all());
    }

    public function test_map_spread(): void
    {
        $c = Collection::make([[1, 2], [3, 4]])->mapSpread(fn($a, $b) => $a + $b);
        $this->assertSame([3, 7], array_values($c->all()));
    }

    // =========================================================================
    // slice / take / skip / forPage / nth / sliding / split / splitIn / chunk / chunkWhile
    // =========================================================================

    public function test_slice(): void
    {
        $c = Collection::make([1, 2, 3, 4, 5])->slice(1, 3);
        $this->assertSame([2, 3, 4], array_values($c->all()));
    }

    public function test_take_positive(): void
    {
        $c = Collection::make([1, 2, 3, 4, 5])->take(3);
        $this->assertSame([1, 2, 3], $c->all());
    }

    public function test_take_negative(): void
    {
        $c = Collection::make([1, 2, 3, 4, 5])->take(-2);
        $this->assertSame([4, 5], array_values($c->all()));
    }

    public function test_skip(): void
    {
        $c = Collection::make([1, 2, 3, 4, 5])->skip(2);
        $this->assertSame([3, 4, 5], array_values($c->all()));
    }

    public function test_for_page(): void
    {
        $c = Collection::make(range(1, 10))->forPage(2, 3);
        $this->assertSame([4, 5, 6], array_values($c->all()));
    }

    public function test_nth(): void
    {
        $c = Collection::make([1, 2, 3, 4, 5, 6])->nth(2);
        $this->assertSame([1, 3, 5], $c->all());
    }

    public function test_nth_with_offset(): void
    {
        $c = Collection::make([1, 2, 3, 4, 5, 6])->nth(2, 1);
        $this->assertSame([2, 4, 6], $c->all());
    }

    public function test_split(): void
    {
        $c = Collection::make([1, 2, 3, 4, 5])->split(2);
        $this->assertCount(2, $c);
    }

    public function test_split_in(): void
    {
        $c = Collection::make(range(1, 9))->splitIn(3);
        $this->assertCount(3, $c);
    }

    public function test_chunk(): void
    {
        $c = Collection::make([1, 2, 3, 4, 5])->chunk(2);
        $this->assertCount(3, $c);
        $this->assertSame([1, 2], $c->first()->all());
    }

    public function test_chunk_while(): void
    {
        $c = Collection::make([1, 1, 2, 2, 3])->chunkWhile(fn($v, $k, $chunk) => $v === $chunk->last());
        $this->assertCount(3, $c);
    }

    public function test_sliding(): void
    {
        $c = Collection::make([1, 2, 3, 4, 5])->sliding(3);
        $this->assertCount(3, $c);
        $this->assertSame([1, 2, 3], array_values($c->first()->all()));
    }

    // =========================================================================
    // takeUntil / takeWhile
    // =========================================================================

    public function test_take_until_callback(): void
    {
        $c = Collection::make([1, 2, 3, 4, 5])->takeUntil(fn($v) => $v >= 4);
        $this->assertSame([1, 2, 3], array_values($c->all()));
    }

    public function test_take_while_callback(): void
    {
        $c = Collection::make([1, 2, 3, 4, 5])->takeWhile(fn($v) => $v <= 3);
        $this->assertSame([1, 2, 3], array_values($c->all()));
    }

    // =========================================================================
    // reverse / random / shuffle / search
    // =========================================================================

    public function test_reverse(): void
    {
        $c = Collection::make([1, 2, 3])->reverse();
        $this->assertSame([3, 2, 1], array_values($c->all()));
    }

    public function test_shuffle_returns_same_elements(): void
    {
        $c = Collection::make([1, 2, 3, 4, 5])->shuffle(42);
        $sorted = $c->sort()->values()->all();
        $this->assertSame([1, 2, 3, 4, 5], $sorted);
    }

    public function test_search_finds_value(): void
    {
        $c = Collection::make(['a', 'b', 'c']);
        $this->assertSame(1, $c->search('b'));
    }

    public function test_search_with_callback(): void
    {
        $c = Collection::make([1, 2, 3, 4]);
        $this->assertSame(2, $c->search(fn($v) => $v === 3));
    }

    public function test_search_returns_false_when_not_found(): void
    {
        $this->assertFalse(Collection::make([1, 2, 3])->search(99));
    }

    public function test_random_single(): void
    {
        $c = Collection::make([10, 20, 30]);
        $val = $c->random(1);
        $this->assertContains($val, [10, 20, 30]);
    }

    // =========================================================================
    // implode / join
    // =========================================================================

    public function test_implode_simple(): void
    {
        $this->assertSame('1,2,3', Collection::make([1, 2, 3])->implode(','));
    }

    public function test_implode_by_key(): void
    {
        $data = [['name' => 'Alice'], ['name' => 'Bob']];
        $this->assertSame('Alice,Bob', Collection::make($data)->implode(',', 'name'));
    }

    public function test_join_without_final_glue(): void
    {
        $this->assertSame('a, b, c', Collection::make(['a', 'b', 'c'])->join(', '));
    }

    public function test_join_with_final_glue(): void
    {
        $this->assertSame('a, b and c', Collection::make(['a', 'b', 'c'])->join(', ', ' and '));
    }

    public function test_join_single_item(): void
    {
        $this->assertSame('a', Collection::make(['a'])->join(', ', ' and '));
    }

    public function test_join_empty(): void
    {
        $this->assertSame('', Collection::make([])->join(', ', ' and '));
    }

    // =========================================================================
    // flatten / collapse / combine / pad
    // =========================================================================

    public function test_flatten(): void
    {
        $c = Collection::make([1, [2, 3], [4, [5, 6]]])->flatten();
        $this->assertSame([1, 2, 3, 4, 5, 6], $c->all());
    }

    public function test_flatten_with_depth(): void
    {
        $c = Collection::make([1, [2, [3, [4]]]])->flatten(1);
        $this->assertSame([1, 2, [3, [4]]], $c->all());
    }

    public function test_collapse(): void
    {
        $c = Collection::make([[1, 2], [3, 4], [5]])->collapse();
        $this->assertSame([1, 2, 3, 4, 5], $c->all());
    }

    public function test_combine(): void
    {
        $c = Collection::make(['a', 'b', 'c'])->combine([1, 2, 3]);
        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], $c->all());
    }

    public function test_pad(): void
    {
        $c = Collection::make([1, 2])->pad(5, 0);
        $this->assertSame([1, 2, 0, 0, 0], $c->all());
    }

    // =========================================================================
    // where* methods
    // =========================================================================

    public function test_where(): void
    {
        $data = [['age' => 25], ['age' => 30], ['age' => 25]];
        $c = Collection::make($data)->where('age', 25);
        $this->assertCount(2, $c);
    }

    public function test_where_strict(): void
    {
        $data = [['val' => 1], ['val' => '1']];
        $c = Collection::make($data)->whereStrict('val', 1);
        $this->assertCount(1, $c);
    }

    public function test_where_between(): void
    {
        $data = [['age' => 20], ['age' => 25], ['age' => 30]];
        $c = Collection::make($data)->whereBetween('age', 21, 29);
        $this->assertCount(1, $c);
        $this->assertSame(25, $c->first()['age']);
    }

    public function test_where_not_between(): void
    {
        $data = [['age' => 20], ['age' => 25], ['age' => 30]];
        $c = Collection::make($data)->whereNotBetween('age', 21, 29);
        $this->assertCount(2, $c);
    }

    public function test_where_in(): void
    {
        $data = [['status' => 'a'], ['status' => 'b'], ['status' => 'c']];
        $c = Collection::make($data)->whereIn('status', ['a', 'c']);
        $this->assertCount(2, $c);
    }

    public function test_where_not_in(): void
    {
        $data = [['status' => 'a'], ['status' => 'b'], ['status' => 'c']];
        $c = Collection::make($data)->whereNotIn('status', ['a', 'c']);
        $this->assertCount(1, $c);
    }

    public function test_where_null(): void
    {
        $data = [['val' => null], ['val' => 1], ['val' => null]];
        $c = Collection::make($data)->whereNull('val');
        $this->assertCount(2, $c);
    }

    public function test_where_not_null(): void
    {
        $data = [['val' => null], ['val' => 1], ['val' => 2]];
        $c = Collection::make($data)->whereNotNull('val');
        $this->assertCount(2, $c);
    }

    public function test_where_not_empty(): void
    {
        $data = [['val' => ''], ['val' => 'hello'], ['val' => 0]];
        $c = Collection::make($data)->whereNotEmpty('val');
        $this->assertCount(1, $c);
    }

    public function test_where_instance_of(): void
    {
        $items = [new \stdClass(), 'string', new \stdClass(), 42];
        $c = Collection::make($items)->whereInstanceOf(\stdClass::class);
        $this->assertCount(2, $c);
    }

    public function test_where_like_suffix(): void
    {
        // %suffix pattern: finds items where value ends with the given suffix (strict = case-sensitive)
        $data = [['name' => 'Alice'], ['name' => 'Bob'], ['name' => 'Malice']];
        $c = Collection::make($data)->whereLike('name', '%lice', true);
        $this->assertCount(2, $c);
    }

    public function test_where_like_contains(): void
    {
        // %contains% pattern: finds items where value contains the given substring (strict = case-sensitive)
        $data = [['name' => 'Alice'], ['name' => 'Bob'], ['name' => 'Alicia']];
        $c = Collection::make($data)->whereLike('name', '%li%', true);
        $this->assertCount(2, $c);
    }

    public function test_where_like_exact(): void
    {
        // exact match (no wildcards)
        $data = [['name' => 'Alice'], ['name' => 'Bob'], ['name' => 'alice']];
        $c = Collection::make($data)->whereLike('name', 'Alice', true);
        $this->assertCount(1, $c);
        $this->assertSame('Alice', $c->first()['name']);
    }

    // =========================================================================
    // when / whenEmpty / whenNotEmpty / unless
    // =========================================================================

    public function test_when_empty_called_when_empty(): void
    {
        $called = false;
        Collection::make([])->whenEmpty(function () use (&$called) {
            $called = true;
        });
        $this->assertTrue($called);
    }

    public function test_when_not_empty_called_when_not_empty(): void
    {
        $called = false;
        Collection::make([1])->whenNotEmpty(function () use (&$called) {
            $called = true;
        });
        $this->assertTrue($called);
    }

    public function test_unless_empty(): void
    {
        $called = false;
        Collection::make([1])->unlessEmpty(function () use (&$called) {
            $called = true;
        });
        $this->assertTrue($called);
    }

    public function test_unless_not_empty(): void
    {
        $called = false;
        Collection::make([])->unlessNotEmpty(function () use (&$called) {
            $called = true;
        });
        $this->assertTrue($called);
    }

    // =========================================================================
    // wrap / unwrap / empty (static) / times / collect
    // =========================================================================

    public function test_wrap_scalar(): void
    {
        $c = Collection::wrap('hello');
        $this->assertSame(['hello'], $c->all());
    }

    public function test_wrap_collection_returns_collection(): void
    {
        $c1 = Collection::make([1, 2]);
        $c2 = Collection::wrap($c1);
        $this->assertSame([1, 2], $c2->all());
    }

    public function test_unwrap_collection(): void
    {
        $c = Collection::make([1, 2, 3]);
        $this->assertSame([1, 2, 3], Collection::unwrap($c));
    }

    public function test_unwrap_plain_value(): void
    {
        $this->assertSame(42, Collection::unwrap(42));
    }

    public function test_empty_static_returns_empty_collection(): void
    {
        $c = Collection::empty();
        $this->assertTrue($c->isEmpty());
    }

    public function test_times_creates_collection_by_invoking_callback(): void
    {
        $c = Collection::times(3, fn($n) => $n * 10);
        $this->assertSame([10, 20, 30], $c->all());
    }

    public function test_collect_returns_collection(): void
    {
        $c = Collection::make([1, 2, 3])->collect();
        $this->assertInstanceOf(Collection::class, $c);
        $this->assertSame([1, 2, 3], $c->all());
    }

    // =========================================================================
    // lazy / toArray / toJson / ArrayAccess / getIterator
    // =========================================================================

    public function test_lazy_returns_lazy_collection(): void
    {
        $c = Collection::make([1, 2, 3])->lazy();
        $this->assertInstanceOf(LazyCollection::class, $c);
    }

    public function test_to_array(): void
    {
        $c = Collection::make([1, 2, 3]);
        $this->assertSame([1, 2, 3], $c->toArray());
    }

    public function test_to_array_nested_collections(): void
    {
        $c = Collection::make([Collection::make([1, 2]), Collection::make([3, 4])]);
        $this->assertSame([[1, 2], [3, 4]], $c->toArray());
    }

    public function test_to_json(): void
    {
        $c = Collection::make(['a' => 1, 'b' => 2]);
        $this->assertSame('{"a":1,"b":2}', $c->toJson());
    }

    public function test_array_access_get_set(): void
    {
        $c = Collection::make(['a' => 1]);
        $this->assertSame(1, $c['a']);
        $c['b'] = 2;
        $this->assertSame(2, $c['b']);
    }

    public function test_array_access_isset_unset(): void
    {
        $c = Collection::make(['a' => 1]);
        $this->assertTrue(isset($c['a']));
        unset($c['a']);
        $this->assertFalse(isset($c['a']));
    }

    public function test_get_iterator(): void
    {
        $c = Collection::make([1, 2, 3]);
        $items = [];
        foreach ($c as $v) {
            $items[] = $v;
        }
        $this->assertSame([1, 2, 3], $items);
    }
}
