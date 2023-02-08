<?php

namespace App\Services\Offer;

use Doctrine\ORM\EntityManager;

/**
 * Class AbstractStatistic
 * @package App\Services
 */
abstract class AbstractStatistic implements AdminStatisticInterface
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * AllocationReserveStatisticChart constructor.
     * @param EntityManager $entityManager
     */
    public function __construct(EntityManager $entityManager)
    {
        $this->em = $entityManager;
    }

    /**
     * @return mixed
     */
    abstract public function getRepository();

    /**
     * @param array $filters
     * @return mixed
     */
    abstract public function getData(array $filters);

    /**
     * @param $data
     * @param $filteredData
     * @return mixed
     */
    abstract protected function compareAndChange($data, &$filteredData);

    /**
     * @return EntityManager
     */
    public function getEntityManger()
    {
        return $this->em;
    }

    /**
     * @param $data
     * @return array
     */
    protected function buildLabels($data)
    {
        $allLabels = [];
        $offersTitle = [];

        //need to fill all points in chart
        foreach ($data as $currentElement) {
            //need unique to grab all data
            $allLabels[] = $currentElement['createdAt']->format('d.m.Y H:i:s');

            if ($currentElement['createdByMobileApplication']) {
                $currentElement['label'] = $currentElement['label'] . ' (Mobile Application)';
            }

            $offersTitle[] = $currentElement['label'];
        }

        return [
            'allLabels'    => array_unique($allLabels),
            'offersTitle'  => array_unique($offersTitle)
        ];
    }

    /**
     * @param $data
     * @param $filteredData
     */
    protected function fillBaseData($data, &$filteredData)
    {
        $data = $this->buildLabels($data);
        $allLabels   = $data['allLabels'];
        $offersTitle = $data['offersTitle'];

        $filteredData['labels'] = $allLabels;

        foreach ($offersTitle as $offer) {
            $arr = [
                'label' => $offer,
                'data' => [],
                'fill' => true,
                'yAxisID' => "y-axis-0",
                'backgroundColor' => "rgba(" . $this->randomColor() . ", 0.6)",
            ];

            foreach ($allLabels as $label) {
                $arr['data'][] = ['date' => $label, 'val' => 0];
            }

            $filteredData['datasets'][] = $arr;
        }
    }

    /**
     * @param $filteredData
     * @param bool $withTime
     */
    protected function cleanUpExtra(&$filteredData, $withTime = false)
    {
        $new = [];

        foreach ($filteredData['datasets'] as $currentDataSet) {
            $temp = [];

            foreach ($currentDataSet['data'] as $key => $subData) {

                if (isset($subData['date'])) {
                    $subData = floatval(0);
                }

                $temp[] = $subData;
            }

            $currentDataSet['data'] = $temp;
            $new[] = $currentDataSet;
        }

        $filteredData['datasets'] = $new;
        $filteredData['labels'] = $withTime ? $filteredData['labels'] : $this->cutLabels($filteredData['labels']);
    }

    /**
     * @param array $labels
     * @return array
     */
    protected function cutLabels(array $labels)
    {
        $labelsWithoutTime = [];

        foreach ($labels as $label) {
            $labelsWithoutTime[] = substr($label, 0, 10);
        }

        return $labelsWithoutTime;
    }

    /**
     * @return string
     */
    public function randomColor()
    {
        return rand(0, 255) . ', ' . rand(0, 255) . ', ' . rand(0, 255);
    }
}