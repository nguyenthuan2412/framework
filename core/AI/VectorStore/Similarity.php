<?php

namespace Ryra\Core\AI\VectorStore;

use Ryra\Core\AI\Exceptions\AIException;

class Similarity
{
    /**
     * Độ tương đồng cosine, giá trị trong khoảng [-1, 1].
     * Đây là phép đo mặc định cho embedding vì không phụ thuộc độ dài vector.
     *
     * @param array<int, float> $a
     * @param array<int, float> $b
     */
    public static function cosine(array $a, array $b): float
    {
        static::guardSameSize($a, $b);

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $index => $value) {
            $other = $b[$index];
            $dot += $value * $other;
            $normA += $value * $value;
            $normB += $other * $other;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    /**
     * @param array<int, float> $a
     * @param array<int, float> $b
     */
    public static function dot(array $a, array $b): float
    {
        static::guardSameSize($a, $b);

        $dot = 0.0;

        foreach ($a as $index => $value) {
            $dot += $value * $b[$index];
        }

        return $dot;
    }

    /**
     * Khoảng cách Euclid đổi dấu để dùng chung quy ước "càng lớn càng giống".
     *
     * @param array<int, float> $a
     * @param array<int, float> $b
     */
    public static function euclidean(array $a, array $b): float
    {
        static::guardSameSize($a, $b);

        $sum = 0.0;

        foreach ($a as $index => $value) {
            $diff = $value - $b[$index];
            $sum += $diff * $diff;
        }

        return -sqrt($sum);
    }

    /**
     * @param array<int, float> $a
     * @param array<int, float> $b
     */
    protected static function guardSameSize(array $a, array $b): void
    {
        if (count($a) !== count($b)) {
            throw new AIException(sprintf(
                'Vector dimensions do not match: %d vs %d. Nhiều khả năng dữ liệu được nhúng bằng model khác.',
                count($a),
                count($b),
            ));
        }
    }
}
