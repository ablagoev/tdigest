<?php

namespace ablagoev\Tdigest\Test;

use MathPHP\Probability\Distribution\Continuous;
use PHPUnit\Framework\TestCase;
use ablagoev\TDigest\TDigest;
use ablagoev\TDigest\Centroid;

class TDigestTest extends TestCase
{
    public function test_base_case()
    {
        $digest = new TDigest(100);
        $values = [];
        for ($i = 1; $i <= 100; $i++) {
            $values[] = $i;
        }

        $digest->addSortedValues($values);

        $this->assertEquals(100, $digest->count());
        $this->assertEquals(5050, $digest->sum());
        $this->assertEquals(50.5, $digest->mean());
        $this->assertEquals(1, $digest->min());
        $this->assertEquals(100, $digest->max());

        $this->assertEquals(1, $digest->quantile(0.001));
        $this->assertEquals(2.0 - 0.5, $digest->quantile(0.01));
        $this->assertEquals(50.375, $digest->quantile(0.5));
        $this->assertEquals(100.0 - 0.5, $digest->quantile(0.99));
        $this->assertEquals(100, $digest->quantile(0.999));
    }

    public function test_addSortedValues()
    {
        $digest = new TDigest(100);
        $values = [];
        for ($i = 1; $i <= 100; $i++) {
            $values[] = $i;
        }
        $digest->addSortedValues($values);

        $values = [];
        for ($i = 101; $i <= 200; $i++) {
            $values[] = $i;
        }
        $digest->addSortedValues($values);

        $this->assertEquals(200, $digest->count());
        $this->assertEquals(20100, $digest->sum());
        $this->assertEquals(100.5, $digest->mean());
        $this->assertEquals(1, $digest->min());
        $this->assertEquals(200, $digest->max());

        $this->assertEquals(1, $digest->quantile(0.001));
        $this->assertEquals(4.0 - 1.5, $digest->quantile(0.01));
        $this->assertEquals(100.25, $digest->quantile(0.5));
        $this->assertEquals(200.0 - 1.5, $digest->quantile(0.99));
        $this->assertEquals(200, $digest->quantile(0.999));
    }

    public function test_addSortedValues_small()
    {
        $digest = new TDigest(100);
        $values = [1];
        $digest->addSortedValues($values);

        $this->assertEquals(1, $digest->count());
        $this->assertEquals(1, $digest->sum());
        $this->assertEquals(1, $digest->mean());
        $this->assertEquals(1, $digest->min());
        $this->assertEquals(1, $digest->max());

        $this->assertEquals(1.0, $digest->quantile(0.001));
        $this->assertEquals(1.0, $digest->quantile(0.01));
        $this->assertEquals(1.0, $digest->quantile(0.5));
        $this->assertEquals(1.0, $digest->quantile(0.99));
        $this->assertEquals(1.0, $digest->quantile(0.999));
    }

    public function test_addSortedValues_large()
    {
        $digest = new TDigest(100);

        for ($i = 1; $i <= 1000; $i++) {
            $values[] = $i;
        }
        $digest->addSortedValues($values);

        $this->assertEquals(1000, $digest->count());
        $this->assertEquals(500500, $digest->sum());
        $this->assertEquals(500.5, $digest->mean());
        $this->assertEquals(1, $digest->min());
        $this->assertEquals(1000, $digest->max());

        $this->assertEquals(1.5, $digest->quantile(0.001));
        $this->assertEquals(10.5, $digest->quantile(0.01));
        $this->assertEquals(500.25, $digest->quantile(0.5));
        $this->assertEquals(990.25, $digest->quantile(0.99));
        $this->assertEquals(999.5, $digest->quantile(0.999));
    }

    public function test_addUnsortedValues_large()
    {
        $digest = new TDigest(100);

        for ($i = 1; $i <= 1000; $i++) {
            $values[] = $i;
        }

        shuffle($values);
        $digest->addValues($values);

        $this->assertEquals(1000, $digest->count());
        $this->assertEquals(500500, $digest->sum());
        $this->assertEquals(500.5, $digest->mean());
        $this->assertEquals(1, $digest->min());
        $this->assertEquals(1000, $digest->max());

        $this->assertEquals(1.5, $digest->quantile(0.001));
        $this->assertEquals(10.5, $digest->quantile(0.01));
        $this->assertEquals(500.25, $digest->quantile(0.5));
        $this->assertEquals(990.25, $digest->quantile(0.99));
        $this->assertEquals(999.5, $digest->quantile(0.999));
    }

    public function test_merge_large_digests()
    {
        $digests = [];
        $digest = new TDigest(100);

        $values = [];
        for ($i = 1; $i <= 1000; ++$i) {
            $values[] = $i;
        }

        shuffle($values);
        for ($i = 0; $i < 10; ++$i) {
            $tmp = new TDigest();
            $tmp->addValues(array_slice($values, $i * 100, 100));
            $digests[] = $tmp;
        }

        $digest = TDigest::merge($digests);

        $this->assertEquals(1000, $digest->count());
        $this->assertEquals(500500, $digest->sum());
        $this->assertEquals(500.5, $digest->mean());
        $this->assertEquals(1, $digest->min());
        $this->assertEquals(1000, $digest->max());

        $this->assertEquals(1.5, $digest->quantile(0.001));
        $this->assertEquals(10.5, $digest->quantile(0.01));
        $this->assertEquals(990.25, $digest->quantile(0.99));
        $this->assertEquals(999.5, $digest->quantile(0.999));
    }

    public function test_addSortedValues_negatives()
    {
        $digest = new TDigest(100);

        $values = [];
        for ($i = 1; $i <= 100; $i++) {
            $values[] = $i;
            $values[] = -$i;
        }

        $digest->addValues($values);

        $this->assertEquals(200, $digest->count());
        $this->assertEquals(0, $digest->sum());
        $this->assertEquals(0, $digest->mean());
        $this->assertEquals(-100, $digest->min());
        $this->assertEquals(100, $digest->max());

        $this->assertEquals(-100, $digest->quantile(0.0));
        $this->assertEquals(-100, $digest->quantile(0.001));
        $this->assertEquals(-98.5, $digest->quantile(0.01));
        $this->assertEquals(98.5, $digest->quantile(0.99));
        $this->assertEquals(100, $digest->quantile(0.999));
        $this->assertEquals(100, $digest->quantile(1.0));
    }

    public function test_merge_negatives()
    {
        $digest1 = new TDigest(100);
        $digest2 = new TDigest(100);

        $values = [];
        $negativeValues = [];
        for ($i = 1; $i <= 100; $i++) {
            $values[] = $i;
            $negativeValues[] = -$i;
        }

        $digest1->addValues($values);
        $digest2->addValues($negativeValues);

        $a = [$digest1, $digest2];
        $digest = TDigest::merge($a);

        $this->assertEquals(200, $digest->count());
        $this->assertEquals(0, $digest->sum());
        $this->assertEquals(0, $digest->mean());
        $this->assertEquals(-100, $digest->min());
        $this->assertEquals(100, $digest->max());

        $this->assertEquals(-100, $digest->quantile(0.0));
        $this->assertEquals(-100, $digest->quantile(0.001));
        $this->assertEquals(-98.5, $digest->quantile(0.01));
        $this->assertEquals(98.5, $digest->quantile(0.99));
        $this->assertEquals(100, $digest->quantile(0.999));
        $this->assertEquals(100, $digest->quantile(1.0));
    }

    public function test_createFromCentroids()
    {
        $centroids = [];
        $digest1 = new TDigest(100);
        $values = [];

        for ($i = 1; $i <= 100; $i++) {
            $values[] = $i;
        }

        $digest1->addSortedValues($values);
        $centroidCount = count($digest1->centroids());
        $digest2 = TDigest::createFromCentroids($digest1->centroids(), $digest1->sum(), $digest1->count(), $digest1->max(), $digest1->min(), 100);
        $digest3 = TDigest::createFromCentroids($digest1->centroids(), $digest1->sum(), $digest1->count(), $digest1->max(), $digest1->min(), $centroidCount - 1);

        $this->assertEquals($digest1->sum(), $digest2->sum());
        $this->assertEquals($digest1->count(), $digest2->count());
        $this->assertEquals($digest1->min(), $digest2->min());
        $this->assertEquals($digest1->max(), $digest2->max());
        $this->assertEquals(count($digest1->centroids()), count($digest2->centroids()));

        $this->assertEquals($digest1->sum(), $digest3->sum());
        $this->assertEquals($digest1->count(), $digest3->count());
        $this->assertEquals($digest1->min(), $digest3->min());
        $this->assertEquals($digest1->max(), $digest3->max());
        $this->assertNotEquals(count($digest1->centroids()), count($digest3->centroids()));
    }

    public function test_largeOutlier()
    {
        $digest = new TDigest(100);
        $values = [];

        for ($i = 0; $i < 19; $i++) {
            $values[] = $i;
        }

        $values[] = 1000000;
        sort($values);

        $digest->addSortedValues($values);
        $this->assertLessThan($digest->quantile(0.90), $digest->quantile(0.5));
    }

    public function test_floating_point_sorted()
    {
        // TODO: is this test needed in PHP?
        // When combining centroids, floating point accuracy can lead to us building
        // and unsorted digest if we are not careful. This tests that we are properly
        // sorting the digest.
        $val = 1.4;
        $digest1 = new TDigest(100);
        $digest2 = new TDigest(100);
        $digest3 = new TDigest(100);

        $values1 = [];
        $values2 = [];
        $values3 = [];
        for ($i = 1; $i <= 100; $i++) {
            $values1[] = $val;
            $values2[] = $val;
            $values3[] = $val;
        }

        $digest1->addSortedValues($values1);
        $digest2->addSortedValues($values2);
        $a = [$digest1, $digest2];
        $merged = TDigest::merge($a);

        $digest3->addSortedValues($values2);
        $digest3->addSortedValues($values3);
        $b = [$digest3, $merged];
        $merged2 = TDigest::merge($b);

        $centroids = $merged2->centroids();
        $tmp = $centroids;
        usort(
            $tmp,
            function (Centroid $a, Centroid $b) {
                if ($a->equals($b)) {
                    return 0;
                }

                return ($a->lessThan($b)) ? -1 : 1;
            }
        );

        $this->assertEquals($tmp, $centroids);
    }

    /**
     * @group toArray
     */
    public function test_toArray(): void
    {
        $digest = new TDigest(100);
        $values = range(1, 100);
        $digest->addSortedValues($values);

        $serialized = $digest->toArray();

        $this->assertEquals(100, $serialized['size']);
        $this->assertEquals(88, count($serialized['centroids']));
        $this->assertEquals(1, $serialized['centroids'][0]['mean']);
        $this->assertEquals(1, $serialized['centroids'][0]['weight']);
        $this->assertEquals(100, $serialized['max']);
        $this->assertEquals(1, $serialized['min']);
        $this->assertEquals(100, $serialized['count']);
        $this->assertEquals(5050, $serialized['sum']);
    }

    /**
     * @group createFromArray
     */
    public function test_createFromArray(): void
    {
        $digest = new TDigest(100);
        $values = range(1, 100);
        $digest->addSortedValues($values);

        $serialized = $digest->toArray();
        $digest = TDigest::createFromArray($serialized);

        $this->assertEquals(100, $digest->maxSize());
        $this->assertEquals(88, count($digest->centroids()));
        $this->assertEquals(100, $digest->max());
        $this->assertEquals(1, $digest->min());
        $this->assertEquals(100, $digest->count());
        $this->assertEquals(5050, $digest->sum());
        $this->assertEquals(50.375, $digest->quantile(0.5));
    }

    /**
     * @group toJson
     */
    public function test_toJson(): void
    {
        $digest = new TDigest(100);
        $values = range(1, 100);
        $digest->addSortedValues($values);

        $serialized = json_decode($digest->toJson(), true);

        $this->assertEquals(100, $serialized['size']);
        $this->assertEquals(88, count($serialized['centroids']));
        $this->assertEquals(1, $serialized['centroids'][0]['mean']);
        $this->assertEquals(1, $serialized['centroids'][0]['weight']);
        $this->assertEquals(100, $serialized['max']);
        $this->assertEquals(1, $serialized['min']);
        $this->assertEquals(100, $serialized['count']);
        $this->assertEquals(5050, $serialized['sum']);
    }

    /**
     * @group createFromJson
     */
    public function test_createFromJson(): void
    {
        $digest = new TDigest(100);
        $values = range(1, 100);
        $digest->addSortedValues($values);

        $serialized = $digest->toJson();
        $digest = TDigest::createFromJson($serialized);

        $this->assertEquals(100, $digest->maxSize());
        $this->assertEquals(88, count($digest->centroids()));
        $this->assertEquals(100, $digest->max());
        $this->assertEquals(1, $digest->min());
        $this->assertEquals(100, $digest->count());
        $this->assertEquals(5050, $digest->sum());
        $this->assertEquals(50.375, $digest->quantile(0.5));
    }

    /**
     * @dataProvider distributionProvider
     */
    public function test_distribution(array $underlyingDistribution, float $quantile, bool $digestMerge): void
    {
        $reasonableError = 0.0;
        $logarithmic = $underlyingDistribution[0];
        $modes = $underlyingDistribution[1];

        if ($quantile == 0.001 || $quantile == 0.999) {
            $reasonableError = 0.001;
        } elseif ($quantile == 0.01 || $quantile == 0.99) {
            $reasonableError = 0.01;
        } elseif ($quantile == 0.25 || $quantile == 0.5 || $quantile == 0.75) {
            $reasonableError = 0.04;
        }

        $errors = [];
        $kNumSamples = 1000;
        $kNumRandomRuns = 3;

        for ($iter = 0; $iter < $kNumRandomRuns; $iter++) {
            $values = [];
            $digest = new TDigest(100);

            if ($logarithmic) {
                $logNormal = new Continuous\LogNormal(0.0, 1.0);

                for ($i = 0; $i < $kNumSamples; $i++) {
                    $mode = (int) $logNormal->rand() % $modes;
                    $values[] = $logNormal->rand() + 100 * $mode;
                }
            } else {
                $uniform = null;
                if ($modes == 1) {
                    $uniform = new Continuous\Uniform(0.0, 1);
                } else {
                    $uniform = new Continuous\Uniform(0.0 + 1, $modes - 1);
                }

                $distributions = [];
                for ($i = 0; $i < $modes; $i++) {
                    $distributions[] = new Continuous\Normal(100 * ($i + 1), 25);
                }

                for ($i = 0; $i < $kNumSamples; $i++) {
                    $index = (int) $uniform->rand();
                    $values[] = $distributions[$index]->rand();
                }
            }

            $digests = [];
            for ($i = 0; $i < $kNumSamples / 1000; $i++) {
                $part = array_slice($values, $i * 1000, 1000);
                if ($digestMerge) {
                    $tmp = new TDigest();
                    $tmp->addValues($part);
                    $digests[] = $tmp;
                } else {
                    $digest->addValues($part);
                }
            }

            sort($values);
            if ($digestMerge) {
                $digest = TDigest::merge($digests);
            }

            $estimated = $digest->quantile($quantile);
            $lowerIndex = null;
            $length = count($values);
            for ($j = 0; $j < $length; $j++) {
                if ($estimated < $values[$j]) {
                    $lowerIndex = $j;
                    break;
                }
            }

            if (!$lowerIndex) {
                $lowerIndex = $length - 1;
            }

            $actualRank = $lowerIndex + 1;
            $actualQuantile = ((float) $actualRank) / $kNumSamples;
            $errors[] = $actualQuantile - $quantile;
        }

        $sum = 0;
        foreach ($errors as $error) {
            $sum += $error;
        }

        $mean = $sum / $kNumRandomRuns;
        $numerator = 0.0;
        foreach ($errors as $error) {
            $numerator += pow($error - $mean, 2);
        }

        $stddev = sqrt($numerator / ($kNumRandomRuns - 1));

        $this->assertGreaterThan($stddev, $reasonableError);
    }

    public function distributionProvider(): array
    {
        $distributions = [
            [true, 1],
            [true, 3],
            [false, 1],
            [false, 10],
        ];

        $quantiles = [
            0.001,
            0.01,
            0.25,
            0.50,
            0.75,
            0.99,
            0.999,
        ];

        $mergeDigests = [
            false,
            true
        ];

        $combinations  = $this->generateAllPossibleCombinations([$distributions, $quantiles, $mergeDigests]);
        return $combinations;
    }

    protected function generateAllPossibleCombinations(array $data, array &$all = [], array $group = [], $value = null, $i = 0)
    {
        $keys = array_keys($data);
        if (isset($value) === true) {
            array_push($group, $value);
        }

        if ($i >= count($data)) {
            array_push($all, $group);
        } else {
            $currentKey     = $keys[$i];
            $currentElement = $data[$currentKey];
            foreach ($currentElement as $val) {
                $this->generateAllPossibleCombinations($data, $all, $group, $val, $i + 1);
            }
        }

        return $all;
    }
}
