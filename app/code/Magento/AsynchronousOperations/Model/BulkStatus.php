<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\AsynchronousOperations\Model;

use Magento\AsynchronousOperations\Api\BulkSummaryRepositoryInterface;
use Magento\AsynchronousOperations\Api\Data\OperationInterface;
use Magento\AsynchronousOperations\Api\Data\BulkSummaryInterface;
use Magento\AsynchronousOperations\Model\Repository\Registry as BulkRepositoryRegistry;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\AsynchronousOperations\Model\BulkStatus\CalculatedStatusSql;
use Magento\Framework\EntityManager\MetadataPool;

/**
 * Class BulkStatus
 */
class BulkStatus implements \Magento\Framework\Bulk\BulkStatusInterface
{
    /**
     * @var \Magento\AsynchronousOperations\Api\Data\BulkSummaryInterfaceFactory
     */
    private $bulkCollectionFactory;

    /**
     * @var \Magento\AsynchronousOperations\Api\Data\OperationInterfaceFactory
     */
    private $operationCollectionFactory;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var CalculatedStatusSql
     */
    private $calculatedStatusSql;

    /**
     * @var MetadataPool
     */
    private $metadataPool;

    /**
     * @var BulkSummaryRepositoryInterface
     */
    private $bulkSummaryRepository;

    /**
     * @var BulkRepositoryRegistry
     */
    private $bulkRepositoryRegistry;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var SortOrderBuilder
     */
    private $sortOrderBuilder;

    /**
     * BulkStatus constructor.
     * @param ResourceModel\Bulk\CollectionFactory $bulkCollection
     * @param ResourceModel\Operation\CollectionFactory $operationCollection
     * @param ResourceConnection $resourceConnection
     * @param CalculatedStatusSql $calculatedStatusSql
     * @param MetadataPool $metadataPool
     * @param BulkRepositoryRegistry $bulkRepositoryRegistry
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function __construct(
        \Magento\AsynchronousOperations\Model\ResourceModel\Bulk\CollectionFactory $bulkCollection,
        \Magento\AsynchronousOperations\Model\ResourceModel\Operation\CollectionFactory $operationCollection,
        ResourceConnection $resourceConnection,
        CalculatedStatusSql $calculatedStatusSql,
        MetadataPool $metadataPool,
        BulkRepositoryRegistry $bulkRepositoryRegistry,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SortOrderBuilder $sortOrderBuilder
    ) {
        $this->operationCollectionFactory = $operationCollection;
        $this->bulkCollectionFactory = $bulkCollection;
        $this->resourceConnection = $resourceConnection;
        $this->calculatedStatusSql = $calculatedStatusSql;
        $this->metadataPool = $metadataPool;
        $this->bulkRepositoryRegistry = $bulkRepositoryRegistry;
        $this->bulkSummaryRepository = $this->bulkRepositoryRegistry->getRepository();
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->sortOrderBuilder = $sortOrderBuilder;
    }

    /**
     * @inheritDoc
     */
    public function getFailedOperationsByBulkId($bulkUuid, $failureType = null)
    {
        $failureCodes = $failureType
            ? [$failureType]
            : [
                OperationInterface::STATUS_TYPE_RETRIABLY_FAILED,
                OperationInterface::STATUS_TYPE_NOT_RETRIABLY_FAILED
            ];

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter("bulk_uuid", $bulkUuid, "eq")
            ->addFilter("status", $failureCodes, "in")
            ->create();

        return $this->bulkSummaryRepository
            ->getOperationsList($searchCriteria)
            ->getItems();
    }

    /**
     * @inheritDoc
     */
    public function getOperationsCountByBulkIdAndStatus($bulkUuid, $status)
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter("bulk_uuid", $bulkUuid, "eq")
            ->addFilter("status", $status, "eq")
            ->create();

        return $this->bulkSummaryRepository
            ->getOperationsList($searchCriteria)
            ->getTotalCount();
    }

    /**
     * @inheritDoc
     */
    public function getBulksByUser($userId)
    {
        $statusesArray = [
            OperationInterface::STATUS_TYPE_RETRIABLY_FAILED,
            OperationInterface::STATUS_TYPE_NOT_RETRIABLY_FAILED,
            BulkSummaryInterface::NOT_STARTED,
            OperationInterface::STATUS_TYPE_OPEN,
            OperationInterface::STATUS_TYPE_COMPLETE
        ];

        /** @var SearchCriteria $userBulksSearchCriteria */
        $userBulksSearchCriteria = $this->searchCriteriaBuilder
            ->addSortOrder($this->sortOrderBuilder->setField('start_time')->setDescendingDirection()->create())
            ->addFilter("user_id", $userId, "eq")
            ->create();

        $userBulksList = $this->bulkSummaryRepository
            ->getBulksList($userBulksSearchCriteria);

        /** @var BulkSummaryInterface[] $userBulks */
        $userBulks = $userBulksList->getItems();

        /** @var SortOrder $operationSortOrder */
        $operationSortOrder = $this->sortOrderBuilder
            ->setField('status')
            ->setDescendingDirection()
            ->create();

        foreach ($userBulks as $userBulkId => $userBulk) {
            /** @var SearchCriteria $operationsSearchCriteria */
            $operationsSearchCriteria = $this->searchCriteriaBuilder
                ->addFilter('bulk_uuid', $userBulk->getUuid(), 'eq')
                ->addSortOrder($operationSortOrder)
                ->setPageSize(1)
                ->setCurrentPage(1)
                ->create();
            $bulkOperationsList = $this->bulkSummaryRepository->getOperationsList($operationsSearchCriteria);
            /** @var OperationInterface[] $bulkOperations */
            $bulkOperations = $bulkOperationsList->getItems();
            if (count($bulkOperations) == 0) {
                $userBulk->setStatus(0);
            } else {
                $bulkOperation = array_shift($bulkOperations);
                $userBulk->setStatus((int)$bulkOperation->getStatus());
            }
        }

        /** @var BulkSummaryInterface[] $sortedUserBulks */
        $sortedUserBulks = [];
        foreach ($statusesArray as $status) {
            foreach ($userBulks as $userBulkId => $userBulk) {
                if ($userBulk->getStatus() == $status) {
                    $sortedUserBulks[$userBulkId] = $userBulk;
                }
            }
        }

        return $sortedUserBulks;
    }

    /**
     * @inheritDoc
     */
    public function getBulkStatus($bulkUuid)
    {
        /**
         * Number of operations that has been processed (i.e. operations with any status but 'open')
         */
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter("bulk_uuid", $bulkUuid, "eq")
            ->create();
        $allProcessedOperationsQty = $this->bulkSummaryRepository->getOperationsList($searchCriteria)->getTotalCount();

        if ($allProcessedOperationsQty == 0) {
            return BulkSummaryInterface::NOT_STARTED;
        }

        /**
         * Total number of operations that has been scheduled within the given bulk
         */
        $allOperationsQty = (int)$this->bulkSummaryRepository->getBulkByUuid($bulkUuid)->getOperationCount();

        /**
         * Number of operations that has not been started yet (i.e. operations with status 'open')
         */
        $allOpenOperationsQty = $allOperationsQty - $allProcessedOperationsQty;

        /**
         * Number of operations that has been completed successfully
         */
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter("bulk_uuid", $bulkUuid, "eq")
            ->addFilter("status", OperationInterface::STATUS_TYPE_COMPLETE, "eq")
            ->create();
        $allCompleteOperationsQty = $this->bulkSummaryRepository->getOperationsList($searchCriteria)->getTotalCount();

        if ($allCompleteOperationsQty == $allOperationsQty) {
            return BulkSummaryInterface::FINISHED_SUCCESSFULLY;
        }

        if ($allOpenOperationsQty > 0 && $allOpenOperationsQty !== $allOperationsQty) {
            return BulkSummaryInterface::IN_PROGRESS;
        }

        return BulkSummaryInterface::FINISHED_WITH_FAILURE;
    }
}
