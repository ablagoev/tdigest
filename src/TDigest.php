<?php

namespace ablagoev\TDigest;

use ablagoev\TDigest\Centroid;

class TDigest
{
    protected array $centroids = [];
    protected int $maxSize = 100;
    protected float $sum = 0.0;
    protected int $count = 0;
    protected float $max = -INF;
    protected float $min = INF;

    public function __construct(int $maxSize = 100)
    {
        $this->maxSize = $maxSize;
    }

    public static function createFromCentroids(array $centroids, $sum, $count, $max, $min, $maxSize): TDigest
    {
        $digest = null;
        if (count($centroids) <= $maxSize) {
            $digest = new TDigest($maxSize);
            $digest->sum = $sum;
            $digest->count = $count;
            $digest->max = $max;
            $digest->min = $min;

            Centroid::sort($centroids);
            $digest->centroids = $centroids;
        } else {
            // Number of centroids is greater than maxSize, we need to compress them
            // When merging, resulting digest takes the maxSize of the first digest
            $size = count($centroids);
            $digests = [new TDigest($maxSize), TDigest::createFromCentroids($centroids, $sum, $count, $max, $min, $size)];

            $digest = TDigest::merge($digests);
        }

        return $digest;
    }

    /**
     * @throws RuntimeException
     */
    public static function createFromArray(array $digest)
    {
        $fields = ['centroids', 'sum', 'count', 'size'];
        foreach ($fields as &$field) {
            if (!isset($digest[$field])) {
                throw new \RuntimeException('TDigest array should contain a ' . $field . '  key.');
            }
        }

        if (
            count($digest['centroids']) > 0
            && (!isset($digest['min']) || !isset($digest['max']))
        ) {
                throw new \RuntimeException('TDigest array contains centroids but is missing the min/max fields.');
        }

        if (!isset($digest['max'])) {
            $digest['max'] = -INF;
        }

        if (!isset($digest['min'])) {
            $digest['min'] = INF;
        }

        $centroids = [];
        foreach ($digest['centroids'] as &$centroid) {
            if (!isset($centroid['mean']) || !isset($centroid['weight'])) {
                throw new \RuntimeException('TDigest Centroid array does not contain a mean or a weight.');
            }

            $centroids[] = new Centroid($centroid['mean'], $centroid['weight']);
        }

        return self::createFromCentroids($centroids, $digest['sum'], $digest['count'], $digest['max'], $digest['min'], $digest['size']);
    }

    public static function createFromJson(string $json)
    {
        $digest = json_decode($json, true);
        return self::createFromArray($digest);
    }

    public function toArray(): array
    {
        $data = [
            'centroids' => array_map(fn($c) => $c->toArray(), $this->centroids),
            'sum' => $this->sum,
            'count' => $this->count,
            'max' => $this->max,
            'min' => $this->min,
            'size' => $this->maxSize,
        ];

        // Handle inf variables properly
        if ($data['max'] == -INF) {
            unset($data['max']);
        }
        if ($data['min'] == INF) {
            unset($data['min']);
        }

        return $data;
    }

    public function toJson(): string
    {
        $data = $this->toArray();
        return json_encode($data);
    }

    public function addValues(array $values): void
    {
        sort($values);
        $this->addSortedValues($values);
    }

    public function addSortedValues(array &$values): void
    {
        $valuesLength = count($values);
        if ($valuesLength == 0) {
            return;
        }

        $this->sum = 0;
        $this->count = $this->count + $valuesLength;

        $maybeMin = $values[0];
        $maybeMax = $values[$valuesLength - 1];

        if ($this->count > 0) {
            $this->min = min($this->min, $maybeMin);
            $this->max = max($this->max, $maybeMax);
        } else {
            $this->min = $maybeMin;
            $this->max = $maybeMax;
        }

        $compressed = [];
        $kLimit = 1.0;
        $qLimitTimesCount = self::kToQ($kLimit++, $this->maxSize) * $this->count;

        $centroidIndex = 0;
        $valueIndex = 0;
        $centroidsLength = count($this->centroids);
        $current = null;
        if ($centroidIndex != $centroidsLength && $this->centroids[$centroidIndex]->mean() < $values[$valueIndex]) {
            $current = &$this->centroids[$centroidIndex];
            $centroidIndex += 1;
        } else {
            $current = new Centroid($values[$valueIndex], 1.0);
            $valueIndex += 1;
        }

        $weightSoFar = $current->weight();
        $sumsToMerge = 0.0;
        $weightsToMerge = 0.0;

        while ($centroidIndex != $centroidsLength || $valueIndex != $valuesLength) {
            $next = null;
            if (
                $centroidIndex != $centroidsLength
                && ($valueIndex == $valuesLength || $this->centroids[$centroidIndex]->mean() < $values[$valueIndex])
            ) {
                $next = &$this->centroids[$centroidIndex];
                $centroidIndex += 1;
            } else {
                $next = new Centroid($values[$valueIndex], 1.0);
                $valueIndex += 1;
            }

            $nextSum = $next->mean() * $next->weight();
            $weightSoFar += $next->weight();

            if ($weightSoFar <= $qLimitTimesCount) {
                $sumsToMerge += $nextSum;
                $weightsToMerge += $next->weight();
            } else {
                $this->sum += $current->add($sumsToMerge, $weightsToMerge);
                $sumsToMerge = 0.0;
                $weightsToMerge = 0.0;
                $compressed[] = $current;
                $qLimitTimesCount = self::kToQ($kLimit++, $this->maxSize) * $this->count;
                $current = $next;
            }
        }

        $this->sum += $current->add($sumsToMerge, $weightsToMerge);
        $compressed[] = $current;

        Centroid::sort($compressed);
        $this->centroids = $compressed;
    }

    public static function merge(array &$digests): TDigest
    {
        $nCentroids = 0;
        foreach ($digests as &$digest) {
            $nCentroids += count($digest->centroids);
        }

        if ($nCentroids == 0) {
            return new TDigest();
        }

        $centroids = [];
        $indexes = [];
        $count = 0.0;

        $min = INF;
        $max = -INF;

        foreach ($digests as &$digest) {
            $currentCount = $digest->count();
            if ($currentCount > 0) {
                $min = min($min, $digest->min());
                $max = max($max, $digest->max());
                $count += $currentCount;

                foreach ($digest->centroids as &$centroid) {
                    $centroids[] = &$centroid;
                }
            }
        }

        Centroid::sort($centroids);
        $maxSize = $digests[0]->maxSize;
        $result = new TDigest($maxSize);

        $compressed = [];
        $kLimit = 1.0;
        $qLimitTimesCount = self::kToQ($kLimit, $maxSize) * $count;

        $current = &$centroids[0];
        $weightSoFar = $current->weight();
        $sumsToMerge = 0.0;
        $weightsToMerge = 0.0;

        foreach ($centroids as $index => &$centroid) {
            if ($index == 0) {
                continue;
            }

            $weightSoFar += $centroid->weight();
            if ($weightSoFar <= $qLimitTimesCount) {
                $sumsToMerge += $centroid->mean() * $centroid->weight();
                $weightsToMerge += $centroid->weight();
            } else {
                $result->sum += $current->add($sumsToMerge, $weightsToMerge);
                $sumsToMerge = 0.0;
                $weightsToMerge = 0.0;
                $compressed[] = $current;
                $qLimitTimesCount = self::kToQ($kLimit++, $maxSize) * $count;
                $current = &$centroid;
            }
        }

        $result->sum += $current->add($sumsToMerge, $weightsToMerge);
        $compressed[] = $current;

        Centroid::sort($compressed);
        $result->count = $count;
        $result->min = $min;
        $result->max = $max;
        $result->centroids = $compressed;

        return $result;
    }

    public function quantile(float $quantile): float
    {
        if ($this->empty()) {
            return 0.0;
        }

        $centroidLength = count($this->centroids);
        $rank = $quantile * $this->count;
        $position = 0;
        $t = 0.0;

        if ($quantile > 0.5) {
            if ($quantile >= 1.0) {
                return $this->max;
            }

            $t = $this->count;
            for ($index = $centroidLength - 1; $index > 0; --$index) {
                $centroid = &$this->centroids[$index];
                $t -= $centroid->weight();
                if ($rank >= $t) {
                    $position = $index;
                    break;
                }
            }
        } else {
            if ($quantile <= 0.0) {
                return $this->min;
            }

            $position = $centroidLength - 1;
            foreach ($this->centroids as $index => &$centroid) {
                if ($rank < $t + $centroid->weight()) {
                    $position = $index;
                    break;
                }

                $t += $centroid->weight();
            }
        }

        $delta = 0.0;
        $min = $this->min;
        $max = $this->max;

        if ($centroidLength > 1) {
            if ($position == 0) {
                $delta = $this->centroids[$position + 1]->mean() - $this->centroids[$position]->mean();
                $max = $this->centroids[$position + 1]->mean();
            } elseif ($position == $centroidLength - 1) {
                $delta = $this->centroids[$position]->mean() - $this->centroids[$position - 1]->mean();
                $min = $this->centroids[$position - 1]->mean();
            } else {
                $delta = ($this->centroids[$position + 1]->mean() - $this->centroids[$position - 1]->mean()) / 2.0;
                $min = $this->centroids[$position - 1]->mean();
                $max = $this->centroids[$position + 1]->mean();
            }
        }

        $value = $this->centroids[$position]->mean() + (($rank - $t) / $this->centroids[$position]->weight() - 0.5) * $delta;
        return $this->clamp($value, $min, $max);
    }

    public function mean(): float
    {
        return $this->count > 0 ? $this->sum / $this->count : 0;
    }

    public function sum(): float
    {
        return $this->sum;
    }

    public function count(): float
    {
        return $this->count;
    }

    public function min(): float
    {
        return $this->min;
    }

    public function max(): float
    {
        return $this->max;
    }

    public function empty(): bool
    {
        return count($this->centroids) == 0;
    }

    public function centroids(): array
    {
        return $this->centroids;
    }

    public function maxSize(): int
    {
        return $this->maxSize;
    }

    protected static function kToQ(float $kLimit, int $maxSize): float
    {
        $kDivSize = $kLimit / $maxSize;

        if ($kDivSize >= 0.5) {
            $base = 1.0 - $kDivSize;
            return 1.0 - 2.0 * $base * $base;
        } else {
            return 2.0 * $kDivSize * $kDivSize;
        }
    }

    protected function clamp(float $value, float $low, float $high): float
    {
        if ($value > $high) {
            return $high;
        } elseif ($value < $low) {
            return $low;
        }

        return $value;
    }
}
