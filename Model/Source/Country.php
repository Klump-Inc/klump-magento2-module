<?php

namespace Klump\Payment\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Country implements OptionSourceInterface
{
    /**
     * Possible environment types
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => 'NG',
                'label' => 'Nigeria',
            ],
        ];
    }
}
