<?php

namespace Ryra\Core\Collection\Tests;

use PHPUnit\Framework\TestCase;
use Ryra\Core\Collection\Arr;
use Ryra\Core\Collection\Collection;
use InvalidArgumentException;
use ArrayObject;

class ArrTest extends TestCase
{
    // =========================================================================
    // accessible
    // =========================================================================

    public function test_accessible_with_plain_array(): void
    {
        $this->assertTrue(Arr::accessible([]));
        $this->assertTrue(Arr::accessible([1, 2, 3]));
    }

    public function test_accessible_with_array_access(): void
    {
        $this->assertTrue(Arr::accessible(new ArrayObject([1, 2])));
    }

    public function test_accessible_with_non_array(): void
    {
        $this->assertFalse(Arr::accessible('string'));
        $this->assertFalse(Arr::accessible(42));
        $this->assertFalse(Arr::accessible(null));
        $this->assertFalse(Arr::accessible(new \stdClass()));
    }

    // =========================================================================
    // exists
    // =========================================================================

    public function test_exists_returns_true_for_present_key(): void
    {
        $this->assertTrue(Arr::exists(['a' => 1], 'a'));
        $this->assertTrue(Arr::exists([0 => 'x'], 0));
    }

    public function test_exists_returns_false_for_missing_key(): void
    {
        $this->assertFalse(Arr::exists(['a' => 1], 'b'));
    }

    // =========================================================================
    // get
    // =========================================================================

    public function test_get_returns_value_by_key(): void
    {
        $this->assertSame(1, Arr::get(['a' => 1], 'a'));
    }

    public function test_get_returns_default_for_missing_key(): void
    {
        $this->assertNull(Arr::get(['a' => 1], 'b'));
        $this->assertSame('default', Arr::get(['a' => 1], 'b', 'default'));
    }

    public function test_get_uses_dot_notation(): void
    {
        $array = ['user' => ['name' => 'John', 'age' => 30]];
        $this->assertSame('John', Arr::get($array, 'user.name'));
        $this->assertSame(30, Arr::get($array, 'user.age'));
    }

    public function test_get_dot_notation_returns_default_on_missing(): void
    {
        $array = ['user' => ['name' => 'John']];
        $this->assertSame('N/A', Arr::get($array, 'user.email', 'N/A'));
    }

    public function test_get_with_callable_default(): void
    {
        $result = Arr::get([], 'missing', fn() => 'computed');
        $this->assertSame('computed', $result);
    }

    // =========================================================================
    // has
    // =========================================================================

    public function test_has_returns_true_for_existing_key(): void
    {
        $this->assertTrue(Arr::has(['a' => 1], 'a'));
    }

    public function test_has_returns_false_for_missing_key(): void
    {
        $this->assertFalse(Arr::has(['a' => 1], 'b'));
    }

    public function test_has_with_dot_notation(): void
    {
        $array = ['user' => ['name' => 'John']];
        $this->assertTrue(Arr::has($array, 'user.name'));
        $this->assertFalse(Arr::has($array, 'user.email'));
    }

    public function test_has_with_multiple_keys(): void
    {
        $array = ['a' => 1, 'b' => 2];
        $this->assertTrue(Arr::has($array, ['a', 'b']));
        $this->assertFalse(Arr::has($array, ['a', 'c']));
    }

    public function test_has_returns_false_on_empty_array(): void
    {
        $this->assertFalse(Arr::has([], 'a'));
        $this->assertFalse(Arr::has(['a' => 1], []));
    }

    // =========================================================================
    // push
    // =========================================================================

    public function test_push_appends_to_array(): void
    {
        $array = [1, 2];
        Arr::push($array, 3);
        $this->assertSame([1, 2, 3], $array);
    }

    // =========================================================================
    // prepend
    // =========================================================================

    public function test_prepend_without_key(): void
    {
        $array = [1, 2, 3];
        Arr::prepend($array, 0);
        $this->assertSame([0, 1, 2, 3], $array);
    }

    public function test_prepend_with_key(): void
    {
        $array = ['b' => 2];
        Arr::prepend($array, 1, 'a');
        $this->assertSame(['a' => 1, 'b' => 2], $array);
    }

    // =========================================================================
    // set
    // =========================================================================

    public function test_set_simple_key(): void
    {
        $array = [];
        Arr::set($array, 'name', 'John');
        $this->assertSame('John', $array['name']);
    }

    public function test_set_dot_notation_creates_nested(): void
    {
        $array = [];
        Arr::set($array, 'user.name', 'John');
        $this->assertSame('John', $array['user']['name']);
    }

    public function test_set_null_key_replaces_entire_array(): void
    {
        $array = ['old' => 'data'];
        Arr::set($array, null, ['new' => 'data']);
        $this->assertSame(['new' => 'data'], $array);
    }

    // =========================================================================
    // forget
    // =========================================================================

    public function test_forget_removes_key(): void
    {
        $array = ['a' => 1, 'b' => 2];
        Arr::forget($array, ['a']);
        $this->assertArrayNotHasKey('a', $array);
        $this->assertArrayHasKey('b', $array);
    }

    public function test_forget_with_dot_notation(): void
    {
        $array = ['user' => ['name' => 'John', 'age' => 30]];
        Arr::forget($array, ['user.name']);
        $this->assertArrayNotHasKey('name', $array['user']);
        $this->assertArrayHasKey('age', $array['user']);
    }

    // =========================================================================
    // except
    // =========================================================================

    public function test_except_returns_array_without_keys(): void
    {
        $result = Arr::except(['a' => 1, 'b' => 2, 'c' => 3], ['a', 'c']);
        $this->assertSame(['b' => 2], $result);
    }

    // =========================================================================
    // pull
    // =========================================================================

    public function test_pull_returns_and_removes_value(): void
    {
        $array = ['a' => 1, 'b' => 2];
        $value = Arr::pull($array, 'a');
        $this->assertSame(1, $value);
        $this->assertArrayNotHasKey('a', $array);
    }

    public function test_pull_returns_default_for_missing_key(): void
    {
        $array = ['a' => 1];
        $this->assertSame('default', Arr::pull($array, 'missing', 'default'));
    }

    // =========================================================================
    // keys / values
    // =========================================================================

    public function test_keys_returns_array_keys(): void
    {
        $this->assertSame(['a', 'b'], Arr::keys(['a' => 1, 'b' => 2]));
    }

    public function test_values_returns_array_values(): void
    {
        $this->assertSame([1, 2], Arr::values(['a' => 1, 'b' => 2]));
    }

    // =========================================================================
    // count / empty
    // =========================================================================

    public function test_count_returns_number_of_elements(): void
    {
        $this->assertSame(3, Arr::count([1, 2, 3]));
        $this->assertSame(0, Arr::count([]));
    }

    public function test_empty_returns_true_for_empty_array(): void
    {
        $this->assertTrue(Arr::empty([]));
        $this->assertFalse(Arr::empty([1]));
    }

    // =========================================================================
    // countBy
    // =========================================================================

    public function test_count_by_without_callback(): void
    {
        $result = Arr::countBy(['a', 'b', 'a', 'c', 'a']);
        $this->assertSame(['a' => 3, 'b' => 1, 'c' => 1], $result);
    }

    public function test_count_by_with_callback(): void
    {
        $result = Arr::countBy([1, 2, 3, 4], fn($n) => $n % 2 === 0 ? 'even' : 'odd');
        $this->assertSame(['odd' => 2, 'even' => 2], $result);
    }

    // =========================================================================
    // diff / diffAssoc / diffKeys
    // =========================================================================

    public function test_diff(): void
    {
        $result = Arr::diff([1, 2, 3, 4], [2, 4]);
        $this->assertSame([0 => 1, 2 => 3], $result);
    }

    public function test_diff_assoc(): void
    {
        $result = Arr::diffAssoc(['a' => 1, 'b' => 2], ['a' => 1, 'c' => 3]);
        $this->assertSame(['b' => 2], $result);
    }

    public function test_diff_keys(): void
    {
        $result = Arr::diffKeys(['a' => 1, 'b' => 2, 'c' => 3], ['a' => 0, 'c' => 0]);
        $this->assertSame(['b' => 2], $result);
    }

    // =========================================================================
    // pad
    // =========================================================================

    public function test_pad_to_larger_size(): void
    {
        $result = Arr::pad([1, 2], 5, 0);
        $this->assertSame([1, 2, 0, 0, 0], $result);
    }

    public function test_pad_negative_pads_left(): void
    {
        $result = Arr::pad([1, 2], -5, 0);
        $this->assertSame([0, 0, 0, 1, 2], $result);
    }

    // =========================================================================
    // random / randomKey
    // =========================================================================

    public function test_random_returns_single_value(): void
    {
        $array = [10, 20, 30, 40, 50];
        $result = Arr::random($array, 1);
        $this->assertContains($result, $array);
    }

    public function test_random_returns_multiple_values(): void
    {
        $array = [10, 20, 30, 40, 50];
        $result = Arr::random($array, 3);
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
    }

    public function test_random_throws_when_num_exceeds_count(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Arr::random([1, 2], 5);
    }

    public function test_random_key_returns_key(): void
    {
        $array = ['a' => 1, 'b' => 2, 'c' => 3];
        $key = Arr::randomKey($array, 1);
        $this->assertArrayHasKey($key, $array);
    }

    // =========================================================================
    // replace / replaceRecursive
    // =========================================================================

    public function test_replace(): void
    {
        $result = Arr::replace(['a' => 1, 'b' => 2], ['b' => 99, 'c' => 3]);
        $this->assertSame(['a' => 1, 'b' => 99, 'c' => 3], $result);
    }

    public function test_replace_recursive(): void
    {
        $result = Arr::replaceRecursive(
            ['a' => ['x' => 1, 'y' => 2]],
            ['a' => ['y' => 99, 'z' => 3]]
        );
        $this->assertSame(['a' => ['x' => 1, 'y' => 99, 'z' => 3]], $result);
    }

    // =========================================================================
    // only
    // =========================================================================

    public function test_only_returns_specified_keys(): void
    {
        $result = Arr::only(['a' => 1, 'b' => 2, 'c' => 3], ['a', 'c']);
        $this->assertSame(['a' => 1, 'c' => 3], $result);
    }

    // =========================================================================
    // max / min
    // =========================================================================

    public function test_max_returns_highest_value(): void
    {
        $this->assertSame(5, Arr::max([1, 2, 3, 4, 5]));
    }

    public function test_min_returns_lowest_value(): void
    {
        $this->assertSame(1, Arr::min([3, 1, 2]));
    }

    // =========================================================================
    // map
    // =========================================================================

    public function test_map_applies_callback(): void
    {
        $result = Arr::map(fn($v) => $v * 2, [1, 2, 3]);
        $this->assertSame([0 => 2, 1 => 4, 2 => 6], $result);
    }

    public function test_map_preserves_keys(): void
    {
        $result = Arr::map(fn($v) => $v + 10, ['a' => 1, 'b' => 2]);
        $this->assertSame(['a' => 11, 'b' => 12], $result);
    }

    public function test_map_with_key_callback(): void
    {
        $result = Arr::map(fn($v, $k) => "$k:$v", ['x' => 1, 'y' => 2]);
        $this->assertSame(['x' => 'x:1', 'y' => 'y:2'], $result);
    }

    // =========================================================================
    // merge / mergeRecursive
    // =========================================================================

    public function test_merge_combines_arrays(): void
    {
        $result = Arr::merge(['a' => 1], ['b' => 2]);
        $this->assertSame(['a' => 1, 'b' => 2], $result);
    }

    public function test_merge_overwrites_keys(): void
    {
        $result = Arr::merge(['a' => 1], ['a' => 99]);
        $this->assertSame(['a' => 99], $result);
    }

    public function test_merge_recursive(): void
    {
        $result = Arr::mergeRecursive(['a' => [1]], ['a' => [2]]);
        $this->assertSame(['a' => [1, 2]], $result);
    }

    // =========================================================================
    // pluck
    // =========================================================================

    public function test_pluck_values(): void
    {
        $data = [
            ['name' => 'Alice', 'age' => 25],
            ['name' => 'Bob',   'age' => 30],
        ];
        $this->assertSame(['Alice', 'Bob'], Arr::pluck($data, 'name'));
    }

    public function test_pluck_with_key(): void
    {
        $data = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];
        $result = Arr::pluck($data, 'name', 'id');
        $this->assertSame([1 => 'Alice', 2 => 'Bob'], $result);
    }

    // =========================================================================
    // unique
    // =========================================================================

    public function test_unique_removes_duplicates(): void
    {
        $result = Arr::unique([1, 2, 2, 3, 3, 3]);
        $this->assertSame([0 => 1, 1 => 2, 3 => 3], $result);
    }

    // =========================================================================
    // first / last
    // =========================================================================

    public function test_first_returns_first_element(): void
    {
        $this->assertSame(1, Arr::first([1, 2, 3]));
    }

    public function test_first_with_callback(): void
    {
        $result = Arr::first([1, 2, 3, 4], fn($v) => $v > 2);
        $this->assertSame(3, $result);
    }

    public function test_first_returns_default_when_empty(): void
    {
        $this->assertSame('default', Arr::first([], null, 'default'));
    }

    public function test_last_returns_last_element(): void
    {
        $this->assertSame(3, Arr::last([1, 2, 3]));
    }

    public function test_last_with_callback(): void
    {
        $result = Arr::last([1, 2, 3, 4], fn($v) => $v < 3);
        $this->assertSame(2, $result);
    }

    public function test_last_returns_default_when_empty(): void
    {
        $this->assertSame('default', Arr::last([], null, 'default'));
    }

    // =========================================================================
    // sort / sortKeys
    // =========================================================================

    public function test_sort_ascending(): void
    {
        $array = [3, 1, 2];
        Arr::sort($array);
        $this->assertSame([1, 2, 3], $array);
    }

    public function test_sort_descending(): void
    {
        $array = [3, 1, 2];
        Arr::sort($array, true);
        $this->assertSame([3, 2, 1], $array);
    }

    public function test_sort_keys_ascending(): void
    {
        $array = ['b' => 2, 'a' => 1, 'c' => 3];
        Arr::sortKeys($array);
        $this->assertSame(['a', 'b', 'c'], array_keys($array));
    }

    public function test_sort_keys_descending(): void
    {
        $array = ['b' => 2, 'a' => 1, 'c' => 3];
        Arr::sortKeys($array, true);
        $this->assertSame(['c', 'b', 'a'], array_keys($array));
    }

    // =========================================================================
    // search
    // =========================================================================

    public function test_search_returns_key_of_value(): void
    {
        $this->assertSame(1, Arr::search('b', ['a', 'b', 'c']));
    }

    public function test_search_returns_false_when_not_found(): void
    {
        $this->assertFalse(Arr::search('z', ['a', 'b', 'c']));
    }

    public function test_search_strict(): void
    {
        $this->assertFalse(Arr::search('1', [0, 1, 2], true));
        $this->assertSame(1, Arr::search(1, [0, 1, 2], true));
    }

    // =========================================================================
    // flip
    // =========================================================================

    public function test_flip_swaps_keys_and_values(): void
    {
        $result = Arr::flip(['a' => 1, 'b' => 2]);
        $this->assertSame([1 => 'a', 2 => 'b'], $result);
    }

    // =========================================================================
    // query
    // =========================================================================

    public function test_query_builds_query_string(): void
    {
        $result = Arr::query(['name' => 'John', 'age' => 30]);
        $this->assertStringContainsString('name=John', $result);
        $this->assertStringContainsString('age=30', $result);
    }

    // =========================================================================
    // implode
    // =========================================================================

    public function test_implode_joins_with_glue(): void
    {
        $this->assertSame('a,b,c', Arr::implode(',', ['a', 'b', 'c']));
    }

    // =========================================================================
    // intersect
    // =========================================================================

    public function test_intersect_returns_common_values(): void
    {
        $result = Arr::intersect([1, 2, 3, 4], [2, 4, 6]);
        $this->assertSame([1 => 2, 3 => 4], $result);
    }

    // =========================================================================
    // where / whereNotNull / whereNotEmpty / whereNotBlank
    // =========================================================================

    public function test_where_filters_by_callback(): void
    {
        $result = Arr::where([1, 2, 3, 4, 5], fn($v) => $v > 3);
        $this->assertSame([3 => 4, 4 => 5], $result);
    }

    public function test_where_not_null(): void
    {
        $result = Arr::whereNotNull(['a' => 1, 'b' => null, 'c' => 3]);
        $this->assertSame(['a' => 1, 'c' => 3], $result);
    }

    public function test_where_not_empty(): void
    {
        $result = Arr::whereNotEmpty(['a' => 1, 'b' => '', 'c' => 0, 'd' => 'x']);
        $this->assertSame(['a' => 1, 'd' => 'x'], $result);
    }

    public function test_where_not_blank(): void
    {
        $result = Arr::whereNotBlank(['a' => 'hello', 'b' => '   ', 'c' => '', 'd' => 'world']);
        $this->assertSame(['a' => 'hello', 'd' => 'world'], $result);
    }

    // =========================================================================
    // flatten
    // =========================================================================

    public function test_flatten_multi_dimensional(): void
    {
        $result = Arr::flatten([1, [2, 3], [4, [5, 6]]]);
        $this->assertSame([1, 2, 3, 4, 5, 6], $result);
    }

    public function test_flatten_with_depth(): void
    {
        $result = Arr::flatten([1, [2, [3, [4]]]], 2);
        $this->assertSame([1, 2, 3, [4]], $result);
    }

    public function test_flatten_with_collection_items(): void
    {
        $collection = new Collection([4, 5]);
        $result = Arr::flatten([1, 2, 3, $collection]);
        $this->assertSame([1, 2, 3, 4, 5], $result);
    }

    // =========================================================================
    // collapse
    // =========================================================================

    public function test_collapse_merges_nested_arrays(): void
    {
        $result = Arr::collapse([[1, 2], [3, 4], [5]]);
        $this->assertSame([1, 2, 3, 4, 5], $result);
    }

    // =========================================================================
    // combine
    // =========================================================================

    public function test_combine_keys_and_values(): void
    {
        $result = Arr::combine(['a', 'b', 'c'], [1, 2, 3]);
        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], $result);
    }

    // =========================================================================
    // shuffle
    // =========================================================================

    public function test_shuffle_returns_same_elements(): void
    {
        $array = [1, 2, 3, 4, 5];
        $shuffled = Arr::shuffle($array, 42);
        sort($shuffled);
        $this->assertSame([1, 2, 3, 4, 5], $shuffled);
    }

    public function test_shuffle_with_seed_is_deterministic(): void
    {
        $array = [1, 2, 3, 4, 5];
        $a = Arr::shuffle($array, 999);
        $b = Arr::shuffle($array, 999);
        $this->assertSame($a, $b);
    }

    // =========================================================================
    // slice
    // =========================================================================

    public function test_slice_extracts_portion(): void
    {
        $this->assertSame([2, 3], Arr::slice([1, 2, 3, 4], 1, 2));
    }

    public function test_slice_preserves_keys(): void
    {
        $result = Arr::slice([1, 2, 3, 4], 1, 2, true);
        $this->assertSame([1 => 2, 2 => 3], $result);
    }

    // =========================================================================
    // wrap
    // =========================================================================

    public function test_wrap_scalar_in_array(): void
    {
        $this->assertSame(['hello'], Arr::wrap('hello'));
        $this->assertSame([42], Arr::wrap(42));
    }

    public function test_wrap_array_returns_same(): void
    {
        $this->assertSame([1, 2], Arr::wrap([1, 2]));
    }

    public function test_wrap_null_returns_empty_array(): void
    {
        $this->assertSame([], Arr::wrap(null));
    }
}
