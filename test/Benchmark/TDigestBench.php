<?php

namespace ablagoev\TDigest\Test\Benchmark;

use MathPHP\Probability\Distribution\Continuous;
use ablagoev\TDigest\TDigest;

class TDigestBench
{
    protected $values;

    /**
     * @Revs(10)
     * @Iterations(500)
     * @ParamProviders("generateUniformValues")
     * @Groups({"addSortedValues"})
     * @RetryThreshold(5.0)
     */
    public function benchAddSortedValues(array $params): void
    {
        $digest = new TDigest(100);
        $digest->addSortedValues($params['values']);
    }

    /**
     * @Revs(10)
     * @Iterations(100)
     * @ParamProviders("generateLogNormalValues")
     * @Groups({"merge"})
     * @RetryThreshold(5.0)
     */
    public function benchMerge(array $params): void
    {
        $digests = [];
        $length = count($params['values']);
        for ($i = 0; $i <= $params['length'] / 100; ++$i) {
            $tmp = new TDigest(100);
            $values = array_slice($params['values'], $i * 100, 100);
            $tmp->addSortedValues($values);
            $digests[] = $tmp;
        }

        $digest = TDigest::merge($digests);
    }

    /**
     * @Revs(10)
     * @Iterations(100)
     * @ParamProviders("generateRandomDigest")
     * @Groups({"quantile"})
     * @RetryThreshold(5.0)
     */
    public function benchQuantile(array $params): void
    {

        $quantile = 0.5;
        $params['digest']->quantile($quantile);
    }

    public function generateUniformValues(): array
    {
        $uniform = new Continuous\Uniform(0.0, 1);
        $values = [];
        for ($i = 0; $i < 1000; $i++) {
            $values[] = $uniform->rand();
        }

        sort($values);

        return [
            ['values' => $values],
        ];
    }

    public function generateLogNormalValues(): array
    {
        $logNormal = new Continuous\LogNormal(0.0, 1.0);
        $values = [];
        for ($i = 0; $i < 1000; $i++) {
            $values[] = $logNormal->rand();
        }

        sort($values);

        return [
            ['values' => $values, 'length' => count($values)],
        ];
    }

    public function generateRandomDigest(): array
    {
        $values = $this->generateUniformValues()[0]['values'];
        $digest = new TDigest(100);
        $digest->addSortedValues($values);

        return [
            ['digest' => $digest],
        ];
    }
}
