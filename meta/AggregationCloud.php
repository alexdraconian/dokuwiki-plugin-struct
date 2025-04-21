<?php

namespace dokuwiki\plugin\struct\meta;

class AggregationCloud extends Aggregation
{
    /** @var int */
    protected $max;

    /** @var int */
    protected $min;

    /** @inheritdoc */
    public function render($showNotFound = false)
    {

        if ($this->mode !== 'xhtml') return;

        $rows = $this->searchConfig->getRows();
        $this->max = $rows[0]['count'];
        $this->min = end($rows)['count'];

        $this->sortResults($rows);
        $this->startList();
        foreach ($rows as $result) {
            $this->renderTag($result);
        }
        $this->finishList();
    }

    /**
     * Render a tag of the cloud
     *
     * @param ['tag' => Value, 'count' => int] $result
     */
    protected function renderTag($result)
    {
        /**
         * @var Value $value
         */
        $value = $result['tag'];
        $count = $result['count'];
        if ($value->isEmpty()) {
            return;
        }

        $type = strtolower($value->getColumn()->getType()->getClass());
        $weight = $this->getWeight($count, $this->min, $this->max);

        if (!empty($this->data['target'])) {
            $target = $this->data['target'];
        } else {
            global $INFO;
            $target = $INFO['id'];
        }

        $tagValue = $value->getDisplayValue();
        if (is_array($tagValue)) {
            $tagValue = $tagValue[0];
        }
        $key = $value->getColumn()->getFullQualifiedLabel() . '=';
        $filter = SearchConfigParameters::$PARAM_FILTER . '[' . urlencode($key) . ']=' . urlencode($tagValue);

        $this->renderer->listitem_open(1);
        $this->renderer->listcontent_open();

        if ($this->mode == 'xhtml') {
            $this->renderer->doc .=
                "<div style='font-size:$weight%' data-count='$count' class='cloudtag struct_$type'>";
        }

        $showCount = $this->searchConfig->getConf()['summarize'] ? $count : 0;
        $value->renderAsTagCloudLink($this->renderer, $this->mode, $target, $filter, $weight, $showCount);

        if ($this->mode == 'xhtml') {
            $this->renderer->doc .= '</div>';
        }

        $this->renderer->listcontent_close();
        $this->renderer->listitem_close();
    }

    /**
     * This interpolates the weight between 70 and 150 based on $min, $max and $current
     *
     * @param int $current
     * @param int $min
     * @param int $max
     * @return int
     */
    protected function getWeight($current, $min, $max)
    {
        if ($min == $max) {
            return 100;
        }
        return round(($current - $min) / ($max - $min) * 80 + 70);
    }

    /**
     * Sort the list of results
     */
    protected function sortResults(&$rows)
    {
        usort($rows, function ($a, $b) {
            $asort = $a['tag']->getColumn()->getType()->getSortString($a['tag']);
            $bsort = $b['tag']->getColumn()->getType()->getSortString($b['tag']);
            if ($asort < $bsort) {
                return -1;
            }
            if ($asort > $bsort) {
                return 1;
            }
            return 0;
        });
    }

    protected function startList()
    {
        $this->renderer->listu_open();
    }

    protected function finishList()
    {
        $this->renderer->listu_close();
    }
}
