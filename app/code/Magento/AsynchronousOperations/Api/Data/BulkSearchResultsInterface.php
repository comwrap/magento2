<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\AsynchronousOperations\Api\Data;

interface BulkSearchResultsInterface extends \Magento\Framework\Api\SearchResultsInterface
{
    /**
     * Get list of bulks.
     *
     * @return \Magento\AsynchronousOperations\Api\Data\BulkSummaryInterface[]
     */
    public function getItems();

    /**
     * Set list of bulks.
     *
     * @param \Magento\AsynchronousOperations\Api\Data\BulkSummaryInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
