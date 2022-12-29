<?php

namespace ablagoev\TDigest;

class Centroid
{
    protected float $mean = 0.0;
    protected float $weight = 0.0;

    public function __construct(float $mean = 0.0, float $weight = 1.0)
    {
        $this->mean = $mean;
        $this->weight = $weight;
    }

    public function toArray()
    {
        return [
            'mean' => $this->mean,
            'weight' => $this->weight,
        ];
    }

    public function mean()
    {
        return $this->mean;
    }

    public function weight()
    {
        return $this->weight;
    }

    public function add(float $sum, float $weight)
    {
        $sum += $this->mean * $this->weight;

        $this->weight += $weight;
        $this->mean = $sum / $this->weight;

        return $sum;
    }

    public function lessThan(Centroid &$other): bool
    {
        return $this->mean() < $other->mean();
    }

    public function equals(Centroid &$other): bool
    {
        return $this->mean() == $other->mean();
    }

    public static function sort(array &$centroids): void
    {
        usort(
            $centroids,
            function (Centroid $a, Centroid $b) {
                if ($a->equals($b)) {
                    return 0;
                }

                return ($a->lessThan($b)) ? -1 : 1;
            }
        );
    }
}
